<?php
namespace Fei\Service\Translate\Client;

use Fei\Service\Translate\Client\Exception\TranslateException;

trait ConfigAwareTrait
{
    /**
     * @var array
     */
    protected $config = [];

    /**
     * Get Config
     *
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Set Config
     *
     * @param array $config
     *
     * @return $this
     */
    public function setConfig(array $config)
    {
        $this->config = $config;

        return $this;
    }

    /**
     * Get the path of the config file
     *
     * @return string
     */
    public function getConfigFilePath()
    {
        return dirname(__DIR__) . '/config/config.php';
    }

    /**
     * Import the config
     *
     * @return array
     */
    protected function importConfig($config = null)
    {
        $file = $this->getConfigFilePath();
        if ($config !== null) {
            $this->setConfig($config);
            $this->validateConfig();
        } elseif (is_file($file)) {
            $config = include $file;
            $this->setConfig($config);
            $this->validateConfig();
        } else {
            throw new TranslateException('Config file not found!', 404);
        }

        return $this->getConfig();
    }

    /**
     * Validate the configuration
     *
     * @return bool
     */
    protected function validateConfig()
    {
        $config = $this->getConfig();
        $skipSubscription = (isset($config['skipSubscription'])) ? (bool)$config['skipSubscription'] : false;

        $mandatoryKeys = ['lock_file', 'data_path', 'translations_path', 'url'];

        foreach ($mandatoryKeys as $key) {
            if (!isset($config[$key]) && $skipSubscription === false) {
                throw new TranslateException('The `' . $key . '` config must be specified!', 400);
            }
        }

        return true;
    }

    /**
     * Create the lock file in the client
     *
     * @param string $file
     */
    protected function createLockFile($file)
    {
        file_put_contents($file, time());
    }
}
