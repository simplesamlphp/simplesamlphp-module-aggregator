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

$md = $aggregator->getSources();

$attributemap = null;
if (isset($_REQUEST['attributemap'])) {
    $attributemap = $_REQUEST['attributemap'];
}
$prefix = '';
if (isset($_REQUEST['prefix'])) {
    $prefix = $_REQUEST['prefix'];
}
$suffix = '';
if (isset($_REQUEST['suffix'])) {
    $suffix = $_REQUEST['suffix'];
}

/* Make sure that the request isn't suspicious (contains references to current
 * directory or parent directory or anything like that. Searching for './' in the
 * URL will detect both '../' and './'. Searching for '\' will detect attempts to
 * use Windows-style paths.
 */
if (strpos($attributemap, '\\') !== false) {
    throw new SimpleSAML\Error\BadRequest('Requested URL contained a backslash.');
} elseif (strpos($attributemap, './') !== false) {
    throw new \SimpleSAML\Error\BadRequest('Requested URL contained \'./\'.');
}

$arp = new \SimpleSAML\Module\aggregator\Arp($md, $attributemap, $prefix, $suffix);

$arpxml = $arp->getXML();

$xml = new \DOMDocument();
$xml->loadXML($arpxml);

$firstelement = $xml->firstChild;

if ($aggregator->shouldSign()) {
    $signinfo = $aggregator->getSigningInfo();
    $signer = new \SimpleSAML\XML\Signer($signinfo);
    $signer->sign($firstelement, $firstelement, $firstelement->firstChild);
}

$mimetype = 'application/samlmetadata-xml';
$allowedmimetypes = [
    'text/plain',
    'application/samlmetadata-xml',
    'application/xml',
];

if (isset($_GET['mimetype']) && in_array($_GET['mimetype'], $allowedmimetypes)) {
    $mimetype = $_GET['mimetype'];
}

if ($mimetype === 'text/plain') {
    \SimpleSAML\Utils\XML::formatDOMElement($xml->documentElement);
}

header('Content-Type: ' . $mimetype);

echo $xml->saveXML();
