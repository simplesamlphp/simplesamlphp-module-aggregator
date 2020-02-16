<?php

use SimpleSAML\Configuration;
use SimpleSAML\Module;
use Webmozart\Assert\Assert;

/**
 * Hook to add the aggregator list to the frontpage.
 *
 * @param array &$links  The links on the frontpage, split into sections.
 * @return void
 */
function aggregator_hook_frontpage(array &$links): void
{
    Assert::keyExists($links, 'links');

    $links['federation'][] = [
        'href' => Module::getModuleURL('aggregator/'),
        'text' => '{aggregator:aggregator:frontpage_link}',
    ];
}
