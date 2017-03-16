<?php
use Fei\Service\Translate\Client\Translate;
require_once dirname(__DIR__) . '/vendor/autoload.php';

$client = (new Translate())->setLang('fr_FR')->setDomain('/A');
Translate::setClient($client);

echo \Fei\Service\Translate\Client\_('HELLO_WORLD');
