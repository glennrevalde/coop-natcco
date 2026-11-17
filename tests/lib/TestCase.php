<?php
/**
 * Minimal PHPUnit-compatible TestCase.
 *
 * The unit tests extend PHPUnit\Framework\TestCase. When PHPUnit is installed
 * (vendor/bin/phpunit or a phpunit.phar), PHPUnit's own class is used and this
 * file declares nothing. When it is not installed, this class provides the
 * subset of assertions the tests use, so `php tests/run-unit.php` can run the
 * same files with no Composer dependencies.
 */
declare(strict_types=1);

namespace PHPUnit\Framework;

if (!class_exists(TestCase::class)) {

    class AssertionFailedError extends \RuntimeException
    {
    }

    abstract class TestCase
    {
        private ?string $expectedException = null;
        private ?string $expectedMessagePart = null;
        public static int $assertions = 0;

        protected function setUp(): void
        {
        }

        protected function tearDown(): void
        {
        }

        /** Used by tests/run-unit.php. */
        public function runTestMethod(string $method): void
        {
            $this->expectedException = null;
            $this->expectedMessagePart = null;
            $this->setUp();
            try {
                try {
                    $this->$method();
                } catch (AssertionFailedError $e) {
                    throw $e;
                } catch (\Throwable $e) {
                    if ($this->expectedException !== null && $e instanceof $this->expectedException) {
                        self::$assertions++;
                        if ($this->expectedMessagePart !== null && strpos($e->getMessage(), $this->expectedMessagePart) === false) {
                            throw new AssertionFailedError('Exception message "' . $e->getMessage() . '" does not contain "' . $this->expectedMessagePart . '"');
                        }
                        return;
                    }
                    throw $e;
                }
                if ($this->expectedException !== null) {
                    throw new AssertionFailedError('Expected exception ' . $this->expectedException . ' was not thrown');
                }
            } finally {
                $this->tearDown();
            }
        }

        public function expectException(string $class): void
        {
            $this->expectedException = $class;
        }

        public function expectExceptionMessage(string $part): void
        {
            $this->expectedMessagePart = $part;
        }

        private static function show($v): string
        {
            if (is_string($v)) {
                return '"' . (strlen($v) > 200 ? substr($v, 0, 200) . '…' : $v) . '"';
            }
            return str_replace("\n", ' ', var_export($v, true));
        }

        private static function check(bool $ok, string $message, string $default): void
        {
            self::$assertions++;
            if (!$ok) {
                throw new AssertionFailedError($message !== '' ? $message . ': ' . $default : $default);
            }
        }

        public static function assertTrue($c, string $message = ''): void
        {
            self::check($c === true, $message, 'Failed asserting that ' . self::show($c) . ' is true');
        }

        public static function assertFalse($c, string $message = ''): void
        {
            self::check($c === false, $message, 'Failed asserting that ' . self::show($c) . ' is false');
        }

        public static function assertSame($expected, $actual, string $message = ''): void
        {
            self::check($expected === $actual, $message, 'Expected ' . self::show($expected) . ', got ' . self::show($actual));
        }

        public static function assertNotSame($expected, $actual, string $message = ''): void
        {
            self::check($expected !== $actual, $message, 'Expected a value other than ' . self::show($expected));
        }

        public static function assertEquals($expected, $actual, string $message = ''): void
        {
            self::check($expected == $actual, $message, 'Expected ' . self::show($expected) . ', got ' . self::show($actual));
        }

        public static function assertNull($v, string $message = ''): void
        {
            self::check($v === null, $message, 'Expected null, got ' . self::show($v));
        }

        public static function assertNotNull($v, string $message = ''): void
        {
            self::check($v !== null, $message, 'Expected a value, got null');
        }

        public static function assertCount(int $n, $v, string $message = ''): void
        {
            self::check(is_countable($v) && count($v) === $n, $message, 'Expected ' . $n . ' items, got ' . (is_countable($v) ? count($v) : 'non-countable'));
        }

        public static function assertEmpty($v, string $message = ''): void
        {
            self::check(empty($v), $message, 'Expected empty, got ' . self::show($v));
        }

        public static function assertNotEmpty($v, string $message = ''): void
        {
            self::check(!empty($v), $message, 'Expected a non-empty value');
        }

        public static function assertStringContainsString(string $needle, string $hay, string $message = ''): void
        {
            self::check(strpos($hay, $needle) !== false, $message, 'Expected ' . self::show($hay) . ' to contain ' . self::show($needle));
        }

        public static function assertStringNotContainsString(string $needle, string $hay, string $message = ''): void
        {
            self::check(strpos($hay, $needle) === false, $message, 'Expected ' . self::show($hay) . ' not to contain ' . self::show($needle));
        }

        public static function assertStringStartsWith(string $prefix, string $s, string $message = ''): void
        {
            self::check(strpos($s, $prefix) === 0, $message, 'Expected ' . self::show($s) . ' to start with ' . self::show($prefix));
        }

        public static function assertMatchesRegularExpression(string $re, string $s, string $message = ''): void
        {
            self::check(preg_match($re, $s) === 1, $message, 'Expected ' . self::show($s) . ' to match ' . $re);
        }

        public static function assertGreaterThan($limit, $v, string $message = ''): void
        {
            self::check($v > $limit, $message, 'Expected ' . self::show($v) . ' to be greater than ' . self::show($limit));
        }

        public static function assertContains($needle, array $hay, string $message = ''): void
        {
            self::check(in_array($needle, $hay, true), $message, 'Expected array to contain ' . self::show($needle));
        }

        public static function assertNotContains($needle, array $hay, string $message = ''): void
        {
            self::check(!in_array($needle, $hay, true), $message, 'Expected array not to contain ' . self::show($needle));
        }

        public static function assertArrayHasKey($key, array $a, string $message = ''): void
        {
            self::check(array_key_exists($key, $a), $message, 'Expected array to have key ' . self::show($key));
        }

        public static function assertArrayNotHasKey($key, array $a, string $message = ''): void
        {
            self::check(!array_key_exists($key, $a), $message, 'Expected array not to have key ' . self::show($key));
        }
    }
}
