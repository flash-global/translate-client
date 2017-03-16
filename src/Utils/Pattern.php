<?php

namespace Fei\Service\Translate\Client\Utils;

/**
 * Class Pattern
 *
 * @package Fei\Service\Translate\Client
 */
class Pattern
{
    /**
     * @var string
     */
    protected $pattern;

    /**
     * Pattern constructor.
     *
     * @param string $value
     * @param null   $prepend
     * @param null   $append
     */
    public function __construct($value, $prepend = null, $append = null)
    {
        $this->pattern = $this->buildPattern($value, $prepend, $append);
    }

    /**
     * Build pattern
     *
     * @param string $value
     * @param null   $prepend
     * @param null   $append
     *
     * @return string
     */
    protected function buildPattern($value, $prepend = null, $append = null)
    {
        return sprintf('%s%s%s', $prepend, $value, $append);
    }

    /**
     * Creates an equals pattern
     * (pattern=value)
     *
     * @param $value
     *
     * @return static
     */
    public static function equals($value)
    {
        return new static($value, null, null);
    }

    /**
     * Creates a begins pattern.
     * (pattern=value*)
     *
     * @param string $value
     *
     * @return static
     */
    public static function begins($value)
    {
        return new static($value, null, '*');
    }

    /**
     * Creates an ends pattern.
     * (pattern=*value)
     *
     * @param string $value
     *
     * @return static
     */
    public static function ends($value)
    {
        return new static($value, '*', null);
    }

    /**
     * Creates a contains pattern.
     * (attr=*value*)
     *
     * @param string $value
     *
     * @return static
     */
    public static function contains($value)
    {
        return new static($value, '*', '*');
    }

    /**
     * Implement magic method
     *
     * @return string
     */
    public function __toString()
    {
        return $this->pattern;
    }
}
