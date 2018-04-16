# Service Translate - Client

[![GitHub release](https://img.shields.io/github/release/flash-global/translate-client.svg?style=for-the-badge)](README.md) 

## Table of contents
- [Purpose](#purpose)
- [Requirements](#requirements)
    - [Runtime](#runtime)
- [Step by step installation](#step-by-step-installation)
    - [Initialization](#initialization)
    - [Settings](#settings)
    - [Known issues](#known-issues)
- [Contribution](#contribution)
- [Link to documentation](#link-to-documentation)
    - [Examples](#examples)

## Purpose
This client permit to use the `Translate Api`. Thanks to it, you could request the API to :
* Fetch data
* Create data
* Update data
* Delete data

easily

## Requirements 

### Runtime
- PHP 5.5

## Step by step Installation
> for all purposes (devlopment, contribution and production)

### Initialization
- Cloning repository 
```git clone https://github.com/flash-global/translate-client.git```
- Run Composer depedencies installation
```composer install```

### Settings

Don't forget to set the right `baseUrl` :

```php
<?php 
$translate = new Translate([Translate::OPTION_BASEURL => 'http://translate.dev']);
$translate->setTransport(new BasicTransport());
```

#### Beanstalkd configuration

Running Beanstalkd is very simple. However, you must pay attention to the `z` option which set the maximum job (or message) size in bytes. So, if you want send multiple translations to Translate API, you should allow enough bytes according to the length of your translations.


#### Run `api-client-worker.php`

You could see below an example of running `api-client-worker.php`:

> php /path/to/translate-client/vendor/bin/api-client-worker.php --host 127.0.0.1 --port 11300 --delay 3


| Options | Shortcut | Description                                   | Default     |
|---------|----------|-----------------------------------------------|-------------|
| host    | `-h`     | The host of Beanstalkd instance               | `localhost` |
| port    | `-p`     | The port which Beanstalkd instance listening  | `11300`     |
| delay   | `-d`     | The delay between two treatment of the worker | 3 seconds   |
| verbose | `-v`     | Print verbose information                     | -           |


You can control the api-client-worker.php process by using Supervisor.

#### Configuration

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


### Known issues
No known issue at this time.

## Contribution
As FEI Service, designed and made by OpCoding. The contribution workflow will involve both technical teams. Feel free to contribute, to improve features and apply patches, but keep in mind to carefully deal with pull request. Merging must be the product of complete discussions between Flash and OpCoding teams :) 

## Link to documentation 

### Examples
You can test this client easily thanks to the folder [examples](examples)

Here, an example on how to use example : `php /my/translate-client/folder/examples/translate.php` 

#### Basic usage

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

| Method        | Parameters                                                       | Return                              |
|---------------|------------------------------------------------------------------|-------------------------------------|
| fetchOne      | `int $id`                                                        | `I18nString`                        |
| fetchAll      |                                                                  |                                     |
| find          | `string $key, string $lang = '', string $domain = ''`            | `ArrayCollection` or `I18nString`   |
| search        | `Pattern $pattern`                                               | `array`                             |
| store         | `I18nString $string` or `array $string`                          | `array`                             |
| update        | `I18nString $string` or `array $string`                          | `array`                             |
| delete        | `int` or `I18nString` or `Pattern` or `string` `$parameter`                       | `bool`                              |
| subscribe     | `string $server = null, array $namespaces, $encoding = 'UTF-8'`  | `bool`                              |
| unsubscribe   | `string $server = null, array $namespaces`                       | `bool`                              |
| handleRequest | `string $requestUri = null, string $requestMethod = null`        | `Translate`                         |
| setClient     | `Translate $client`                                              | `Translate`                         |
| setLang       | `string $lang`                                                   | `Translate`                         |
| setDomain     | `string $domain`                                                 | `Translate`                         |
| setLogger     | `Logger $logger`                                                 | `Translate`                         |












