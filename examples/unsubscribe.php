<?php
require __DIR__ . '/../vendor/autoload.php';

use Fei\Service\Logger\Client\Logger;
use Fei\Service\Translate\Client\Translate;
use Fei\ApiClient\Transport\BasicTransport;

try {
    $logger = new Logger([
        Logger::OPTION_BASEURL => 'http://logger.dev',
        Translate::OPTION_HEADER_AUTHORIZATION => 'key'
    ]);
    $logger->setTransport(new BasicTransport());

    $translate = new Translate([Translate::OPTION_BASEURL => 'http://translate.dev']);
    $translate->setTransport(new BasicTransport());
    $translate->setLogger($logger);

    $translate->unsubscribe(null, ['/']);
} catch (\Exception $e) {
    echo $e->getMessage() . PHP_EOL;
    $previous = $e->getPrevious();
    if ($previous instanceof Guzzle\Http\Exception\ServerErrorResponseException) {
        var_dump($previous->getRequest());
        var_dump($previous->getResponse()->getBody(true));
    }
}
