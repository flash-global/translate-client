<?php

namespace Tests\Fei\Service\Translate\Client;

use Codeception\Test\Unit;
use Codeception\Util\Stub;
use FastRoute\Dispatcher;
use Fei\ApiClient\RequestDescriptor;
use Fei\ApiClient\ResponseDescriptor;
use Fei\ApiClient\Transport\SyncTransportInterface;
use Fei\Entity\Exception;
use Fei\Service\Logger\Client\Logger;
use Fei\Service\Translate\Client\Exception\TranslateException;
use Fei\Service\Translate\Client\Exception\ValidationException;
use Fei\Service\Translate\Client\Translate;
use Fei\Service\Translate\Client\Utils\ArrayCollection;
use Fei\Service\Translate\Client\Utils\Pattern;
use Fei\Service\Translate\Entity\I18nString;

/**
 * Class TranslateTest
 *
 * @package Tests\Fei\Service\Translate\Client
 */
class TranslateTest extends Unit
{
    public function testConstructorWhenConfigFileNotFound()
    {
        $this->expectException(TranslateException::class);

        Stub::construct(Translate::class, [], [
            'getConfigFilePath' => 'fake-path'
        ]);
    }

    public function testBuildDesfaultSubscriptionWhenNoServerInTheConfig()
    {
        $translate = Stub::make(Translate::class, [
            'getConfig' => Stub::once(function () {
                return [];
            }),
            'getBaseUrl' => Stub::once(function () {
                return 'base-url';
            }),
            'subscribe' => Stub::once(function () {
                return true;
            })
        ]);

        $results = $this->invokeNonPublicMethod($translate, 'buildDefaultSubscription');

        $this->assertEquals(true, $results);
    }

    public function testBuildDesfaultSubscriptionWhenServerIsInTheConfig()
    {
        $translate = Stub::make(Translate::class, [
            'getConfig' => Stub::once(function () {
                return [
                    'servers' => [
                        'fake-url' => [],
                        'fake-url-2' => []
                    ]
                ];
            }),
            'getBaseUrl' => Stub::once(function () {
                return 'base-url';
            }),
            'subscribe' => Stub::exactly(2, function () {
                return true;
            })
        ]);

        $results = $this->invokeNonPublicMethod($translate, 'buildDefaultSubscription');

        $this->assertEquals(true, $results);
    }

    public function testFetchOneNoTransportSet()
    {
        $translate = new Translate([Translate::OPTION_BASEURL => 'http://url']);

        $this->expectException(TranslateException::class);
        $this->expectExceptionMessage('Transport has to be set');

        $translate->fetchOne(1);
    }

    public function testSubscribeWhenServerIsNull()
    {
        $translate = Stub::construct(Translate::class, [], [
            'getConfig' => [
              'lock_file' => 'lock-fake'
            ],
            'importConfig' => Stub::once(),
            'checkWritableDirectories' => Stub::once(),
            'buildDefaultSubscription' => Stub::once(function () {
                return true;
            })
        ]);

        $result = $translate->subscribe();

        $this->assertTrue($result);
    }

    public function testSubscribeWhenLockFileIsPresent()
    {
        $translate = Stub::construct(Translate::class, [], [
            'getConfig' => [
              'lock_file' => dirname(__DIR__) . '/data/lock_file'
            ],
            'importConfig' => Stub::once(),
            'checkWritableDirectories' => Stub::once(),
        ]);

        $result = $translate->subscribe();

        $this->assertTrue($result);
    }

    public function testCreateLockFile()
    {
        $translate = new Translate();

        $file = dirname(__DIR__) . '/data/tmp_lock';
        $this->invokeNonPublicMethod($translate, 'createLockFile', [$file]);

        $this->assertFileExists($file);
        $this->assertEquals(file_get_contents($file), time());
        unlink($file);
    }

    public function testSubscribeWhenServerIsSetButNoUrlSetInTheConfig()
    {
        $translate = Stub::construct(Translate::class, [], [
            'setBaseUrl' => Stub::once(),
            'checkTransport' => Stub::once(),
            'importConfig' => Stub::once(),
            'checkWritableDirectories' => Stub::once(),
            'getConfig' => Stub::once(function () {
                return ['lock_file' => 'fake-lock'];
            }),
        ]);

        $this->expectException(TranslateException::class);
        $translate->subscribe('fake-server');
    }

    public function testSubscribeWhenServerAndUrlAreSet()
    {
        $responseMock = $this->getMockBuilder(ResponseDescriptor::class)->setMethods(['getBody'])->getMock();
        $responseMock->expects($this->once())->method('getBody')->willReturn('true');

        $translate = Stub::make(Translate::class, [
            'setBaseUrl' => Stub::once(),
            'checkTransport' => Stub::once(),
            'createLockFile' => Stub::once(),
            'checkWritableDirectories' => Stub::once(),
            'getConfig' => Stub::once(function () {
                return [
                    'url' => 'fake-url',
                    'lock_file' => 'fake-lock_file'
                ];
            }),
            'send' => Stub::once(function () use ($responseMock) {
                return $responseMock;
            }),
        ]);

        $results = $translate->subscribe('fake-server');

        $this->assertTrue($results);
    }

    public function testUnsubscribeWhenServerIsNotNull()
    {
        $responseMock = $this->getMockBuilder(ResponseDescriptor::class)->setMethods(['getBody'])->getMock();
        $responseMock->expects($this->once())->method('getBody')->willReturn('true');

        $file = dirname(__DIR__) . '/data/fake-lock';
        $translate = Stub::make(Translate::class, [
            'getConfig' => Stub::once(function () use ($file) {
                return [
                    'url' => 'fake-url',
                    'lock_file' => $file
                ];
            }),
            'setBaseUrl' => Stub::once(),
            'checkWritableDirectories' => Stub::once(),
            'checkTransport' => Stub::once(),
            'send' => Stub::once(function () use ($responseMock) {
                return $responseMock;
            })
        ]);

        file_put_contents($file, time());

        $results = $translate->unsubscribe('server');

        $this->assertFileNotExists($file);
        $this->assertTrue($results);
    }

    public function testHandleRequestWhenRouteIsFound()
    {
        $cb = function () {
            return 'fake-cb';
        };

        $dispatcherMock = $this->getMockBuilder(Dispatcher::class)->setMethods(['dispatch'])->getMock();
        $dispatcherMock->expects($this->once())->method('dispatch')->willReturn([Dispatcher::FOUND, $cb]);

        $translate = Stub::make(Translate::class, [
           'getDispatcher' => $dispatcherMock
        ]);

        $this->assertEquals($translate, $translate->handleRequest('/test?param=value'));
    }

    public function testUnsubscribeWhenServerIsNullAndNoServerIsNSetInTheConfig()
    {
        $responseMock = $this->getMockBuilder(ResponseDescriptor::class)->setMethods(['getBody'])->getMock();
        $responseMock->expects($this->once())->method('getBody')->willReturn('true');

        $translate = Stub::make(Translate::class, [
            'checkTransport' => Stub::once(),
            'checkWritableDirectories' => true,
            'getConfig' => Stub::atLeastOnce(function () {
                return [
                    'url' => 'fake-url',
                    'lock_file' => 'fake-lock_file'
                ];
            }),
            'getBaseUrl' => Stub::exactly(2, function () {
                return 'fake-url';
            }),
            'send' => Stub::once(function () use ($responseMock) {
                return $responseMock;
            })
        ]);

        $results = $translate->unsubscribe();

        $this->assertTrue($results);
    }

    public function testUnsubscribeWhenNoUrlIsConfiguredInTheConfig()
    {
        $translate = Stub::make(Translate::class, [
            'checkTransport' => Stub::once(),
            'checkWritableDirectories' => true,
            'getConfig' => Stub::atLeastOnce(function () {
                return [];
            }),
        ]);

        $this->expectException(TranslateException::class);
        $this->expectExceptionMessage('Call url not configured in the config file!');

        $translate->unsubscribe();
    }

    public function testFetchOne()
    {
        $translate = new Translate([Translate::OPTION_BASEURL => 'http://url']);

        $flag = null;

        $data = $this->getValidI18nString()->toArray();

        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->once())->method('send')->withConsecutive(
            [
                $this->callback(function (RequestDescriptor $requestDescriptor) use (&$request1) {
                    return $request1 = $requestDescriptor;
                })
            ]
        )->willReturnOnConsecutiveCalls(
            (new ResponseDescriptor())->setBody(json_encode([
                "data" => $data,
                "meta" => [
                    "entity" => "Fei\\Service\\Translate\\Entity\\I18nString"
                ]
            ]))
        );

        $translate->setTransport($transport);

        $result = $translate->fetchOne(1);

        $this->assertEquals($request1->getMethod(), 'GET');
        $this->assertEquals(
            $request1->getUrl(),
            'http://url/api/i18n-string?id=1'
        );
        $this->assertEquals(new I18nString($data), $result);
    }

    public function testStore()
    {
        $translate = new Translate([Translate::OPTION_BASEURL => 'http://url']);

        $request = new RequestDescriptor();
        $flag = null;

        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->once())->method('send')->willReturnCallback(
            function (RequestDescriptor $requestDescriptor, $mFlag) use (&$request, &$flag, $transport) {
                $request = $requestDescriptor;
                $flag = $mFlag;
                return (new ResponseDescriptor())->setBody(json_encode(true));
            }
        );

        $translate->setTransport($transport);

        $string = new I18nString();
        $string->setNamespace('/')
            ->setLang('fr')
            ->setKey('KEY')
            ->setContent('Content');
        
        $result = $translate->store($string);

        $this->assertEquals($request->getMethod(), 'POST');
        $this->assertEquals($request->getUrl(), 'http://url/api/i18n-string');
        $this->assertEquals(
            ['entities' => \json_encode([$string->toArray()])],
            $request->getBodyParams()
        );
        $this->assertTrue($result);
    }

    public function testStoreNoTransportSet()
    {
        $translate = new Translate([Translate::OPTION_BASEURL => 'http://url']);

        $this->expectException(TranslateException::class);
        $this->expectExceptionMessage('Transport has to be set');

        $translate->store(new I18nString());
    }

    public function testUpdateWithoutId()
    {
        $translate = new Translate([Translate::OPTION_BASEURL => 'http://url']);

        $transport = $this->createMock(SyncTransportInterface::class);
        $translate->setTransport($transport);

        $string = new I18nString();
        $string->setNamespace('/')
            ->setLang('fr')
            ->setKey('KEY')
            ->setContent('Content');

        $this->expectException(TranslateException::class);
        $translate->update($string);
    }

    public function testUpdateWhenStringIsNotAnI18nStringInstance()
    {
        $translate = new Translate([Translate::OPTION_BASEURL => 'http://url']);

        $transport = $this->createMock(SyncTransportInterface::class);
        $translate->setTransport($transport);

        $string = 'string';

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('You have to send an I18nString entity!');
        $translate->update($string);
    }

    public function testUpdate()
    {
        $translate = new Translate([Translate::OPTION_BASEURL => 'http://url']);

        $request = new RequestDescriptor();
        $flag = null;

        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->once())->method('send')->willReturnCallback(
            function (RequestDescriptor $requestDescriptor, $mFlag) use (&$request, &$flag, $transport) {
                $request = $requestDescriptor;
                $flag = $mFlag;
                return (new ResponseDescriptor())->setBody(json_encode(true));
            }
        );

        $translate->setTransport($transport);

        $string = new I18nString();
        $string->setNamespace('/')
            ->setId(1)
            ->setLang('fr')
            ->setKey('KEY')
            ->setContent('Content');

        $result = $translate->update($string);

        $this->assertEquals($request->getMethod(), 'PATCH');
        $this->assertEquals($request->getUrl(), 'http://url/api/i18n-string');
        $this->assertEquals(
            ['entities' => \json_encode([$string->toArray()])],
            $request->getBodyParams()
        );
        $this->assertTrue($result);
    }

    public function testUpdateNoTransportSet()
    {
        $translate = new Translate([Translate::OPTION_BASEURL => 'http://url']);

        $this->expectException(TranslateException::class);
        $this->expectExceptionMessage('Transport has to be set');

        $translate->update(new I18nString());
    }

    public function testDeleteNoTransportSet()
    {
        $translate = new Translate([Translate::OPTION_BASEURL => 'http://url']);

        $this->expectException(TranslateException::class);
        $this->expectExceptionMessage('Transport has to be set');

        $translate->delete(new I18nString());
    }

    public function testDeleteWhenGivingStringParameter()
    {
        $translate = new Translate([Translate::OPTION_BASEURL => 'http://url']);

        $request = new RequestDescriptor();
        $flag = null;

        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->once())->method('send')->willReturnCallback(
            function (RequestDescriptor $requestDescriptor, $mFlag) use (&$request, &$flag, $transport) {
                $request = $requestDescriptor;
                $flag = $mFlag;
                return (new ResponseDescriptor())->setBody(json_encode(true));
            }
        );

        $translate->setTransport($transport);

        $result = $translate->delete('MY_KEY');

        $this->assertEquals($request->getMethod(), 'DELETE');
        $this->assertEquals($request->getUrl(), 'http://url/api/i18n-string');
        $this->assertEquals(
            ['params' => \json_encode(['key' => 'MY_KEY'])],
            $request->getBodyParams()
        );
        $this->assertTrue($result);
    }

    public function testDeleteWhenGivingIntegerParameter()
    {
        $translate = new Translate([Translate::OPTION_BASEURL => 'http://url']);

        $request = new RequestDescriptor();
        $flag = null;

        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->once())->method('send')->willReturnCallback(
            function (RequestDescriptor $requestDescriptor, $mFlag) use (&$request, &$flag, $transport) {
                $request = $requestDescriptor;
                $flag = $mFlag;
                return (new ResponseDescriptor())->setBody(json_encode(true));
            }
        );

        $translate->setTransport($transport);

        $result = $translate->delete(1);

        $this->assertEquals($request->getMethod(), 'DELETE');
        $this->assertEquals($request->getUrl(), 'http://url/api/i18n-string');
        $this->assertEquals(
            ['params' => \json_encode(['id' => 1])],
            $request->getBodyParams()
        );
        $this->assertTrue($result);
    }

    public function testDeleteWhenGivingI18nStringParameter()
    {
        $translate = new Translate([Translate::OPTION_BASEURL => 'http://url']);

        $request = new RequestDescriptor();
        $flag = null;

        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->once())->method('send')->willReturnCallback(
            function (RequestDescriptor $requestDescriptor, $mFlag) use (&$request, &$flag, $transport) {
                $request = $requestDescriptor;
                $flag = $mFlag;
                return (new ResponseDescriptor())->setBody(json_encode(true));
            }
        );

        $translate->setTransport($transport);

        $result = $translate->delete($this->getValidI18nString()->setId(2));

        $this->assertEquals($request->getMethod(), 'DELETE');
        $this->assertEquals($request->getUrl(), 'http://url/api/i18n-string');
        $this->assertEquals(
            ['params' => \json_encode(['id' => 2])],
            $request->getBodyParams()
        );
        $this->assertTrue($result);
    }
    public function testDeleteWithInvalidParameter()
    {
        $translate = new Translate([Translate::OPTION_BASEURL => 'http://url']);

        $transport = $this->createMock(SyncTransportInterface::class);
        $translate->setTransport($transport);

        $this->expectException(TranslateException::class);
        $this->expectExceptionMessage('Bad request: the parameter hs to be a valid `key`, `id`, `entity` or `Pattern`');
        $this->expectExceptionCode(400);

        $translate->delete(new \stdClass());
    }

    public function testSearchNoTransportSet()
    {
        $translate = new Translate([Translate::OPTION_BASEURL => 'http://url']);

        $this->expectException(TranslateException::class);
        $this->expectExceptionMessage('Transport has to be set');

        $translate->search(Pattern::contains('fake-content'));
    }

    public function testSearch()
    {
        $translate = new Translate([Translate::OPTION_BASEURL => 'http://url']);

        $request = new RequestDescriptor();

        $data = $this->getValidI18nString()->toArray();
        $string = new I18nString($data);

        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->once())->method('send')->willReturnCallback(
            function (RequestDescriptor $requestDescriptor) use (&$request, $transport, $data) {
                $request = $requestDescriptor;
                return (new ResponseDescriptor())->setBody(json_encode([$data]));
            }
        );

        $translate->setTransport($transport);

        $result = $translate->search(Pattern::equals('Content'));

        $this->assertEquals($request->getMethod(), 'GET');
        $this->assertEquals($request->getUrl(), 'http://url/api/i18n-string?content=Content');

        $this->assertEquals($result, [$string]);
    }

    public function testFindWithoutTranportSet()
    {
        $translate = new Translate([Translate::OPTION_BASEURL => 'http://url']);

        $this->expectException(TranslateException::class);
        $this->expectExceptionMessage('Transport has to be set');

        $translate->find('KEY');
    }

    public function testFindWithOnlyTheKeyParameter()
    {
        $translate = new Translate([Translate::OPTION_BASEURL => 'http://url']);

        $request = new RequestDescriptor();
        $flag = null;

        $data = $this->getValidI18nString()->toArray();
        $string = new I18nString($data);

        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->once())->method('send')->willReturnCallback(
            function (RequestDescriptor $requestDescriptor) use (&$request, $data) {
                $request = $requestDescriptor;
                return (new ResponseDescriptor())->setBody(json_encode([$data]));
            }
        );

        $translate->setTransport($transport);

        $result = $translate->find('KEY');

        $this->assertEquals($request->getMethod(), 'GET');
        $this->assertEquals($request->getUrl(), 'http://url/api/i18n-string?key=KEY');

        $this->assertEquals($result, new ArrayCollection([$string]));
    }

    public function testFindWithOnlyTheKeyAndLangParameter()
    {
        $translate = new Translate([Translate::OPTION_BASEURL => 'http://url']);

        $request = new RequestDescriptor();
        $flag = null;

        $data = $this->getValidI18nString()->toArray();
        $string = new I18nString($data);

        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->once())->method('send')->willReturnCallback(
            function (RequestDescriptor $requestDescriptor) use (&$request, $data) {
                $request = $requestDescriptor;
                return (new ResponseDescriptor())->setBody(json_encode([$data]));
            }
        );

        $translate->setTransport($transport);

        $result = $translate->find('KEY', 'fr_FR');

        $this->assertEquals($request->getMethod(), 'GET');
        $this->assertEquals($request->getUrl(), 'http://url/api/i18n-string?key=KEY&lang=fr_FR');

        $this->assertEquals($result, new ArrayCollection([$string]));
    }

    public function testFindWithAllParametersSet()
    {
        $translate = new Translate([Translate::OPTION_BASEURL => 'http://url']);

        $request = new RequestDescriptor();
        $flag = null;

        $data = $this->getValidI18nString()->toArray();
        $string = new I18nString($data);

        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->once())->method('send')->willReturnCallback(
            function (RequestDescriptor $requestDescriptor) use (&$request, $data) {
                $request = $requestDescriptor;
                return (new ResponseDescriptor())->setBody(json_encode([$data]));
            }
        );

        $translate->setTransport($transport);

        $result = $translate->find('KEY', 'fr_FR', '/');

        $this->assertEquals($request->getMethod(), 'GET');
        $url = 'http://url/api/i18n-string?key=KEY&lang=fr_FR&namespace=' . urlencode('/');
        $this->assertEquals($request->getUrl(), $url);

        $this->assertEquals($result, $string);
    }

    public function testDeleteWhenGivingPatternParameter()
    {
        $translate = new Translate([Translate::OPTION_BASEURL => 'http://url']);

        $request = new RequestDescriptor();
        $flag = null;

        $transport = $this->createMock(SyncTransportInterface::class);
        $transport->expects($this->once())->method('send')->willReturnCallback(
            function (RequestDescriptor $requestDescriptor, $mFlag) use (&$request, &$flag, $transport) {
                $request = $requestDescriptor;
                $flag = $mFlag;
                return (new ResponseDescriptor())->setBody(json_encode(true));
            }
        );

        $translate->setTransport($transport);

        $result = $translate->delete(Pattern::equals('my-content'));

        $this->assertEquals($request->getMethod(), 'DELETE');
        $this->assertEquals($request->getUrl(), 'http://url/api/i18n-string');
        $this->assertEquals(
            ['params' => \json_encode(['pattern' => 'my-content'])],
            $request->getBodyParams()
        );
        $this->assertTrue($result);
    }

    public function testLangAccessors()
    {
        $client = new Translate();
        $client->setLang('fr_FR');

        $this->assertEquals('fr_FR', $client->getLang());
        $this->assertAttributeEquals($client->getLang(), 'lang', $client);
    }

    public function testDomainAccessors()
    {
        $client = new Translate();
        $client->setDomain('/a/b/c');

        $this->assertEquals('/a/b/c', $client->getDomain());
        $this->assertAttributeEquals($client->getDomain(), 'domain', $client);
    }

    public function testGetClientWhenItHasNotBeenSet()
    {
        $this->expectException(TranslateException::class);
        $this->expectExceptionMessage('Client has to be set before using it!');
        Translate::getClient();
    }

    public function testGetClientWhenItHasBeenSet()
    {
        $fakeClient = $this->getMockBuilder(Translate::class)->disableOriginalConstructor()->getMock();
        Translate::setClient($fakeClient);

        $result = Translate::getClient();

        $this->assertInstanceOf(Translate::class, $result);
        $this->assertEquals($fakeClient, $result);
    }

    public function testSetClientWithoutLogger()
    {
        $fakeClient = $this->getMockBuilder(Translate::class)->disableOriginalConstructor()->getMock();

        $set = Translate::setClient($fakeClient);
        $this->assertInstanceOf(Translate::class, $set);

        $this->assertEquals($fakeClient, Translate::getClient());
        $this->assertAttributeEquals(Translate::getClient(), 'client', Translate::class);
    }

    public function testSetLogger()
    {
        $fakeLogger = $this->getMockBuilder(Logger::class)->getMock();

        $translate = new Translate();
        $set = $translate->setLogger($fakeLogger);

        $this->assertEquals($translate, $set);
        $this->assertAttributeEquals($fakeLogger, 'logger', $translate);
    }

    public function testTranslateWhenGivenDomainIsNotValid()
    {
        $translate = Stub::make(Translate::class, [
            'isDomain' => false
        ]);

        $this->expectException(TranslateException::class);
        $this->expectExceptionMessage('This domain is not valid!');

        $translate->translate('KEY', 'a/b');
    }

    public function testTranslateWhenGivenLangIsNotValid()
    {
        $translate = Stub::make(Translate::class, [
            'isLang' => false,
            'isDomain' => true
        ]);

        $this->expectException(TranslateException::class);
        $this->expectExceptionMessage('This lang is not valid!');

        $translate->translate('KEY', 'a/b', 'fr_from_france');
    }

    public function testTranslateWhenTranslationFileIsMissing()
    {
        $translate = Stub::make(Translate::class, [
            'isDomain' => true,
            'isLang' => true,
            'getConfig' => [
                'translations_path' => 'fake-path'
            ],
        ]);

        $translated = $translate->translate('KEY');

        $this->assertEquals('KEY', $translated);
    }

    public function testTranslateWhenTranslationFileExistsAndKeyIsNotInItAndLoggerNotConfigured()
    {
        $translate = Stub::make(Translate::class, [
            'isDomain' => true,
            'isLang' => true,
            'getConfig' => [
                'translations_path' => dirname(__DIR__) . '/data/translate'
            ],
        ]);

        $translated = $translate->translate('KEY_FAKE', '/one/domain', 'fr_FR');

        $this->assertEquals('KEY_FAKE', $translated);
    }

    public function testTranslateWhenTranslationFileExistsAndKeyIsNotInItAndLoggerIsConfigured()
    {
        $fakeLogger = $this->getMockBuilder(Logger::class)->setMethods(['notify'])->getMock();
        $fakeLogger->expects($this->once())->method('notify')->willReturn(null);

        $translate = Stub::make(Translate::class, [
            'isDomain' => true,
            'isLang' => true,
            'getLogger' => $fakeLogger,
            'getConfig' => [
                'translations_path' => dirname(__DIR__) . '/data/translate'
            ],
        ]);

        $translated = $translate->translate('KEY_FAKE', '/one/domain', 'fr_FR');

        $this->assertEquals('KEY_FAKE', $translated);
    }

    public function testTranslateWhenTranslationFileExistsAndKeyIsPresnet()
    {
        $translate = Stub::make(Translate::class, [
            'isDomain' => true,
            'isLang' => true,
            'getConfig' => [
                'translations_path' => dirname(__DIR__) . '/data/translate'
            ],
        ]);

        $translated = $translate->translate('KEY', '/one/domain', 'fr_FR');

        $this->assertEquals('Value', $translated);
    }

    public function testDispatcherAccessors()
    {
        $dispatcherMock = $this->getMockBuilder(Dispatcher::class)->getMock();

        $translate = new Translate();
        $translate->setDispatcher($dispatcherMock);

        $this->assertEquals($dispatcherMock, $translate->getDispatcher());
        $this->assertAttributeEquals($translate->getDispatcher(), 'dispatcher', $translate);
    }

    public function testDomainHierarchy()
    {
        $translate = new Translate();

        $class = new \ReflectionClass('Fei\Service\Translate\Client\Translate');
        $method = $class->getMethod('domainHierarchy');
        $method->setAccessible(true);

        $this->assertEquals(
            ['/test/sub-test/sub-sub-test', '/test/sub-test', '/test', '/'],
            $method->invokeArgs($translate, ['/test/sub-test/sub-sub-test'])
        );

        $this->assertEquals(
            ['/test', '/'],
            $method->invokeArgs($translate, ['/test'])
        );

        $this->assertEquals(
            ['/'],
            $method->invokeArgs($translate, ['/'])
        );
    }

    public function testValidateI18nString()
    {
        $translate = new Translate([Translate::OPTION_BASEURL => 'http://url']);

        $i18n_string = $this->getValidI18nString();
        $res = $this->invokeNonPublicMethod($translate, 'validateI18nString', [$i18n_string]);
        $this->assertNull($res);

        $i18n_string->setContent('');
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('I18nString entity is not valid: (content: Content cannot be empty)');
        $this->invokeNonPublicMethod($translate, 'validateI18nString', [$i18n_string]);
    }

    public function testDomainWithNotADomain()
    {
        $translate = new Translate();

        $class = new \ReflectionClass('Fei\Service\Translate\Client\Translate');
        $method = $class->getMethod('domainHierarchy');
        $method->setAccessible(true);

        $this->assertEquals(
            [],
            $method->invokeArgs($translate, [''])
        );

        $this->assertEquals(
            [],
            $method->invokeArgs($translate, [null])
        );

        $this->assertEquals(
            [],
            $method->invokeArgs($translate, ['notadomain'])
        );

        $this->assertEquals(
            [],
            $method->invokeArgs($translate, ['notadomain/test'])
        );
    }

    protected function invokeNonPublicMethod($object, $name, array $args = [])
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($name);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $args);
    }

    private function getValidI18nString()
    {
        return (new I18nString())->setId(1)
            ->setContent('Content')
            ->setLang('fr_FR')
            ->setKey('KEY')
            ->setCreatedAt(new \DateTime())
            ->setNamespace('/domain');
    }

    public function testIsDomain()
    {
        $translate = new Translate();

        $this->assertTrue($this->invokeNonPublicMethod($translate, 'isDomain', ['/a']));
        $this->assertTrue($this->invokeNonPublicMethod($translate, 'isDomain', ['/a/b']));
        $this->assertTrue($this->invokeNonPublicMethod($translate, 'isDomain', ['/a/b/D']));
        $this->assertFalse($this->invokeNonPublicMethod($translate, 'isDomain', ['a']));
        $this->assertFalse($this->invokeNonPublicMethod($translate, 'isDomain', ['a/b']));
    }

    public function testIsLang()
    {
        $translate = new Translate();

        $this->assertTrue($this->invokeNonPublicMethod($translate, 'isLang', ['fr']));
        $this->assertTrue($this->invokeNonPublicMethod($translate, 'isLang', ['en']));
        $this->assertTrue($this->invokeNonPublicMethod($translate, 'isLang', ['fr_FR']));

        $this->assertFalse($this->invokeNonPublicMethod($translate, 'isLang', ['fra']));
        $this->assertFalse($this->invokeNonPublicMethod($translate, 'isLang', ['fr-FR']));
        $this->assertFalse($this->invokeNonPublicMethod($translate, 'isLang', ['fr-fr']));
        $this->assertFalse($this->invokeNonPublicMethod($translate, 'isLang', ['fr_FRA']));
    }
}
