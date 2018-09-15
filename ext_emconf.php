<?php

/***************************************************************
 * Extension Manager/Repository config file for ext "simple_api".
 *
 * Auto generated 19-04-2017 10:01
 *
 * Manual updates:
 * Only the data in the array - everything else is removed by next
 * writing. "version" and "dependencies" must not be touched!
 ***************************************************************/

$EM_CONF[$_EXTKEY] = [
    'title' => 'Simple API',
    'description' => 'Service to route HTTP/REST requests to your own TYPO3 controllers.',
    'category' => 'services',
    'author' => 'Xavier Perseguers (Causal)',
    'author_email' => 'xavier@causal.ch',
    'shy' => '',
    'dependencies' => '',
    'conflicts' => '',
    'priority' => '',
    'module' => '',
    'doNotLoadInFE' => 1,
    'state' => 'stable',
    'internal' => '',
    'uploadfolder' => 0,
    'createDirs' => '',
    'modify_tables' => '',
    'clearCacheOnLoad' => 0,
    'lockType' => '',
    'author_company' => 'Causal Sàrl',
    'version' => '1.1.0-dev',
    'constraints' => [
        'depends' => [
            'php' => '5.5.0-7.2.99',
            'typo3' => '7.6.0-8.7.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
    '_md5_values_when_last_written' => '',
    'suggests' => [],
    'autoload' => [
        'psr-4' => ['Causal\\SimpleApi\\' => 'Classes']
    ],
];
