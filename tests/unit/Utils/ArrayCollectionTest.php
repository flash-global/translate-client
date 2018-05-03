<?php

namespace Tests\Fei\Service\Translate\Client\Utils;

use Codeception\Test\Unit;
use Fei\Service\Translate\Client\Utils\ArrayCollection;
use Fei\Service\Translate\Client\Utils\Pattern;
use stdClass;

/**
 * Class ArrayCollectionTest
 *
 * @package Tests\Fei\Service\Translate\Client
 */
class ArrayCollectionTest extends Unit
{
    public function testToStringMagicMethod()
    {
        $obj = $this->getMockBuilder(stdClass::class)
            ->setMethods(['__toString'])
            ->getMock();

        $obj->expects($this->once())
            ->method('__toString')
            ->willReturn('test');

        $collection = new ArrayCollection([$obj]);

        $this->assertEquals('test', (string)$collection);
    }
}
