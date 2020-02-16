<?php

use DOMDocument;
use SimpleSAML\Configuration;
use SimpleSAML\Error;
use SimpleSAML\Module\aggregator\Aggregator;
use SimpleSAML\Module\aggregator\Arp;
use SimpleSAML\Utils;
use SimpleSAML\XHTML\Template;
use SimpleSAML\XML;

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

$md = $aggregator->getSources();

if (!array_key_exists('attributemap', $_REQUEST)) {
    throw new Error\BadRequest('Missing attributemap in request');
}
$attributemap = $_REQUEST['attributemap'];

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
    throw new Error\BadRequest('Requested URL contained a backslash.');
} elseif (strpos($attributemap, './') !== false) {
    throw new Error\BadRequest('Requested URL contained \'./\'.');
}

$arp = new Arp($md, $attributemap, $prefix, $suffix);

$arpxml = $arp->getXML();

$xml = new DOMDocument();
$xml->loadXML($arpxml);

$firstelement = $xml->documentElement;

if ($aggregator->shouldSign()) {
    $signinfo = $aggregator->getSigningInfo();
    $signer = new XML\Signer($signinfo);
    /** @psalm-suppress ArgumentTypeCoercion */
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
    Utils\XML::formatDOMElement($xml->documentElement);
}

header('Content-Type: ' . $mimetype);

echo $xml->saveXML();
