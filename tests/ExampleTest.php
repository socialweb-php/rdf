<?php

declare(strict_types=1);

namespace Ramsey\Test\Rdf;

use Ramsey\Rdf\Example;

class ExampleTest extends TestCase
{
    public function testGreet(): void
    {
        $example = new Example();

        $this->assertSame('Hello, Friends!', $example->greet('Friends'));
    }
}
