<?php

$config = \SimpleSAML\Configuration::getInstance();
$gConfig = \SimpleSAML\Configuration::getConfig('module_aggregator.php');


// Get list of aggregators
$aggregators = $gConfig->getConfigItem('aggregators');

// If aggregator ID is not provided, show the list of available aggregates
if (!array_key_exists('id', $_GET)) {
    $t = new \SimpleSAML\XHTML\Template($config, 'aggregator:list.php');
    $t->data['sources'] = $aggregators->getOptions();
    $t->show();
    exit;
}
$id = $_GET['id'];
if (!in_array($id, $aggregators->getOptions())) {
    throw new \SimpleSAML\Error\NotFound('No aggregator with id ' . var_export($id, true) . ' found.');
}

$aConfig = $aggregators->getConfigItem($id);

$aggregator = new \SimpleSAML\Module\aggregator\Aggregator($gConfig, $aConfig, $id);

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
    \SimpleSAML\Utils\XML::formatDOMElement($xml);
}

$metadata = '<?xml version="1.0"?>' . "\n" . $xml->ownerDocument->saveXML($xml);

header('Content-Type: ' . $mimetype);
header('Content-Length: ' . strlen($metadata));

echo $metadata;
