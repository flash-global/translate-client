<?php

namespace Tests\Fei\Service\Translate\Client\Utils;

use Codeception\Test\Unit;
use Fei\Service\Translate\Client\Utils\Pattern;

/**
 * Class PatternTest
 *
 * @package Tests\Fei\Service\Translate\Client
 */
class PatternTest extends Unit
{
    public function testEquals()
    {
        $this->assertEquals('value', Pattern::equals('value'));
    }

    public function testBegins()
    {
        $this->assertEquals('value*', Pattern::begins('value'));
    }

    public function testEnds()
    {
        $this->assertEquals('*value', Pattern::ends('value'));
    }

    public function testContains()
    {
        $this->assertEquals('*value*', Pattern::contains('value'));
    }
}
