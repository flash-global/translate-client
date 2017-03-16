#Translate Client

You can use this client to consume the Translate service.

With this client you can use two kind of transports to send the requests :

* Asynchronous transport implemented by `BeanstalkProxyTransport`
* Synchronous transport implemented by `BasicTransport`

`BeanstalkProxyTransport` delegates the API consumption to workers by sending translate entities to a Beanstalkd queue.

`BasicTransport` uses the classic HTTP layer to send translations synchronously.

You can find examples of how to use translate client methods in the `examples` folder.

# Installation

Translate client needs at least PHP 5.5 to work properly.

Add this requirement to your `composer.json`: `"fei/translate-client": : "^1.0"`

Or execute `composer.phar require fei/translate-client` in your terminal.

If you want use the asynchronous functionality of the Translate client (and we know you want), you need an instance of [Beanstalkd](http://kr.github.io/beanstalkd/) which running properly and an instance of api-client-worker.php which will consume the Beanstalk's pipe and forward messages payload to the Translate API:

> Translate Client -> Beanstalkd -> api-client-worker.php -> Translate API server

### Beanstalkd configuration

Running Beanstalkd is very simple. However, you must pay attention to the `z` option which set the maximum job (or message) size in bytes. So, if you want send multiple translations to Translate API, you should allow enough bytes according to the length of your translations.


### Run `api-client-worker.php`

You could see below an example of running `api-client-worker.php`:

> php /path/to/translate-client/vendor/bin/api-client-worker.php --host 127.0.0.1 --port 11300 --delay 3


| Options | Shortcut | Description                                   | Default     |
|---------|----------|-----------------------------------------------|-------------|
| host    | `-h`     | The host of Beanstalkd instance               | `localhost` |
| port    | `-p`     | The port which Beanstalkd instance listening  | `11300`     |
| delay   | `-d`     | The delay between two treatment of the worker | 3 seconds   |
| verbose | `-v`     | Print verbose information                     | -           |


You can control the api-client-worker.php process by using Supervisor.

# Entities and classes

### I18nString entity

In addition to traditional `id` and `createdAt` fields, I18nString entity has **four** important properties:

| Properties    | Type              |
|---------------|-------------------|
| id            | `integer`         |
| createdAt     | `datetime`        |
| lang             | `string`          |
| key           | `string`          |
| namespace     | `string`         |
| content       | `string`         |

* `lang` is a string indicating the language of the translation. It can be formatted either with two chars or with 5. For example you could have `fr` or `fr_FR`
* `key` is a string representing the key used to refer to this translation
* `namespace` is a string representing the namespace of the translation. For example, you could have `/project/pricer/invoices`
* `content` is a string representing the content of your translation

# Configuration

In order to make the client working properly, you need to configure a couple of paramaters :.

The configuration takes place in the `config/config.php` file. Here is a complete example of the configurations :

```php
<?php
return [
    'lock_file' => dirname(__DIR__) . '/.translations.lock',
    'data_path' => dirname(__DIR__) . '/data',
    'translations_path' => dirname(__DIR__) . '/translations',
    'servers' => [
        'http://10.5.0.1:8010' => [
            'namespaces' => ['/a/b/c/d']
        ]
    ],
    'url' => 'http://10.6.0.1:8040/examples/handleRequest.php/update'
];
```
* `lock_file`: this configuration is used to locate the `lock` used to determine if you have already subscribed to an API server
* `data_path `: this configuration is used to set one directory that will be used to store temporary data when updating the translation cache of your client
* `translations_path`: this configuration is used to set one directory that will be used to store your translation cache files
* `servers`: this is an array defining all the servers you want to subscribe when calling the `Translate::subscribe` method without any `$server` in the parameter of the method.
* `url`: this is the url used to listen the requests coming from the Translate API server when sending new translation cache files. Do not forget this parameter otherwise you will not receive any translations

# Basic usage

In order to consume `Translate` methods, you have to define a new `Translate` instance and set the right transport (Asynchronously or Synchronously).


```php
<?php

use Fei\Service\Translate\Client\ Translate;
use Fei\ApiClient\Transport\BasicTransport;
use Fei\ApiClient\Transport\BeanstalkProxyTransport;
use Pheanstalk\Pheanstalk;

$translate = new Translate([Translate::OPTION_BASEURL => 'https://translate.api']); // Put your translate API base URL here
$translate->setTransport(new BasicTransport());

$proxy = new BeanstalkProxyTransport();
$proxy->setPheanstalk(new Pheanstalk('127.0.0.1'));

$translate->setAsyncTransport($proxy);

// Use the translate client methods...
```
Translate client instance will first attempt to transfer the messages with Beanstalkd, if the process fail then the client will try to send I18nString payload directly to the right API endpoint.

There are several methods in Translate class, all listed in the following table:

| Method         | Parameters                                                       | Return                              |
|---------------|------------------------------------------------------------------|-------------------------------------|
| fetchOne          | `int $id`                                                        | `I18nString`                        |
| find           | `string $key, string $lang = '', string $domain = ''`            | `ArrayCollection` or `I18nString`   |
| search           | `Pattern $pattern`                                               | `array`                             |
| store           | `I18nString $string` or `array $string`                          | `array`                             |
| update           | `I18nString $string` or `array $string`                          | `array`                             |
| delete           | `int|I18nString|Pattern|string $parameter`                       | `bool`                              |
| subscribe     | `string $server = null, array $namespaces, $encoding = 'UTF-8'`  | `bool`                              |
| unsubscribe   | `string $server = null, array $namespaces`                       | `bool`                              |
| handleRequest | `string $requestUri = null, string $requestMethod = null`        | `Translate`                         |
| setClient     | `Translate $client`                                              | `Translate`                         |
| setLang       | `string $lang`                                                   | `Translate`                         |
| setDomain     | `string $domain`                                                 | `Translate`                         |
| setLogger     | `Logger $logger`                                                 | `Translate`                         |


### Client option

Only one option is available which can be passed either by the constructor or by calling the `setOptions` method `Translate::setOptions(array $options)`:

| Option         | Description                                    | Type   | Possible Values                                | Default |
|----------------|------------------------------------------------|--------|------------------------------------------------|---------|
| OPTION_BASEURL | This is the server to which send the requests. | string | Any URL, including protocol but excluding path | -       |


**Note**: All the examples below are also available in the examples directory.

## Subscribe

You can subscribe to a new API server from the client by using the `Translate::subscribe` method.

* `string $server`: string representing the API server. It can be null so the server will be taken from the config file.
* `array $namespaces`: array containing all the namespaces we want to subscribe. Let it empty to subscribe to all available namespaces
* `string $encoding`: string representing the string encoding wanted for the translations. By default, you will get UTF-8 encoded translations.

Here is an example on how to use this method:

```php
<?php
use Fei\Service\Logger\Client\Logger;
use Fei\Service\Translate\Client\Translate;
use Fei\ApiClient\Transport\BasicTransport;

$logger = new Logger([Logger::OPTION_BASEURL => 'http://logger.dev']);
$logger->setTransport(new BasicTransport());

$translate = new Translate([Translate::OPTION_BASEURL => 'http://translate.dev']);
$translate->setLogger($logger);
$translate->setTransport(new BasicTransport());

$translate->subscribe(null, []);
```

Keep in mind that once you have subscribed to a server, a lock file is added so you cannot subscribe to another server.

When you subscribe to an API server, it will send you the available translations when they are generated. It can take a few seconds.

## Unsubscribe

Once you have subscribed to an API server and don't want the translations from this server anymore, you can cancel you subscription by using the `Translate::unsubscribe` method.

* `string $server`: string representing the API server. It can be null so the server will be taken from the config file.
* `array $namespaces`: array containing all the namespaces we want to cancel the subscription. Let it empty to completely unsubscribe from this server.

Here is an example on how to use this method:

```php
<?php
use Fei\Service\Logger\Client\Logger;
use Fei\Service\Translate\Client\Translate;
use Fei\ApiClient\Transport\BasicTransport;

$logger = new Logger([Logger::OPTION_BASEURL => 'http://logger.dev']);
$logger->setTransport(new BasicTransport());

$translate = new Translate([Translate::OPTION_BASEURL => 'http://translate.dev']);
$translate->setTransport(new BasicTransport());
$translate->setLogger($logger);

$translate->unsubscribe(null, ['/']);
```
## fetchOne

You can get one I18nString representing a translation by using the `Translate::fetchOne` method with this parameter:

* `int $id`: integer representing the entity unique `id` parameter.

Here is an example on how to use it:

```php
<?php
use Fei\Service\Translate\Client\Translate;
use Fei\ApiClient\Transport\BasicTransport;
use Fei\Service\Translate\Entity\I18nString;

$translate = new Translate([Translate::OPTION_BASEURL => 'http://translate.dev']);
$translate->setTransport(new BasicTransport());

$i18nString = $translate->fetchOne(1);

if ($i18nString instanceof I18nString) {
    echo $i18nString;
}
```

## find

Maybe you will want to retrieve multiple translations according to a `key`, `namespace` or `language`. You can easily do this with the `Translate::find` method.

* `string $key`: string representing the `key` you want to retrieve ;
* `string $lang `: string representing the `lang` you want to filter on ;
* `string $domain`: string representing the `domain` you want to filter on.

Here is an example on how to use it:

```php
<?php
use Fei\Service\Translate\Client\Translate;
use Fei\ApiClient\Transport\BasicTransport;
use Fei\Service\Translate\Entity\I18nString;
use Fei\Service\Translate\Client\Utils\ArrayCollection;

$translate = new Translate([Translate::OPTION_BASEURL => 'http://translate.dev']);
$translate->setTransport(new BasicTransport());

$string = $translate->find($k);

if ($string instance of ArrayCollection) {
	echo $string->get(0);
}
```

## search

You can also search into all the entities by using the `Translate::search` method.

* `Pattern $pattern `: Pattern representing the criteria of your search.

Here is an example on how to use it:

```php
<?php
use Fei\Service\Translate\Client\Translate;
use Fei\ApiClient\Transport\BasicTransport;
use Fei\Service\Translate\Client\Utils\Pattern;
use Fei\Service\Translate\Entity\I18nString;

$translate = new Translate([Translate::OPTION_BASEURL => 'http://translate.dev']);
$translate->setTransport(new BasicTransport());

$strings = $translate->search(Pattern::begins('Hello'));

foreach ($strings as $string) {
    echo $string->getKey() . ' - ' . $string . '<br/>';
}
```

## store

You can create new translations using the client `Translate::store` method.

* `I18nString $string or array $string`: `$string` has to be either a I18nString instance or a array of I18nString instance

Here is an example on how to use it:

```php
<?php
use Fei\Service\Translate\Client\Translate;
use Fei\ApiClient\Transport\BasicTransport;
use Fei\Service\Translate\Entity\I18nString;

$translate = new Translate([Translate::OPTION_BASEURL => 'http://translate.dev']);
$translate->setTransport(new BasicTransport());

$values = [
    (new I18nString())
        ->setContent('Hello World')
        ->setKey('HELLO_WORLD')
        ->setLang('en_US')
        ->setNamespace('/')
];
$ids = $translate->store($values);
```

## update

You can update existing translations using the client `Translate::update` method.

* `I18nString $string or array $string`: `$string` has to be either a I18nString instance or a array of I18nString instance

Here is an example on how to use it:

```php
<?php
use Fei\Service\Translate\Client\Translate;
use Fei\ApiClient\Transport\BasicTransport;
use Fei\Service\Translate\Entity\I18nString;

$translate = new Translate([Translate::OPTION_BASEURL => 'http://translate.dev']);
$translate->setTransport(new BasicTransport());

$strings = $translate->find('KEY');
$strings->get(0)->setContent('Content updated');

$ids = $translate->update($strings->toArray());
```

## delete

You can delete existing translations using the client `Translate::delete` method.

* `int|I18nString|Pattern|string $parameter`: `$parameter` has to be of one of the specified types.

If several translations are found according to your `$parameter`, they will all be deleted.

Here is an example on how to use it:

```php
<?php
use Fei\Service\Translate\Client\Translate;
use Fei\ApiClient\Transport\BasicTransport;
use Fei\Service\Translate\Entity\I18nString;

$translate = new Translate([Translate::OPTION_BASEURL => 'http://translate.dev']);
$translate->setTransport(new BasicTransport());

$translate->delete(Pattern::begins('Hello'));
$translate->delete('MY_KEY');
```


## handleRequest

This method has to be used in your frontal controller of your client application. It is used to listen to requests send by the API server when sending translations cache to your client according to the `url` you have set in the config file in your client.

* `string $requestUri = null`: `$requestUri ` is the request URI that will match with what you defined in your config
* `string $requestMethod = null`: the method to listen (POST ; GET etc.)


Here is an example on how to use it:

```php
<?php
use Fei\Service\Translate\Client\Translate;

$translate = new Translate();
$translate->handleRequest($_SERVER['REQUEST_URI'], $_SERVER['REQUEST_METHOD']);
```

## setClient

This method is used to set the Translate client before asking for translations in your apps.

* `Translate $client` is the client to set

Here is an example on how to use it:

```php
<?php
use Fei\Service\Translate\Client\Translate;

$client = (new Translate())
				->setLang('fr_FR')
				->setDomain('/A');
				
Translate::setClient($client);
```

## setLang

This method is used to set the default language used when you asking translations with the `_` method.

* `string $lang` the language in 2 or 5 chars (`fr` or `fr_FR` for example)

Here is an example on how to use it:

```php
<?php
use Fei\Service\Translate\Client\Translate;

$client = (new Translate())
				->setLang('fr_FR');
```

## setDomain

This method is used to set the default domain used when you asking translations with the `_` method.

* `string $domain ` the domain to use by default

Here is an example on how to use it:

```php
<?php
use Fei\Service\Translate\Client\Translate;

$client = (new Translate())
				->setDomain('/pricer/invoices');
```

## setLogger

You can use the `Logger` client to add notifications when a translation is missing. To do this, you have to set the logger client to the translate client before asking to translate anything with the `_` method.

* `Logger $logger ` the Logger client instance

Here is an example on how to use it:

```php
<?php
use Fei\Service\Logger\Client\Logger;
use Fei\Service\Translate\Client\Translate;
use Fei\ApiClient\Transport\BasicTransport;

$logger = new Logger([Logger::OPTION_BASEURL => 'http://logger.dev']);
$logger->setTransport(new BasicTransport());

$client = (new Translate())
				->setLan('fr_FR')
				->setDomain('/pricer/invoices')
				->setLogger($logger);

$translate->setTransport(new BasicTransport());
```
# Translate inside your applications

We have made a simple `_` function that you can use everywhere in your applications that uses the Translate client.

Here are the parameters of this function :

* `string $key` : the key you want to get the translation
* `string $domain = null` in which namespace you would like to pick your translation
* `string $lang = null` in what language you want the translation

Note that the `$domain` and the `$lang` are two optional parameters. If you don't specified them, their default value will be taken according to you instance of the translate client (see examples below).

Here are two example on how to use this function :

```php
$client = (new Translate())->setLang('fr_FR')->setDomain('/A');
Translate::setClient($client);

echo \Fei\Service\Translate\Client\_('HELLO_WORLD');
```

In this example we are going to find the key `HELLO_WORLD` int the default `domain' (/A) for the default language (fr_FR). If the translation is not found, the key will be returned.


```php
$client = (new Translate())->setLang('fr_FR')->setDomain('/A');
Translate::setClient($client);

echo \Fei\Service\Translate\Client\_('HELLO_WORLD', '/b', 'en_GB');
```

In this example we are going to find the key `HELLO_WORLD` int the specified `domain' (/b) for the specified language (en_GB). If the translation is not found, the key will be returned.
