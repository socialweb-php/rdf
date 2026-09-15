<?php

declare(strict_types=1);

namespace SocialWeb\Test\Rdf\Canonicalization;

use RuntimeException;

use function file_get_contents;
use function is_array;
use function is_string;
use function json_decode;
use function ltrim;
use function sprintf;
use function strtolower;

use const JSON_THROW_ON_ERROR;

/**
 * Reads the W3C RDF Dataset Canonicalization test manifest vendored under
 * tests/fixtures/rdf-canon
 *
 * The manifest is JSON-LD, which this package cannot process, but its
 * structure is fixed, so it is read as plain JSON.
 */
final class W3cManifest
{
    public const string FIXTURES = __DIR__ . '/../fixtures/rdf-canon';

    public const string EVAL = 'rdfc:RDFC10EvalTest';

    public const string MAP = 'rdfc:RDFC10MapTest';

    public const string NEGATIVE = 'rdfc:RDFC10NegativeEvalTest';

    /**
     * Yields, for every entry of the given type, its identifier and the
     * action file path, the result file path (null for the negative test),
     * the hash algorithm name as PHP's hash() knows it, and the work factor
     * to run with
     *
     * @param self::EVAL | self::MAP | self::NEGATIVE $type
     *
     * @return iterable<string, array{string, string | null, string, int}>
     */
    public static function entries(string $type): iterable
    {
        $manifest = json_decode(self::read(self::FIXTURES . '/manifest.jsonld'), true, 512, JSON_THROW_ON_ERROR);
        $entries = is_array($manifest) ? $manifest['entries'] ?? null : null;

        if (!is_array($entries)) {
            throw new RuntimeException('The manifest has no list of entries');
        }

        foreach ($entries as $entry) {
            if (!is_array($entry) || ($entry['type'] ?? null) !== $type) {
                continue;
            }

            $result = isset($entry['result']) ? self::FIXTURES . '/' . self::string($entry, 'result') : null;

            yield ltrim(self::string($entry, 'id'), '#') => [
                self::FIXTURES . '/' . self::string($entry, 'action'),
                $result,
                strtolower(self::string($entry, 'hashAlgorithm', 'SHA256')),
                self::workFactor(self::string($entry, 'computationalComplexity')),
            ];
        }
    }

    /**
     * Maps an entry's computational complexity to the work factor the
     * reference implementation's test harness runs it with
     */
    public static function workFactor(string $complexity): int
    {
        return match ($complexity) {
            'low' => 0,
            'medium' => 2,
            'high' => 3,
            default => throw new RuntimeException(sprintf('Unknown computational complexity "%s"', $complexity)),
        };
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

    /**
     * @param array<mixed> $entry
     */
    private static function string(array $entry, string $key, ?string $default = null): string
    {
        $value = $entry[$key] ?? $default;

        if (!is_string($value)) {
            throw new RuntimeException(sprintf('A manifest entry has no string value for "%s"', $key));
        }

        return $value;
    }
}
