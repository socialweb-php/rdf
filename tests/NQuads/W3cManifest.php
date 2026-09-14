<?php

declare(strict_types=1);

namespace SocialWeb\Test\Rdf\NQuads;

use RuntimeException;

use function file_get_contents;
use function preg_match_all;
use function sprintf;

use const PREG_SET_ORDER;

/**
 * Reads the W3C N-Quads syntax test manifest vendored under
 * tests/fixtures/n-quads
 *
 * The manifest is Turtle, which this package cannot parse, but its layout is
 * regular: every entry starts with `<#name> a rdft:Type ;` and carries an
 * `mf:action <file>` line before the next entry. A regular expression is
 * enough to read it.
 */
final class W3cManifest
{
    public const string FIXTURES = __DIR__ . '/../fixtures/n-quads';

    public const string POSITIVE = 'TestNQuadsPositiveSyntax';

    public const string NEGATIVE = 'TestNQuadsNegativeSyntax';

    /**
     * Yields the name and action file path of every entry of the given type
     *
     * @param self::POSITIVE | self::NEGATIVE $type
     *
     * @return iterable<string, array{string}>
     */
    public static function entries(string $type): iterable
    {
        $manifest = self::read(self::FIXTURES . '/manifest.ttl');
        $pattern = '/<#([^>]+)> a rdft:' . $type . ' ;.*?mf:action\s+<([^>]+)>/s';

        preg_match_all($pattern, $manifest, $matches, PREG_SET_ORDER);

        foreach ($matches as [, $name, $action]) {
            yield $name => [self::FIXTURES . '/' . $action];
        }
    }

    /**
     * Reads a fixture file, failing loudly if it is missing
     */
    public static function read(string $path): string
    {
        $contents = @file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to read fixture %s', $path));
        }

        return $contents;
    }
}
