<?php

namespace Fei\Service\Translate\Client;

use Fei\Service\Translate\Client\Utils\Pattern;
use Fei\Service\Translate\Entity\I18nString;

/**
 * Interface TranslateInterface
 *
 * @package Fei\Service\Translate\Client
 */
interface TranslateInterface
{
    /**
     * Fetch an I18nString by its primary key ID
     *
     * @param int $id
     *
     * @return I18nString
     */
    public function fetchOne($id);

    /**
     * Find an I18nString by its translation key
     *
     * @param string $key
     * @param string $lang
     * @param string $domain
     *
     * @return I18nString
     */
    public function find($key, $lang = '', $domain = '');

    /**
     * Search I18nString entities corresponding to a pattern.
     *
     * The pattern could contain `*` which will replace any string
     *
     * @param Pattern $pattern
     *
     * @return I18nString[]
     */
    public function search(Pattern $pattern);

    /**
     * Store I18nString(s) on translate server
     *
     * @param I18nString|I18nString[] $string
     *
     * @return bool
     */
    public function store($string);

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
    public function delete($parameter);

    /**
     * Subscribe a callback on translate server for receive translations updates
     *
     * @param array $server
     * @param array $namespaces
     * @param string $encoding
     *
     * @return bool
     */
    public function subscribe($server = null, array $namespaces = [], $encoding = 'UTF-8');

    /**
     * Remove the subscription callback
     *
     * @param array $server
     * @param array $namespaces
     *
     * @return bool
     */
    public function unsubscribe($server = null, $namespaces = []);
}
