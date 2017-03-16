<?php
require __DIR__ . '/../vendor/autoload.php';

use Fei\Service\Translate\Client\Translate;
use Fei\ApiClient\Transport\BasicTransport;
use Fei\Service\Translate\Entity\I18nString;

try {
    $translate = new Translate([Translate::OPTION_BASEURL => 'http://translate.dev']);

    $k = 'HELLO_WORLD' . time();

    $translate->setTransport(new BasicTransport());
    $values = [
        (new I18nString())
            ->setContent('Hello World')
            ->setKey($k)
            ->setLang('en_US')
            ->setNamespace('/')
    ];
    $ids = $translate->store($values);

    $strings = $translate->find($k);
    $strings->get(0)->setContent('Content updated');

    $ids = $translate->update($strings->toArray());
} catch (\Exception $e) {
    echo $e->getMessage() . PHP_EOL;
    $previous = $e->getPrevious();
    if ($previous instanceof Guzzle\Http\Exception\ServerErrorResponseException) {
        var_dump($previous->getRequest());
        var_dump($previous->getResponse()->getBody(true));
    }
}
