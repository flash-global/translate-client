<?php
require __DIR__ . '/../vendor/autoload.php';

use Fei\Service\Translate\Client\Translate;
use Fei\ApiClient\Transport\BasicTransport;
use Fei\Service\Translate\Client\Utils\Pattern;
use Fei\Service\Translate\Entity\I18nString;

try {
    $translate = new Translate([Translate::OPTION_BASEURL => 'http://translate.dev']);

    $translate->setTransport(new BasicTransport());
    $key = 'TRANSLATION_' . time();
    $values = [
        (new I18nString())
            ->setContent('Hello World')
            ->setKey('HELLO_WORLD_' . time())
            ->setLang('en_US')
            ->setNamespace('/'),

        (new I18nString())
            ->setContent('Traduction')
            ->setKey($key)
            ->setLang('fr_FR')
            ->setNamespace('/domain')
    ];
    $ids = $translate->store($values);

    $translate->delete(Pattern::begins('Hello'));
    $translate->delete($key);

} catch (\Exception $e) {
    echo $e->getMessage() . PHP_EOL;
    $previous = $e->getPrevious();
    if ($previous instanceof Guzzle\Http\Exception\ServerErrorResponseException) {
        var_dump($previous->getRequest());
        var_dump($previous->getResponse()->getBody(true));
    }
}
