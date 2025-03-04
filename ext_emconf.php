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
    'author_company' => 'Causal Sàrl',
    'state' => 'stable',
    'version' => '2.0.0-dev',
    'constraints' => [
        'depends' => [
            'php' => '8.2.0-8.3.99',
            'typo3' => '11.5.41-12.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
    'autoload' => [
        'psr-4' => ['Causal\\SimpleApi\\' => 'Classes']
    ],
];
