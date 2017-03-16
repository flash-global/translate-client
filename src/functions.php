<?php
namespace Fei\Service\Translate\Client;

function _($key, $domain = null, $lang = null)
{
    return Translate::getClient()->translate($key, $domain, $lang);
}
