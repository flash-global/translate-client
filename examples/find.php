<?php
require __DIR__ . '/../vendor/autoload.php';

use Fei\Service\Translate\Client\Translate;
use Fei\ApiClient\Transport\BasicTransport;
use Fei\Service\Translate\Entity\I18nString;

try {
    $translate = new Translate([
        Translate::OPTION_BASEURL => 'http://translate.dev',
        Translate::OPTION_HEADER_AUTHORIZATION => 'key'
    ]);

    $translate->setTransport(new BasicTransport());

    $k = 'HELLO_WORLD_' . time();
    $values = [
        (new I18nString())
            ->setContent('Hello World')
            ->setKey($k)
            ->setLang('en_US')
            ->setNamespace('/'),
    ];
    $ids = $translate->store($values);

    $string = $translate->find('lkjh', 'kjlh', 'lkjh');
    
    echo $string;
} catch (\Exception $e) {
    echo $e->getMessage() . PHP_EOL;
    $previous = $e->getPrevious();
    if ($previous instanceof Guzzle\Http\Exception\ServerErrorResponseException) {
        var_dump($previous->getRequest());
        var_dump($previous->getResponse()->getBody(true));
    }
}
