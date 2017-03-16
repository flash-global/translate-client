<?php
use Fei\Service\Translate\Client\Translate;

require_once '../vendor/autoload.php';

$translate = new Translate();
$translate->handleRequest($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);