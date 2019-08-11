<?php

namespace SimpleSAML\Module\aggregator;

/**
 * Aggregates metadata for multiple sources into one signed file
 *
 * @author Andreas Åkre Solberg <andreas.solberg@uninett.no>
 * @package simpleSAMLphp
 */

class Aggregator
{
    /** @var \SimpleSAML\Configuration Configuration for the whole aggregator module */
    private $gConfig;

    /** @var \SimpleSAML\Configuration Configuration for the specific aggregate */
    private $aConfig;

    /** @var array */
    private $sets;

    /** @var array */
    private $excludeTags = [];

    /** @var string */
    private $id;

    /**
     * Constructor for the Aggregator.
     * @param \SimpleSAML\Configuration $gConfig
     * @param \SimpleSAML\Configuration $aConfig
     * @param string $id
     */
    public function __construct($gConfig, $aConfig, $id)
    {
        $this->gConfig = $gConfig;
        $this->aConfig = $aConfig;
        $this->id = $id;

        $this->sets = [
            'saml20-idp-remote',
            'saml20-sp-remote',
            'shib13-idp-remote',
            'shib13-sp-remote',
            'attributeauthority-remote'
        ];

        if ($this->aConfig->hasValue('set')) {
            $this->limitSets($this->aConfig->getString('set'));
        }
    }


    /**
     * @param string $set
     * @return void
     */
    public function limitSets($set)
    {
        if (is_array($set)) {
            $this->sets = array_intersect($this->sets, $set);
            return;
        }

        switch($set) {
            case 'saml2' :
                $this->sets = array_intersect($this->sets, ['saml20-idp-remote', 'saml20-sp-remote']);
                break;
            case 'shib13' :
                $this->sets = array_intersect($this->sets, ['shib13-idp-remote', 'shib13-sp-remote']);
                break;
            case 'idp' :
                $this->sets = array_intersect($this->sets, ['saml20-idp-remote', 'shib13-idp-remote', 'attributeauthority-remote']);
                break;
            case 'sp' :
                $this->sets = array_intersect($this->sets, ['saml20-sp-remote', 'shib13-sp-remote']);
                break;
            default:
                $this->sets = array_intersect($this->sets, [$set]);
                break;
        }
    }


    /**
     * Add tag to excelude when collecting source metadata.
     * 
     * @param string|array $exclude  May be string or array identifying a tag to exclude.
     * @return void
     */
    public function exclude($exclude)
    {
        $this->excludeTags = array_merge($this->excludeTags, \SimpleSAML\Utils\Arrays::arrayize($exclude));
    }


    /**
     * Returns a list of entities with metadata
     * @return array
     */ 
    public function getSources()
    {
        $sourcesDef = $this->aConfig->getArray('sources');

        try {
            $sources = \SimpleSAML\Metadata\MetaDataStorageSource::parseSources($sourcesDef);
        } catch (\Exception $e) {
            throw new \Exception('Invalid aggregator source configuration for aggregator '.var_export($this->id, true).': '.$e->getMessage());
        }

        /* Find list of all available entities. */
        $entities = [];

        foreach ($sources as $source) {
            foreach ($this->sets as $set) {
                foreach ($source->getMetadataSet($set) as $entityId => $metadata) {
                    $metadata['entityid'] = $entityId;
                    $metadata['metadata-set'] = $set;

                    if (isset($metadata['tags']) && 
                            count(array_intersect($this->excludeTags, $metadata['tags'])) > 0) {
                        \SimpleSAML\Logger::debug('Excluding entity ID [' . $entityId . '] becuase it is tagged with one of [' . 
                            var_export($this->excludeTags, true) . ']');
                        continue;
                    } else {
                        #echo('<pre>'); print_r($metadata); exit;
                    }
                    if (!array_key_exists($entityId, $entities)) {
                        $entities[$entityId] = [];
                    }

                    if (array_key_exists($set, $entities[$entityId])) {
                        /* Entity already has metadata for the given set. */
                        continue;
                    }
                    $entities[$entityId][$set] = $metadata;
                }
            }
        }
        return $entities;
    }


    /**
     * @return int|null
     */
    public function getMaxDuration()
    {
        if ($this->aConfig->hasValue('maxDuration')) {
            return $this->aConfig->getInteger('maxDuration');
        } elseif ($this->gConfig->hasValue('maxDuration')) {
            return $this->gConfig->getInteger('maxDuration');
        }
        return null;
    }


    /**
     * @return bool
     */
    public function getReconstruct()
    {
        if ($this->aConfig->hasValue('reconstruct')) {
            return $this->aConfig->getBoolean('reconstruct');
        } elseif ($this->gConfig->hasValue('reconstruct')) {
            return $this->gConfig->getBoolean('reconstruct');
        }
        return false;
    }


    /**
     * @return bool
     */
    public function shouldSign()
    {
        if ($this->aConfig->hasValue('sign.enable')) {
            return $this->aConfig->getBoolean('sign.enable');
        } else if ($this->gConfig->hasValue('sign.enable')) {
            return $this->gConfig->getBoolean('sign.enable');
        }
        return false;
    }


    /**
     * @return array
     */
    public function getSigningInfo()
    {
        if ($this->aConfig->hasValue('sign.privatekey')) {
            return [
                'privatekey' => $this->aConfig->getString('sign.privatekey'),
                'privatekey_pass' => $this->aConfig->getString('sign.privatekey_pass', null),
                'certificate' => $this->aConfig->getString('sign.certificate'),
                'id' => 'ID'
            ];
        }

        return [
            'privatekey' => $this->gConfig->getString('sign.privatekey'),
            'privatekey_pass' => $this->gConfig->getString('sign.privatekey_pass', null),
            'certificate' => $this->gConfig->getString('sign.certificate'),
            'id' => 'ID'
        ];
    }


    /**
     * @return \DOMElement
     */
    public function getMetadataDocument()
    {
        // Get metadata entries
        $entities = $this->getSources();
        $maxDuration = $this->getMaxDuration();
        $reconstruct = $this->getReconstruct();

        $entitiesDescriptor = new \SAML2\XML\md\EntitiesDescriptor();
        $entitiesDescriptor->Name = $this->id;
        $entitiesDescriptor->validUntil = time() + $maxDuration;

        // add RegistrationInfo extension if enabled
        if ($this->gConfig->hasValue('RegistrationInfo')) {
            $ri = new \SAML2\XML\mdrpi\RegistrationInfo();
            foreach ($this->gConfig->getArray('RegistrationInfo') as $riName => $riValues) {
                switch ($riName) {
                    case 'authority':
                        $ri->registrationAuthority = $riValues;
                        break;
                    case 'instant':
                        $ri->registrationInstant = \SAML2\Utils::xsDateTimeToTimestamp($riValues);
                        break;
                    case 'policies':
                        $ri->RegistrationPolicy = $riValues;
                        break;
                }
            }
            $entitiesDescriptor->Extensions[] = $ri;
        }

        /* Build EntityDescriptor elements for them. */
        foreach ($entities as $entity => $sets) {
            $entityDescriptor = null;
            foreach ($sets as $set => $metadata) {
                if (!array_key_exists('entityDescriptor', $metadata)) {
                    /* One of the sets doesn't contain an EntityDescriptor element. */
                    $entityDescriptor = false;
                    break;
                }

                if ($entityDescriptor == null) {
                    /* First EntityDescriptor elements. */
                    $entityDescriptor = $metadata['entityDescriptor'];
                    continue;
                }

                assert(is_string($entityDescriptor));
                if ($entityDescriptor !== $metadata['entityDescriptor']) {
                    /* Entity contains multiple different EntityDescriptor elements. */
                    $entityDescriptor = false;
                    break;
                }
            }

            if (is_string($entityDescriptor) && !$reconstruct) {
                /* All metadata sets for the entity contain the same entity descriptor. Use that one. */
                $tmp = new \DOMDocument();
                $tmp->loadXML(base64_decode($entityDescriptor));
                $entitiesDescriptor->children[] = new \SAML2\XML\md\EntityDescriptor($tmp->documentElement);
            } else {
                $tmp = new \SimpleSAML\Metadata\SAMLBuilder($entity, $maxDuration, $maxDuration);

                $orgmeta = null;
                foreach ($sets as $set => $metadata) {
                    $tmp->addMetadata($set, $metadata);
                    $orgmeta = $metadata;
                }
                $tmp->addOrganizationInfo($orgmeta);
                $entitiesDescriptor->children[] = new \SAML2\XML\md\EntityDescriptor($tmp->getEntityDescriptor());
            }
        }

        $document = $entitiesDescriptor->toXML();

        // sign the metadata if enabled
        if ($this->shouldSign()) {
            $signer = new \SimpleSAML\XML\Signer($this->getSigningInfo());
            $signer->sign($document, $document, $document->firstChild);
        }

        return $document;
    }
}
