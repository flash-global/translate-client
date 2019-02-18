<?php

return [
    'lock_file' => dirname(__DIR__) . '/.translations.lock',
    'data_path' => dirname(__DIR__) . '/data',
    'translations_path' => dirname(__DIR__) . '/translations',
    'localTranslationsFile' => dirname(__DIR__) . '/localTranslations',
    'hostHeader' => 'testHeader',
    'skipSubscription' => false,
    'servers' => [
        'http://10.5.0.1:8010' => [
        ]
    ],
    'url' => 'http://10.6.0.1:8040/examples/handleRequest.php/update',
    'sanitizedKeys' => false
];
