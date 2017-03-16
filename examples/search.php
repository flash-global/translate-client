<?php
require __DIR__ . '/../vendor/autoload.php';

use Fei\Service\Translate\Client\Translate;
use Fei\ApiClient\Transport\BasicTransport;
use Fei\Service\Translate\Client\Utils\Pattern;
use Fei\Service\Translate\Entity\I18nString;

try {
    $translate = new Translate([Translate::OPTION_BASEURL => 'http://translate.dev']);

    $translate->setTransport(new BasicTransport());
    $values = [
        (new I18nString())
            ->setContent('Hello World')
            ->setKey('HELLO_WORLD_' . time())
            ->setLang('en_US')
            ->setNamespace('/'),

        (new I18nString())
            ->setContent('Traduction')
            ->setKey('TRANSLATION' . time())
            ->setLang('fr_FR')
            ->setNamespace('/domain')
    ];
    $ids = $translate->store($values);

    $strings = $translate->search(Pattern::begins('Hello'));

    foreach ($strings as $string) {
        echo $string->getKey() . ' - ' . $string . '<br/>';
    }
} catch (\Exception $e) {
    echo $e->getMessage() . PHP_EOL;
    $previous = $e->getPrevious();
    if ($previous instanceof Guzzle\Http\Exception\ServerErrorResponseException) {
        var_dump($previous->getRequest());
        var_dump($previous->getResponse()->getBody(true));
    }
}
