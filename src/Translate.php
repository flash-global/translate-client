<?php

namespace Fei\Service\Translate\Client;

use DateInterval;
use DateTime;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Fei\ApiClient\AbstractApiClient;
use Fei\ApiClient\RequestDescriptor;
use Fei\ApiClient\ResponseDescriptor;
use Fei\Entity\Exception;
use Fei\Service\Logger\Client\Logger;
use Fei\Service\Logger\Entity\Notification;
use Fei\Service\Translate\Client\Exception\TranslateException;
use Fei\Service\Translate\Client\Exception\ValidationException;
use Fei\Service\Translate\Client\Utils\ArrayCollection;
use Fei\Service\Translate\Client\Utils\Pattern;
use Fei\Service\Translate\Entity\I18nString;
use Fei\Service\Translate\Validator\I18nStringValidator;
use Guzzle\Http\Exception\BadResponseException;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response;
use ZipArchive;

/**
 * Class Translate
 *
 * @package Fei\Service\Translate\Client
 */
class Translate extends AbstractApiClient implements TranslateInterface
{
    use ConfigAwareTrait;

    const API_TRANSLATE_PATH_INFO = '/api/i18n-string';
    const API_TRANSLATE_PATH_UPDATE = '/api/translation-cache';
    const OPTION_LOG_ON_MISSING_TRANSLATION = 'logOnMissingTranslation';
    const OPTION_LOG_LEVEL = 'logLevel';

    protected $staticCache = [];

    /**
     * @var bool
     */
    protected $logOnMissingTranslation = false;

    /**
     * @var int
     */
    protected $logLevel = Notification::LVL_DEBUG;

    /**
     * @var string
     */
    protected $lang;

    /**
     * @var string
     */
    protected $domain;

    /**
     * @var Dispatcher
     */
    protected $dispatcher;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var ResponseInterface
     */
    protected $response;

    /**
     * @var Translate
     */
    protected static $client = null;

    public function __construct(array $options = array(), $config = null)
    {
        parent::__construct($options);
        $this->importConfig($config);
        $this->initDispatcher();
    }

    /**
     * @param $path
     * @return bool
     */
    protected function checkWritable($path)
    {
        return (is_dir($path) && !is_writable($path)) || (!is_dir($path) && !is_writable(pathinfo($path, PATHINFO_DIRNAME)));
    }

    /**
     * Check if the dir that have to be writable are
     *
     * @return bool
     */
    protected function checkWritableDirectories()
    {
        $config = $this->getConfig();

        if (isset($config['skipSubscription']) && $config['skipSubscription']) {
            return true;
        }

        if ($this->checkWritable($config['data_path']) || $this->checkWritable($config['translations_path'])) {
            if ($this->getLogger() instanceof Logger) {
                $notif = new Notification([
                    'message' => 'Directories not writable',
                    'level' => Notification::LVL_WARNING,
                    'context' => [
                        'directories' => $config['data_path'] . ' and ' . $config['translations_path']
                    ]
                ]);
                $this->getLogger()->notify($notif);
            }
        }
        return true;
    }

    /**
     * Build the default subscription to an api server
     *
     * @return bool
     */
    public function buildDefaultSubscription()
    {
        $config = $this->getConfig();

        $isOk = true;
        $servers = isset($config['servers']) ? $config['servers'] : null;

        if (null === $servers) {
            $servers = [$this->getBaseUrl() => []];
        }

        foreach ($servers as $server => $options) {
            $this->setBaseUrl($server);

            $namespaces = (isset($options['namespaces']) && is_array($options['namespaces'])) ? $options['namespaces'] : [];
            $encoding = (isset($options['encoding'])) ? $options['encoding'] : 'UTF-8';
            $host = (isset($options['host'])) ? $options['host'] : null;

            $isOk = $isOk && $this->subscribe($server, $namespaces, $encoding, $host);
        }

        return $isOk;
    }

    /**
     * Fetch an I18nString by its primary key ID
     *
     * @param int $id
     *
     * @return I18nString
     */
    public function fetchOne($id)
    {
        $this->checkTransport();

        $request = (new RequestDescriptor())
            ->setMethod('GET')
            ->setUrl($this->buildUrl(self::API_TRANSLATE_PATH_INFO . '?id=' . urlencode($id)));

        /**
         * @var I18nString $i18nString
         */
        $i18nString = $this->fetch($request);

        return $i18nString;
    }

    /**
     * @param array $namespaces
     * @return string
     */
    protected function buildQueryNamespaces(array $namespaces)
    {
        $fieldName = 'namespaces[]';
        $query = [];

        foreach ($namespaces as $namespace) {
            $query[] = $fieldName . '=' . $namespace;
        }

        return implode('&', $query);
    }

    /**
     * @param string $data
     */
    protected function manageUpdateFile($data)
    {
        $config = $this->getConfig();
        $filePath = $config['translations_path'] . '/update.zip';

        file_put_contents($filePath, base64_decode($data));

        $zip = new ZipArchive();
        $zip->open($filePath);
        $zip->extractTo($config['translations_path']);

        unlink($filePath);
    }

    /**
     * @param array $namespaces
     * @param string $server
     */
    protected function fetchAllByServer(array $namespaces, $server)
    {
        $this->setBaseUrl($server);
        $queryNamespaces = $this->buildQueryNamespaces($namespaces);

        $request = (new RequestDescriptor())
            ->setMethod('GET')
            ->setUrl($this->buildUrl(self::API_TRANSLATE_PATH_UPDATE . '?' . $queryNamespaces));

        /** @var ResponseDescriptor $result */
        $result = $this->send($request);

        $bodyJson = $result->getBody()->getContents();
        $bodyData = json_decode($bodyJson, true);

        $this->manageUpdateFile($bodyData);
    }

    /**
     * @return bool
     * @throws \Exception
     */
    protected function lockFileExpired()
    {
        $config = $this->getConfig();

        if (!is_readable($config['lock_file'])) {
            return true;
        }

        $lockFileContent = file_get_contents($config['lock_file']);
        $dateCreateLockFile = new DateTime();
        $dateCreateLockFile->setTimestamp($lockFileContent);
        $dateCreateLockFile->add(new DateInterval('PT24H'));
        $now = new DateTime();

        return $now > $dateCreateLockFile;
    }

    /**
     * In charge to get translations from all translate servers
     *
     * @throws TranslateException
     * @throws \Exception
     */
    public function fetchAll()
    {
        if (!$this->lockFileExpired()) {
            return;
        }

        $config = $this->getConfig();

        $this->checkTransport();

        $servers = isset($config['servers']) ? $config['servers'] : null;

        if (is_null($servers)) {
            throw new TranslateException('No servers for update');
        }

        $this->createLockFile($config['lock_file']);

        foreach ($servers as $server => $options) {
            $namespaces = (isset($options['namespaces']) && is_array($options['namespaces']))
                ? $options['namespaces']
                : [];

            if (!empty($namespaces)) {
                $this->fetchAllByServer($namespaces, $server);
            }
        }
    }

    /**
     * Find an I18nString by its translation key
     *
     * @param string $key
     * @param string $lang
     * @param string $domain
     *
     * @return ArrayCollection|I18nString
     */
    public function find($key, $lang = '', $domain = '')
    {
        $this->checkTransport();

        $data = [
            'key' => $key,
            'lang' => $lang,
            'namespace' => $domain,
        ];
        $data = array_filter($data);

        $request = (new RequestDescriptor())
            ->setMethod('GET')
            ->setUrl($this->buildUrl(self::API_TRANSLATE_PATH_INFO . '?' . http_build_query($data)));

        $res = $this->send($request);
        $values = \json_decode($res->getBody(), true);

        $collection = new ArrayCollection();
        foreach ($values as $value) {
            $collection->add(new I18nString($value));
        }

        if (!empty($key) && !empty($lang) && !empty($domain)) {
            return $collection->get(0);
        }

        return $collection;
    }

    /**
     * Search I18nString entities corresponding to a pattern.
     *
     * The pattern could contain `*` which will replace any string
     *
     * @param Pattern $pattern
     *
     * @return I18nString[]
     */
    public function search(Pattern $pattern)
    {
        $this->checkTransport();

        $request = (new RequestDescriptor())
            ->setMethod('GET')
            ->setUrl($this->buildUrl(self::API_TRANSLATE_PATH_INFO . '?content=' . urlencode($pattern->__toString())));

        $res = $this->send($request);
        $values = \json_decode($res->getBody(), true);

        $values = array_map(function ($v) {
            return new I18nString($v);
        }, $values);

        return $values;
    }

    /**
     * Store I18nString(s) on translate server
     *
     * @param I18nString|I18nString[] $string
     * @return array
     * @throws Exception
     */
    public function store($string)
    {
        $this->checkTransport();

        $entities = (is_array($string)) ? $string : [$string];
        foreach ($entities as &$entity) {
            if ($entity instanceof I18nString) {
                $this->validateI18nString($entity);
                $entity = $entity->toArray();
            } else {
                throw new Exception('You have to send an I18nString entity!', 400);
            }
        }

        $request = (new RequestDescriptor())
            ->setMethod('POST')
            ->setUrl($this->buildUrl(self::API_TRANSLATE_PATH_INFO));
        $request->setBodyParams(['entities' => \json_encode($entities)]);

        $res = $this->send($request);
        return \json_decode($res->getBody(), true);
    }

    /**
     * Update I18nString(s) on translate server
     *
     * @param I18nString|I18nString[] $string
     * @return array
     * @throws Exception
     */
    public function update($string)
    {
        $this->checkTransport();

        $entities = (is_array($string)) ? $string : [$string];
        foreach ($entities as &$entity) {
            if (!$entity instanceof I18nString) {
                throw new Exception('You have to send an I18nString entity!');
            }

            if (empty($entity->getId())) {
                throw new TranslateException('An id of the translation has to been set before updating it!', 400);
            }
            $this->validateI18nString($entity);
            $entity = $entity->toArray();
        }

        $request = (new RequestDescriptor())
            ->setMethod('PATCH')
            ->setUrl($this->buildUrl(self::API_TRANSLATE_PATH_INFO));
        $request->setBodyParams(['entities' => \json_encode($entities)]);

        $res = $this->send($request);
        return \json_decode($res->getBody(), true);
    }

    /**
     * Delete on translate server all I18nString(s) matching parameter
     *
     * If the parameter is a translation key (string) or a pattern (Pattern), all search occurrences will be deleted.
     * For deleting one occurrence, use a primary key (int) or an I18nString instance.
     *
     * @param int|I18nString|Pattern|string $parameter
     *
     * @return bool
     */
    public function delete($parameter)
    {
        $this->checkTransport();

        $params = [];

        // want to delete according to a unique ID
        if (is_string($parameter)) {
            $params['key'] = $parameter;
        } elseif (is_int($parameter)) {
            $params['id'] = $parameter;
        } elseif ($parameter instanceof I18nString) {
            $params['id'] = $parameter->getId();
        } elseif ($parameter instanceof Pattern) {
            $params['pattern'] = $parameter->__toString();
        } else {
            throw new TranslateException(
                'Bad request: the parameter hs to be a valid `key`, `id`, `entity` or `Pattern`',
                400
            );
        }

        $request = (new RequestDescriptor())
            ->setMethod('DELETE')
            ->setUrl($this->buildUrl(self::API_TRANSLATE_PATH_INFO));
        $request->setBodyParams(['params' => \json_encode($params)]);

        $res = $this->send($request);
        return \json_decode($res->getBody(), true);
    }

    /**
     * Subscribe a callback on translate server for receive translations updates
     *
     * @param array $server
     * @param array $namespaces
     * @param string $encoding
     *
     * @param null $host
     * @return bool
     */
    public function subscribe($server = null, array $namespaces = [], $encoding = 'UTF-8', $host = null)
    {
        $this->checkWritableDirectories();

        $config = $this->getConfig();

        if (isset($config['skipSubscription']) && $config['skipSubscription']) {
            return false;
        }

        if ($server === null) {
            return $this->buildDefaultSubscription();
        }

        if (is_file($config['subscribe_lock'])) {
            return true;
        }

        $this->createLockFile($config['subscribe_lock']);

        $this->setBaseUrl($server);
        $this->checkTransport();

        if (!isset($config['url'])) {
            $this->notify('Call url not configured in the config file', ['level' => Notification::LVL_ERROR]);
        }

        $params = [
            'namespaces' => $namespaces,
            'url' => $config['url'],
            'encoding' => $encoding
        ];

        if (null !== $host) {
            $params['host'] = $host;
        }

        $request = (new RequestDescriptor())
            ->setMethod('POST')
            ->setUrl($this->buildUrl(self::API_TRANSLATE_PATH_INFO . '/subscribe'));
        $request->setBodyParams(['params' => \json_encode($params)]);

        try {
            $res = $this->send($request);
        } catch (\Exception $e) {
            $this->notify('something went wrong while subscribing to translate', ['level' => Notification::LVL_ERROR]);
            unlink($config['subscribe_lock']);
            return false;
        }
        $res = \json_decode($res->getBody(), true);

        if ($res) {
            return $res;
        }

        unlink($config['subscribe_lock']);
    }

    /**
     * Remove the subscription callback
     *
     * @param array $server
     * @param array $namespaces
     *
     * @return bool
     */
    public function unsubscribe($server = null, $namespaces = [])
    {
        $this->checkWritableDirectories();

        $config = $this->getConfig();

        if (null === $server) {
            $servers = isset($config['servers']) ? $config['servers'] : [$this->getBaseUrl()];

            $isOk = true;
            foreach (array_keys($servers) as $server) {
                $isOk = $isOk && $this->unsubscribe($server, $namespaces);
            }

            return $isOk;
        }
        $this->setBaseUrl($server);
        $this->checkTransport();

        $url = isset($config['url']) ? $config['url'] : null;

        if (null === $url) {
            throw new TranslateException('Call url not configured in the config file!', 403);
        }

        $params = [
            'namespaces' => $namespaces,
            'url' => $url
        ];

        $request = (new RequestDescriptor())
            ->setMethod('DELETE')
            ->setUrl($this->buildUrl(self::API_TRANSLATE_PATH_INFO . '/unsubscribe'));
        $request->setBodyParams(['params' => \json_encode($params)]);

        $res = $this->send($request);
        $res = \json_decode($res->getBody(), true);

        if ($res && is_file($config['lock_file'])) {
            unlink($config['lock_file']);
        }

        return $res;
    }

    /**
     * Init the routes dispatcher
     *
     * @return Translate
     */
    protected function initDispatcher()
    {
        $config = $this->getConfig();
        $url = parse_url($config['url']);

        if (!empty($url['path'])) {
            $this->setDispatcher(
                \FastRoute\simpleDispatcher(function (RouteCollector $r) use ($url) {
                    $r->addRoute('POST', $url['path'], new UpdateTranslationHandler($this->getConfig()));
                    $r->addRoute('GET', $url['path'], new UpdateTranslationHandler($this->getConfig()));
                })
            );
        }

        return $this;
    }

    /**
     * Handle translate request
     *
     * @param string $requestUri
     * @param string $requestMethod
     *
     * @return Translate
     */
    public function handleRequest($requestUri = null, $requestMethod = null)
    {
        $config = $this->getConfig();
        $skipSubscription = (isset($config['skipSubscription'])) ? (bool)$config['skipSubscription'] : false;

        if ($skipSubscription) {
            return $this;
        }

        $pathInfo = $requestUri;

        if (false !== ($pos = strpos($pathInfo, '?'))) {
            $pathInfo = substr($pathInfo, 0, $pos);
        }

        $pathInfo = rawurldecode($pathInfo);

        $info = $this->getDispatcher()->dispatch($requestMethod, $pathInfo);

        if ($info[0] == Dispatcher::FOUND) {
            $response = $info[1]($this);

            if ($response instanceof ResponseInterface) {
                $this->setResponse($response);
            }
        }

        return $this;
    }

    /**
     * Emit the client response if exists and die...
     */
    public function emit()
    {
        if (headers_sent($file, $line)) {
            throw new \LogicException(sprintf('Headers already sent in %s on line %d', $file, $line));
        }
        if ($this->getResponse()) {
            (new Response\SapiEmitter())->emit($this->getResponse());
            exit();
        }
    }

    /**
     * Returns the hierarchy of a domain string
     *
     * @param string $domain
     *
     * @return array
     */
    protected function domainHierarchy($domain)
    {
        if (!$this->isDomain($domain)) {
            return [];
        }

        $hierarchy = $domain === '/' ? [] : [$domain];

        while (($pos = strrpos($domain, '/')) !== false) {
            $domain = substr($domain, 0, $pos);
            $hierarchy[] = empty($domain) ? '/' : $domain;
        }

        return $hierarchy;
    }

    /**
     * Check if a string is a translation domain
     *
     * @param string $domain
     *
     * @return bool
     */
    protected function isDomain($domain)
    {
        return is_string($domain) && !empty($domain) && $domain[0] === '/';
    }

    /**
     * Check if a string is a valid language
     *
     * @param string $lang
     *
     * @return bool
     */
    protected function isLang($lang)
    {
        return is_string($lang) && preg_match('/^[a-z]{2}(_[A-Z]{2})?$/', $lang);
    }

    /**
     * Validate a File entity
     *
     * @param I18nString $string
     */
    protected function validateI18nString(I18nString $string)
    {
        $validator = new I18nStringValidator();

        if (!$validator->validate($string)) {
            throw (new ValidationException(
                sprintf('I18nString entity is not valid: (%s)', $validator->getErrorsAsString()),
                400
            ))->setErrors($validator->getErrors());
        }
    }

    /**
     * {@inheritdoc}
     */
    public function send(RequestDescriptor $request, $flags = 0)
    {
        try {
            $response = parent::send($request, $flags);

            if ($response instanceof ResponseDescriptor) {
                return $response;
            }
        } catch (\Exception $e) {
            $previous = $e->getPrevious();
            if ($previous instanceof BadResponseException) {
                $data = \json_decode($previous->getResponse()->getBody(true), true);
                if (isset($data['code']) && isset($data['error'])) {
                    throw new TranslateException($data['error'], $data['code'], $e);
                }
            }

            throw new TranslateException($e->getMessage(), $e->getCode(), $e);
        }

        return null;
    }

    /**
     * Check that a transport has been set
     *
     * @return Translate
     */
    protected function checkTransport()
    {
        if (!$this->getTransport()) {
            throw new TranslateException('Transport has to be set');
        }
    }

    /**
     * Get Dispatcher
     *
     * @return Dispatcher
     */
    public function getDispatcher()
    {
        return $this->dispatcher;
    }

    /**
     * Set Dispatcher
     *
     * @param Dispatcher $dispatcher
     *
     * @return $this
     */
    public function setDispatcher($dispatcher)
    {
        $this->dispatcher = $dispatcher;

        return $this;
    }

    /**
     * @param $domain
     * @param $lang
     *
     * @throws TranslateException
     */
    protected function checkTranslate($domain, $lang)
    {
        // domain not valid
        if (!$this->isDomain($domain)) {
            throw new TranslateException('This domain is not valid!', 400);
        }

        // lang not valid
        if (!$this->isLang($lang)) {
            throw new TranslateException('This lang is not valid!', 400);
        }
    }

    protected function domain($domain)
    {
        return null === $domain ? $this->getDomain() : $domain;
    }

    protected function lang($lang)
    {
        return null === $lang ? $this->getLang() : $lang;
    }

    /**
     * @param string $domain
     * @param string $lang
     */
    protected function loadTranslations($domain = null, $lang = null)
    {
        $config = $this->getConfig();

        if (isset($config['translations_path']) && is_readable($config['translations_path'] . $domain . '/' . $lang . '.php')) {
            $this->staticCache[$domain][$lang] = include $config['translations_path'] . $domain . '/' . $lang . '.php';
        } elseif (isset($config['localTranslationsFile']) && is_readable($config['localTranslationsFile'])) {
            $this->staticCache[$domain][$lang] = include $config['localTranslationsFile'];
        } else {
            throw new TranslateException('No domain or lang founded');
        }
    }

    /**
     * @param string $domain
     * @param string $lang
     *
     * @return array
     */
    protected function getTranslations($domain = null, $lang = null)
    {
        if (empty($this->staticCache[$domain][$lang])) {
            $this->loadTranslations($domain, $lang);
        }

        return $this->staticCache[$domain][$lang];
    }

    /**
     * Get the translation for the key $key in the domain $domain for the lang $lang
     *
     * @param $key
     * @param $domain
     * @param $lang
     *
     * @return string
     */
    public function translate($key, $domain = null, $lang = null)
    {
        $domain = $this->domain($domain);
        $lang = $this->lang($lang);

        $translated = $key;
        $found = false;

        $translations = $this->getTranslations($domain, $lang);

        if (isset($translations[$key])) {
            $found = true;
            $translated = $translations[$key];
        }

        if ($this->logOnMissingTranslation && false === $found && $this->getLogger() instanceof Logger) {
            $notif = new Notification([
                'message' => 'Translation not found!',
                'level' => $this->logLevel,
                'context' => [
                    'key' => $key,
                    'domain' => $domain,
                    'lang' => $lang
                ]
            ]);

            $this->getLogger()->notify($notif);
        }

        return $translated;
    }

    /**
     * Set the translate client
     *
     * @param Translate $client
     *
     * @return Translate
     */
    public static function setClient(Translate $client)
    {
        self::$client = $client;

        return self::$client;
    }

    /**
     * Get an instance of client
     *
     * @return Translate
     */
    public static function getClient()
    {
        if (self::$client === null) {
            throw new TranslateException('Client has to be set before using it!');
        }

        return self::$client;
    }

    /**
     * Get Lang
     *
     * @return string
     */
    public function getLang()
    {
        return $this->lang;
    }

    /**
     * Set Lang
     *
     * @param string $lang
     *
     * @return $this
     */
    public function setLang($lang)
    {
        $this->lang = $lang;

        return $this;
    }

    /**
     * Get Domain
     *
     * @return string
     */
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * Set Domain
     *
     * @param string $domain
     *
     * @return $this
     */
    public function setDomain($domain)
    {
        $this->domain = $domain;

        return $this;
    }

    /**
     * Get Logger
     *
     * @return Logger
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Set Logger
     *
     * @param Logger $logger
     *
     * @return Translate
     */
    public function setLogger(Logger $logger)
    {
        $logger->setFilterLevel(Notification::LVL_WARNING);
        $this->logger = $logger;

        return $this;
    }

    /**
     * Get Response
     *
     * @return ResponseInterface
     */
    public function getResponse()
    {
        return $this->response;
    }

    /**
     * Set Response
     *
     * @param ResponseInterface $response
     *
     * @return $this
     */
    public function setResponse($response)
    {
        $this->response = $response;
        return $this;
    }

    protected function notify($message, $params = [])
    {
        if ($this->getLogger() instanceof Logger) {
            $notification = new Notification($params);
            $notification->setMessage($message);

            $this->getLogger()->notify($notification);
        }
    }
}
