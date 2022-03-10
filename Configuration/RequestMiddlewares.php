<?php

return [
    'frontend' => [
        'causal/simpleapi/handler' => [
            'target' => \Causal\SimpleApi\Middleware\ApiMiddleware::class,
            'before' => [
                'typo3/cms-frontend/eid',
            ],
        ],
    ],
];
