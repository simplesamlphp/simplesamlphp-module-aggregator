<?php

use SimpleSAML\Configuration;
use SimpleSAML\Error;
use SimpleSAML\Module\aggregator\Aggregator;
use SimpleSAML\Utils;
use SimpleSAML\XHTML\Template;

$config = Configuration::getInstance();
$gConfig = Configuration::getConfig('module_aggregator.php');


// Get list of aggregators
$aggregators = $gConfig->getConfigItem('aggregators');

if ($aggregators === null) {
    throw new Error\CriticalConfigurationError('No aggregators found in module configuration.');
}

// If aggregator ID is not provided, show the list of available aggregates
if (!array_key_exists('id', $_GET)) {
    $t = new Template($config, 'aggregator:list.twig');
    $t->data['sources'] = $aggregators->getOptions();
    $t->send();
    exit;
}
$id = $_GET['id'];
if (!in_array($id, $aggregators->getOptions())) {
    throw new Error\NotFound('No aggregator with id ' . var_export($id, true) . ' found.');
}

/** @psalm-var \SimpleSAML\Configuration $aConfig  We've checked it exists right above */
$aConfig = $aggregators->getConfigItem($id);

$aggregator = new Aggregator($gConfig, $aConfig, $id);

if (isset($_REQUEST['set'])) {
    $aggregator->limitSets($_REQUEST['set']);
}
if (isset($_REQUEST['exclude'])) {
    $aggregator->exclude($_REQUEST['exclude']);
}

$xml = $aggregator->getMetadataDocument();

$mimetype = 'application/samlmetadata+xml';
$allowedmimetypes = [
    'text/plain',
    'application/samlmetadata-xml',
    'application/xml',
];

if (isset($_GET['mimetype']) && in_array($_GET['mimetype'], $allowedmimetypes)) {
    $mimetype = $_GET['mimetype'];
}

if ($mimetype === 'text/plain') {
    Utils\XML::formatDOMElement($xml);
}

$metadata = '<?xml version="1.0"?>' . "\n" . $xml->ownerDocument->saveXML($xml);

header('Content-Type: ' . $mimetype);
header('Content-Length: ' . strlen($metadata));

echo $metadata;
