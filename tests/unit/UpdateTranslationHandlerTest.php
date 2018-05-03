<?php
namespace Tests\Fei\Service\Translate\Client;

use Codeception\Test\Unit;
use Codeception\Util\Stub;
use Fei\Service\Translate\Client\Exception\TranslateException;
use Fei\Service\Translate\Client\UpdateTranslationHandler;
use Guzzle\Tests\Service\Mock\Command\Sub\Sub;
use ZipArchive;

/**
 * Class UpdateTranslationHandlerTest
 *
 * @package Tests\Fei\Service\Translate\Client
 */
class UpdateTranslationHandlerTest extends Unit
{
    public function testUpdateTranslationHandlerIsCallable()
    {
        $obj = Stub::make(UpdateTranslationHandler::class, [
            'importConfig' => true
        ]);
        $this->assertTrue(is_callable($obj));
    }

    public function testInvokeMethodWhenDataPathExists()
    {
        $handler = Stub::make(UpdateTranslationHandler::class, [
            'getConfig' => Stub::once(function () {
                return [
                    'data_path' => 'fake-path'
                ];
            }),
            'setConfig' => Stub::once(),
            'importConfig' => Stub::once(),
            'importArchive' => Stub::once()
        ]);

        $_POST['body'] = 'fake-body';
        $handler->__invoke();
    }

    public function testInvokeMethodWhenDataPathDoesNotExists()
    {
        $handler = Stub::make(UpdateTranslationHandler::class, [
            'getConfig' => Stub::once(),
            'importConfig' => function () {
                throw new TranslateException();
            },
            'setConfig' => Stub::once(),
            'importArchive' => Stub::never()
        ]);

        $this->setExpectedException(TranslateException::class);

        $_POST['body'] = 'fake-body';
        $handler->__construct([]);
    }

    public function testInvokeMethodWhenGetParameterGetInfosIsPassed()
    {
        $expected = ['fake-config'];
        $handler = Stub::make(UpdateTranslationHandler::class, [
            'importConfig' => $expected,
            'getConfig' => $expected
        ]);

        $_GET['getInfos'] = true;
        $handler->__construct($expected);
        $response = $handler->__invoke();

        $this->assertEquals(json_encode($expected), $response->getBody());
    }

    public function testImportArchiveWhenTranslationPathExists()
    {
        $path = dirname(__DIR__) . '/data/translate/archive';

        $handler = Stub::make(UpdateTranslationHandler::class, [
            'importFiles' => Stub::once(),
            'getConfig' => [
                'translations_path' => $path . '/tmp_path',
                'lock_file' => dirname(__FILE__) . '/fake-lock'
            ],
            'importConfig' => Stub::once(),
            'createLockFile' => Stub::once(),
        ]);

        $body = base64_encode(file_get_contents($path . '/one.zip'));
        $this->invokeNonPublicMethod($handler, 'importArchive', [$path, $body]);
        $this->assertFileExists($path . '/tmp_path');
        $this->invokeNonPublicMethod($handler, 'rmdir', [$path . '/tmp_path']);
    }

    public function testImportFiles()
    {
        $handler = Stub::make(UpdateTranslationHandler::class, [
            'createDirectory' => Stub::exactly(3),
            'copyFile' => Stub::exactly(4),
        ]);

        $dir = dirname(__DIR__) . '/data/tmp_dir/a/b/c';
        mkdir($dir, 0777, true);
        file_put_contents($dir . '/f.txt', time());
        file_put_contents($dir . '/../f.txt', time());
        file_put_contents($dir . '/../../f.txt', time());
        file_put_contents($dir . '/../../../f.txt', time());

        $this->invokeNonPublicMethod($handler, 'importFiles', [dirname(__DIR__) . '/data/tmp_dir', 'fake-dest']);
        $this->invokeNonPublicMethod($handler, 'rmdir', [dirname(__DIR__) . '/data/tmp_dir']);
    }

    public function testCreateDirectory()
    {
        $handler = Stub::make(UpdateTranslationHandler::class, [
            'importConfig' => true
        ]);

        $dir = dirname(__DIR__) . '/data/tmp_not_exists';

        $this->invokeNonPublicMethod($handler, 'createDirectory', [$dir]);

        $this->assertFileExists($dir);
        $this->invokeNonPublicMethod($handler, 'rmdir', [$dir]);
    }

    public function testCopyFileWhenTheDestinationDoesNotExistsYet()
    {
        $handler = Stub::make(UpdateTranslationHandler::class, [
            'importConfig' => true
        ]);

        $dir = dirname(__DIR__) . '/data/tmp';
        mkdir($dir, 0777, true);

        $src = $dir . '/src';
        file_put_contents($src, 'src');

        $dest = $dir . '/dest';

        $this->invokeNonPublicMethod($handler, 'copyFile', [$src, $dest]);

        $this->assertFileExists($src);
        $this->assertFileExists($dest);

        $this->invokeNonPublicMethod($handler, 'rmdir', [$dir]);
    }

    public function testCopyFileWhenTheDestinationExists()
    {
        $handler = Stub::make(UpdateTranslationHandler::class, [
            'importConfig' => true
        ]);

        $dir = dirname(__DIR__) . '/data/tmp';
        mkdir($dir, 0777, true);

        $src = $dir . '/src';
        $dest = $dir . '/dest';

        file_put_contents($src, 'src');
        file_put_contents($dest, 'dest');

        $this->invokeNonPublicMethod($handler, 'copyFile', [$src, $dest]);

        $this->assertFileExists($dest);
        $this->assertFileExists($src);

        $this->invokeNonPublicMethod($handler, 'rmdir', [$dir]);
    }

    public function testRmdir()
    {
        $base = dirname(__DIR__) . '/data';
        $dir = $base . '/a/b/c/d';

        mkdir($dir, 0777, true);
        file_put_contents($base . '/a/file.txt', 'file');
        file_put_contents($base . '/a/b/file.txt', 'file');
        file_put_contents($base . '/a/b/c/file.txt', 'file');
        file_put_contents($base . '/a/b/c/d/file.txt', 'file');



        $handler = $this->getMockBuilder(UpdateTranslationHandler::class)
            ->disableOriginalConstructor()
            ->setMethods(null)
            ->getMock();

        $this->assertFileExists($dir);
        $this->invokeNonPublicMethod($handler, 'rmdir', [$base . '/a']);
        $this->assertFileNotExists($dir);
    }

    protected function invokeNonPublicMethod($object, $name, array $args = [])
    {
        $reflection = new \ReflectionClass($object);
        $method = $reflection->getMethod($name);
        $method->setAccessible(true);

        return $method->invokeArgs($object, $args);
    }
}
