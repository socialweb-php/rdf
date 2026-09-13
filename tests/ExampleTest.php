<?php

declare(strict_types=1);

namespace SocialWeb\Test\Rdf;

use SocialWeb\Rdf\Example;

class ExampleTest extends TestCase
{
    public function testGreet(): void
    {
        $example = new Example();

        $this->assertSame('Hello, Friends!', $example->greet('Friends'));
    }
}
