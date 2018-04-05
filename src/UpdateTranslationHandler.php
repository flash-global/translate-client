<?php
namespace Fei\Service\Translate\Client;

use Fei\Service\Translate\Client\Exception\TranslateException;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Zend\Diactoros\Response;

/**
 * Class UpdateTranslationHandler
 *
 * @package Fei\Service\Translate\Client
 */
class UpdateTranslationHandler
{
    use ConfigAwareTrait;

    /**
     * UpdateTranslationHandler constructor.
     *
     * @param array $config
     */
    public function __construct($config)
    {
        $this->importConfig($config);
    }

    /**
     * Handle Update request
     *
     * @return Response|Response\JsonResponse
     */
    public function __invoke()
    {
        error_log(print_r($_SERVER, true));
        $body = (isset($_POST['body'])) ? $_POST['body'] : null;

        if (null !== $body) {
            $config = $this->getConfig();
            $this->importArchive($config['data_path'], $body);

            return new Response('php://memory', 204);
        } elseif (isset($_GET['getInfos'])) {
            return new Response\JsonResponse($this->getConfig());
        }
    }

    /**
     * Import translations files
     *
     * @param $path
     * @param $body
     */
    protected function importArchive($path, $body)
    {
        $config = $this->getConfig();
        $tmpDir = $path . '/' . uniqid();

        $lock = $config['lock_file'];
        if (!is_file($lock)) {
            $this->createLockFile($lock);
        }
        if (mkdir($tmpDir, 0777, true)) {
            file_put_contents($tmpDir . '/archive.zip', base64_decode($body));
            $zip = new \ZipArchive();
            $zip->open($tmpDir . '/archive.zip');

            $list = $tmpDir . '/archive';
            $zip->extractTo($list);

            if (is_dir($list)) {
                $translationsPath = $config['translations_path'];

                if (!is_dir($translationsPath)) {
                    mkdir($translationsPath, 0777, true);
                }

                $this->importFiles($list, $translationsPath);
            }

            $this->rmdir($tmpDir);
        }
    }

    /**
     * Import all the files present in namespaces
     *
     * @param $source
     * @param $dest
     */
    protected function importFiles($source, $dest)
    {
        $source = str_replace('\\', '/', realpath($source));

        if (is_dir($source) === true) {
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($files as $file) {
                $file = str_replace('\\', '/', $file);

                if (in_array(substr($file, strrpos($file, '/')+1), array('.', '..'))) {
                    continue;
                }

                $file = realpath($file);

                if (is_dir($file) === true) {
                    $this->createDirectory($dest . '/' . str_replace($source . '/', '', $file . '/'));
                } elseif (is_file($file) === true) {
                    $this->copyFile($file, $dest . '/' . str_replace($source . '/', '', $file));
                }
            }
        }
    }

    /**
     * Create the directory according to the namespace
     *
     * @param $dir
     *
     * @return UpdateTranslationHandler
     */
    protected function createDirectory($dir)
    {
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $this;
    }

    /**
     * Copy the new translation file into the $dest directory
     *
     * @param $source
     * @param $dest
     *
     * @return UpdateTranslationHandler
     */
    protected function copyFile($source, $dest)
    {
        // renaming the old one
        //if (is_file($dest)) {
        //    rename($dest, $dest . '.' . time());
        //}

        // copy the new one
        copy($source, $dest);

        return $this;
    }

    /**
     * Remove a directory recursively
     *
     * @param string $dir
     * @return bool
     */
    protected function rmdir($dir)
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->rmdir("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }
}
