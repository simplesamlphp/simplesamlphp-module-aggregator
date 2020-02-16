<?php

namespace SimpleSAML\Module\aggregator;

use SimpleSAML\Configuration;

/*
 * @author Andreas Åkre Solberg <andreas.solberg@uninett.no>
 * @package simpleSAMLphp
 */
class Arp
{
    /** @var array $metadata */
    private $metadata;

    /** @var array $attributes */
    private $attributes;

    /** @var string $prefix */
    private $prefix;

    /** @var string $suffix */
    private $suffix;


    /**
     * Constructor
     *
     * @param array $metadata
     * @param string|null $attributemap
     * @param string $prefix
     * @param string $suffix
     */
    public function __construct(array $metadata, ?string $attributemap, string $prefix, string $suffix)
    {
        $this->metadata = $metadata;

        $this->prefix = $prefix;
        $this->suffix = $suffix;

        if (!empty($attributemap)) {
            $this->loadAttributeMap($attributemap);
        }
    }


    /**
     * @param string $attributemap
     * @return void
     */
    private function loadAttributeMap(string $attributemap): void
    {
        $config = Configuration::getInstance();
        /** @psalm-var string $path */
        $path = $config->getPathValue('attributemap', 'attributemap/');
        include($path . $attributemap . '.php');
        /** @psalm-var array $attributemap */
        $this->attributes = $attributemap;
    }


    /**
     * @param string $name
     * @return string
     */
    private function surround(string $name): string
    {
        $ret = '';
        if (!empty($this->prefix)) {
            $ret .= htmlspecialchars($this->prefix);
        }
        $ret .= $name;
        if (!empty($this->suffix)) {
            $ret .= htmlspecialchars($this->suffix);
        }
        return $ret;
    }


    /**
     * @param string $name
     * @return string
     */
    private function getAttributeID(string $name): string
    {
        if (empty($this->attributes)) {
            return $this->surround($name);
        }
        if (array_key_exists($name, $this->attributes)) {
            return $this->surround($this->attributes[$name]);
        }
        return $this->surround($name);
    }


    /**
     * @return string
     */
    public function getXML(): string
    {
        $xml = <<<EOT
<?xml version="1.0" encoding="UTF-8"?>
<AttributeFilterPolicyGroup id="urn:mace:funet.fi:haka:kalmar" xmlns="urn:mace:shibboleth:2.0:afp"
    xmlns:basic="urn:mace:shibboleth:2.0:afp:mf:basic" xmlns:saml="urn:mace:shibboleth:2.0:afp:mf:saml"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="urn:mace:shibboleth:2.0:afp classpath:/schema/shibboleth-2.0-afp.xsd
                        urn:mace:shibboleth:2.0:afp:mf:basic classpath:/schema/shibboleth-2.0-afp-mf-basic.xsd
                        urn:mace:shibboleth:2.0:afp:mf:saml classpath:/schema/shibboleth-2.0-afp-mf-saml.xsd">
EOT;

        foreach ($this->metadata as $metadata) {
            #echo '<pre>'; print_r($metadata); # exit;
            if (isset($metadata['saml20-sp-remote'])) {
                #echo '<pre>'; print_r($metadata); exit;
                $xml .= $this->getEntryXML($metadata['saml20-sp-remote']);
            }
        }

        $xml .= '</AttributeFilterPolicyGroup>';
        return $xml;
    }


    /**
     * @param array $entry
     * @return string
     */
    private function getEntryXML(array $entry): string
    {
        $entityid = $entry['entityid'];
        return '<AttributeFilterPolicy id="' .
            $entityid . '"><PolicyRequirementRule xsi:type="basic:AttributeRequesterString" value="' .
            $entityid . '" />' . $this->getEntryXMLcontent($entry) . '</AttributeFilterPolicy>';
    }


    /**
     * @param array $entry
     * @return string
     */
    private function getEntryXMLcontent(array $entry): string
    {
        $ids = [];
        if (!array_key_exists('attributes', $entry)) {
            return '';
        }

        $ret = '';
        foreach ($entry['attributes'] as $a) {
            $ret .= '<AttributeRule attributeID="' . $this->getAttributeID($a) .
                '"><PermitValueRule xsi:type="basic:ANY" /></AttributeRule>';
        }
        return $ret;
    }
}
