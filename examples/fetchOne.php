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

    $values = [
        (new I18nString())
            ->setContent('Hello World')
            ->setKey('HELLO_WORLD_' . time())
            ->setLang('en_US')
            ->setNamespace('/'),
    ];
    $ids = $translate->store($values);

    $i18nString = $translate->fetchOne(reset($ids));

    if ($i18nString instanceof I18nString) {
        echo $i18nString;
    }
} catch (\Exception $e) {
    echo $e->getMessage() . PHP_EOL;
    $previous = $e->getPrevious();
    if ($previous instanceof Guzzle\Http\Exception\ServerErrorResponseException) {
        var_dump($previous->getRequest());
        var_dump($previous->getResponse()->getBody(true));
    }
}
