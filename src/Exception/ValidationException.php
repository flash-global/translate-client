<?php

namespace Fei\Service\Translate\Client\Exception;

/**
 * Class ValidationException
 *
 * @package Fei\Service\Translate\Client\Exception
 */
class ValidationException extends \LogicException
{
    /**
     * @var array
     */
    protected $errors = [];

    /**
     * Get Errors
     *
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Set Errors
     *
     * @param array $errors
     *
     * @return $this
     */
    public function setErrors(array $errors = [])
    {
        $this->errors = $errors;
        return $this;
    }
}
