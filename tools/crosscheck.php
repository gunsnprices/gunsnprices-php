<?php
// Compares the PHP port with the answers of the JavaScript package (see tools/crosscheck-js.mjs).
//
//   php tools/crosscheck.php js.jsonl [--show=20] [--exact]

require __DIR__ . '/../vendor/autoload.php';

use GunsNPrices\Internal\Rx;
use GunsNPrices\Parser;

Rx::$strict = true;
$file = isset($argv[1]) ? $argv[1] : '';
if ($file === '' || !is_file($file)) {
    fwrite(STDERR, "usage: php tools/crosscheck.php js.jsonl [--show=20] [--exact]\n");
    exit(2);
}
$show = 20;
foreach ($argv as $a) {
    if (strpos($a, '--show=') === 0) {
        $show = (int) substr($a, 7);
    }
    if ($a === '--exact') {
        // only the fully translated patterns, also on ASCII titles
        Rx::$exactOnly = true;
    }
}

/** Deep equality where 16 (JSON from JavaScript) equals 16.0 (PHP float). */
function same($a, $b): bool
{
    if ((is_int($a) || is_float($a)) && (is_int($b) || is_float($b))) {
        return (float) $a === (float) $b;
    }
    if (is_array($a) && is_array($b)) {
        if (array_keys($a) !== array_keys($b)) {
            return false;
        }
        foreach ($a as $k => $v) {
            if (!same($v, $b[$k])) {
                return false;
            }
        }
        return true;
    }
    return $a === $b;
}

$n = 0;
$diffTitles = 0;
$byField = [];
$time = 0.0;
$fh = fopen($file, 'r');
while (($line = fgets($fh)) !== false) {
    $line = trim($line);
    if ($line === '') {
        continue;
    }
    $js = json_decode($line, true);
    $title = $js['title'];
    $opts = $js['opts'];
    $t0 = microtime(true);
    $parse = Parser::parseTitle($title, $opts);
    $time += microtime(true) - $t0;
    $php = [
        'parse' => $parse,
        'model' => Parser::identifyModel($title, $opts),
        'capacity' => Parser::magazineCapacity($title),
        'barrel' => Parser::barrelLength($title),
        'rounds' => Parser::roundsPerBox($title),
        'ammo' => Parser::looksLikeAmmo($title),
        'caliberField' => $js['caliberField'] === null ? null : [$js['caliberField'][0], Parser::normalizeCaliber($js['caliberField'][0])],
    ];
    $n++;
    $bad = [];
    foreach ($php as $k => $v) {
        if (!same($v, $js[$k])) {
            $bad[] = $k;
            $byField[$k] = (isset($byField[$k]) ? $byField[$k] : 0) + 1;
        }
    }
    if ($bad) {
        $diffTitles++;
        if ($diffTitles <= $show) {
            echo $title, "\n";
            foreach ($bad as $k) {
                echo "  $k\n    js  ", json_encode($js[$k], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    "\n    php ", json_encode($php[$k], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
            }
        }
    }
}
printf("%d titles, %d with differences %s\n", $n, $diffTitles, json_encode($byField));
printf("parseTitle: %.3f ms per title (PHP %s, PCRE %s, JIT %s%s)\n", $time * 1000 / max(1, $n), PHP_VERSION, PCRE_VERSION, ini_get("pcre.jit"), Rx::$exactOnly ? ", exact patterns only" : "");
exit($diffTitles ? 1 : 0);
