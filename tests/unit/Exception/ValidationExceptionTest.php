<?php

namespace Tests\Fei\Service\Translate\Client\Exception;

use Codeception\Test\Unit;
use Fei\Service\Translate\Client\Exception\ValidationException;
use Fei\Service\Translate\Client\Utils\ArrayCollection;
use Fei\Service\Translate\Client\Utils\Pattern;

/**
 * Class ValidationExceptionTest
 *
 * @package Tests\Fei\Service\Translate\Client
 */
class ValidationExceptionTest extends Unit
{
    public function testErrorsAccessors()
    {
        $errors = ['err1', 'err2'];
        $validationException = new ValidationException();
        $validationException->setErrors($errors);

        $this->assertEquals($errors, $validationException->getErrors());
        $this->assertAttributeEquals($validationException->getErrors(), 'errors', $validationException);
    }
}
