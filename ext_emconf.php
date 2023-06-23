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
    'author' => 'Xavier Perseguers',
    'author_email' => 'xavier@causal.ch',
    'doNotLoadInFE' => 1,
    'state' => 'stable',
    'uploadfolder' => 0,
    'createDirs' => '',
    'clearCacheOnLoad' => 0,
    'author_company' => 'Causal Sàrl',
    'version' => '1.1.0-dev',
    'constraints' => [
        'depends' => [
            'php' => '7.4.0-8.2.99',
            'typo3' => '9.5.0-11.5.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
    'autoload' => [
        'psr-4' => ['Causal\\SimpleApi\\' => 'Classes']
    ],
];
