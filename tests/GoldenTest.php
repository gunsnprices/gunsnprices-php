<?php

namespace GunsNPrices\Tests;

use GunsNPrices\Internal\Rx;
use GunsNPrices\Parser;
use PHPUnit\Framework\TestCase;

/**
 * The same 1,150 real store titles and answers as the Python package (tests/golden.jsonl) and the JavaScript
 * package (test/golden.jsonl): parseTitle() must give exactly the recorded result. Compared as JSON, which keeps
 * the types visible (26.0 vs 26, null vs "", [] vs null).
 */
final class GoldenTest extends TestCase
{
    public function cases(): array
    {
        $out = [];
        foreach (file(__DIR__ . '/golden.jsonl', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $i => $line) {
            $case = json_decode($line, true);
            $out[sprintf('%04d %s', $i + 1, substr($case['args']['title'], 0, 60))] = [$case['args'], $case['expected']];
        }
        return $out;
    }

    public function testFileHasAllCases(): void
    {
        $this->assertCount(1150, $this->cases());
    }

    /**
     * @dataProvider cases
     */
    public function testGolden(array $args, array $expected): void
    {
        $this->assertSame(json_encode($expected), json_encode(self::compact($args)));
    }

    /** ASCII titles normally run on PCRE's own \b, \d, \w, \s; the fully translated patterns must agree too. */
    public function testGoldenWithExactPatternsOnly(): void
    {
        Rx::$exactOnly = true;
        try {
            $failures = [];
            foreach ($this->cases() as $name => list($args, $expected)) {
                if (json_encode($expected) !== json_encode(self::compact($args))) {
                    $failures[] = $name;
                }
            }
            $this->assertSame([], $failures);
        } finally {
            Rx::$exactOnly = false;
        }
    }

    private static function compact(array $args): array
    {
        $title = $args['title'];
        unset($args['title']);
        $info = Parser::parseTitle($title, $args);
        $m = $info['model'];
        return [
            'kind' => $info['kind'],
            'brand' => $info['brand'],
            'model' => $m ? [$m['family'], $m['model'], $m['generation'], $m['configs'], $m['features'], $m['source']] : null,
            'calibers' => array_column($info['calibers'], 'id'),
            'capacity' => $info['capacity'],
            'barrel_length' => $info['barrelLength'],
            'rounds' => $info['rounds'],
            'cost_per_round' => $info['costPerRound'],
        ];
    }
}
