<?php
require __DIR__ . '/../vendor/autoload.php';

use Fei\Service\Translate\Client\Translate;
use Fei\ApiClient\Transport\BasicTransport;

try {
    $translate = new Translate([
        Translate::OPTION_BASEURL => 'http://translate.dev',
        Translate::OPTION_HEADER_AUTHORIZATION => 'key'
    ]);
    $translate->setTransport(new BasicTransport());
    $translate->fetchAll();
} catch (\Exception $e) {
    echo $e->getMessage() . PHP_EOL;
    $previous = $e->getPrevious();
    if ($previous instanceof Guzzle\Http\Exception\ServerErrorResponseException) {
        var_dump($previous->getRequest());
        var_dump($previous->getResponse()->getBody(true));
    }
}
