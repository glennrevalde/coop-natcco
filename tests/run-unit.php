<?php
/**
 * Runs every unit test case in tests/unit without needing Composer.
 *
 *   php tests/run-unit.php                 (prints the results table)
 *   php tests/run-unit.php --md=FILE       (also writes a Markdown results table)
 *   php tests/run-unit.php UTC07           (runs only files whose name contains UTC07)
 *
 * With PHPUnit installed the same files run with:  phpunit -c tests/phpunit.xml
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}
require __DIR__ . '/bootstrap.php';

$md = null;
$only = null;
foreach (array_slice($argv, 1) as $a) {
    if (strpos($a, '--md=') === 0) {
        $md = substr($a, 5);
    } else {
        $only = $a;
    }
}

function utc_header(string $file): array
{
    $src = (string) file_get_contents($file);
    $h = [];
    foreach (['Test Case ID', 'Module', 'Unit', 'Role', 'Description', 'Expected'] as $k) {
        $h[$k] = preg_match('/\* ' . preg_quote($k, '/') . ':\s*(.+)/', $src, $m) ? trim($m[1]) : '';
    }
    return $h;
}

$files = glob(__DIR__ . '/unit/UTC*Test.php') ?: [];
natsort($files);
$results = [];
$started = microtime(true);
foreach ($files as $file) {
    if ($only !== null && stripos(basename($file), $only) === false) {
        continue;
    }
    $before = get_declared_classes();
    require_once $file;
    $classes = array_filter(array_diff(get_declared_classes(), $before), fn($c) => is_subclass_of($c, PHPUnit\Framework\TestCase::class));
    $h = utc_header($file);
    foreach ($classes as $class) {
        $methods = array_filter(get_class_methods($class), fn($m) => strpos($m, 'test') === 0);
        $failures = [];
        $start = PHPUnit\Framework\TestCase::$assertions;
        foreach ($methods as $method) {
            $t = new $class();
            try {
                $t->runTestMethod($method);
            } catch (Throwable $e) {
                $failures[] = $method . ': ' . get_class($e) . ' - ' . $e->getMessage();
            }
        }
        $n = PHPUnit\Framework\TestCase::$assertions - $start;
        $results[] = [
            'id' => $h['Test Case ID'] ?: basename($file, '.php'), 'module' => $h['Module'], 'unit' => $h['Unit'], 'role' => $h['Role'],
            'description' => $h['Description'], 'expected' => $h['Expected'],
            'actual' => $failures ? implode(' | ', $failures) : 'Behaved as expected: ' . count($methods) . ' test method(s), ' . $n . ' assertion(s) held.',
            'pass' => !$failures && $n > 0,
            'file' => 'tests/unit/' . basename($file),
        ];
    }
}

$pass = count(array_filter($results, fn($r) => $r['pass']));
foreach ($results as $r) {
    printf("%-7s %-4s %s\n", $r['id'], $r['pass'] ? 'PASS' : 'FAIL', $r['description']);
    if (!$r['pass']) {
        echo "        Expected: {$r['expected']}\n        Actual:   {$r['actual']}\n";
    }
}
printf("\n%d of %d unit test cases passed (%d assertions) in %.2fs on PHP %s\n", $pass, count($results), PHPUnit\Framework\TestCase::$assertions,
    microtime(true) - $started, PHP_VERSION);

if ($md) {
    $esc = fn($s) => str_replace(['|', "\n"], ['\\|', ' '], (string) $s);
    $out = "| ID | Module | Unit | Description | Expected result | Actual result | Result |\n|---|---|---|---|---|---|---|\n";
    foreach ($results as $r) {
        $out .= '| ' . implode(' | ', array_map($esc, [$r['id'], $r['module'], $r['unit'], $r['description'], $r['expected'], $r['actual'], $r['pass'] ? 'Pass' : 'Fail'])) . " |\n";
    }
    $out .= "\n" . $pass . ' of ' . count($results) . ' unit test cases passed on PHP ' . PHP_VERSION . ".\n";
    file_put_contents($md, $out);
}
exit($pass === count($results) && $results ? 0 : 1);
