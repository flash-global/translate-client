<?php

namespace Tests\Fei\Service\Translate\Client\Utils;

use Codeception\Test\Unit;
use Fei\Service\Translate\Client\Utils\ArrayCollection;
use Fei\Service\Translate\Client\Utils\Pattern;

/**
 * Class ArrayCollectionTest
 *
 * @package Tests\Fei\Service\Translate\Client
 */
class ArrayCollectionTest extends Unit
{
    public function testToStringMagicMethod()
    {
        $collection = new ArrayCollection([new class() {
            public function __toString()
            {
                return 'string-from-class';
            }
        }]);

        $this->assertEquals('string-from-class', (string)$collection);
    }
}
