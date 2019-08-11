#!/usr/bin/env php
<?php

require_once(dirname(dirname(dirname(dirname(__FILE__)))) . '/lib/_autoload.php');

if ($argc < 2) {
    fwrite(STDERR, "Missing aggregator id.\n");
    exit(1);
}
$id = $argv[1];

$gConfig = \SimpleSAML\Configuration::getConfig('module_aggregator.php');
$aggregators = $gConfig->getConfigItem('aggregators');

$aConfig = $aggregators->getConfigItem($id, null);
if ($aConfig === null) {
    fwrite(STDERR, 'No aggregator with id ' . var_export($id, null) . " found.\n");
    exit(1);
}

$aggregator = new \SimpleSAML\Module\aggregator\Aggregator($gConfig, $aConfig, $id);

$xml = $aggregator->getMetadataDocument();
echo($xml->saveXML());
