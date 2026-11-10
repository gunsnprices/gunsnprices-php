<?php

namespace GunsNPrices\Tests;

use GunsNPrices\Parser;
use PHPUnit\Framework\TestCase;

/** The JavaScript package's test/api.test.js, plus PHP-specific input handling and the README examples. */
final class ApiTest extends TestCase
{
    public function testIdentifyModel(): void
    {
        $cases = [
            ['Glock 19 Gen 5 MOS 9mm 4.02in 15rd Pistol', [], 'Glock 19 Gen 5 MOS'],
            ['G19 Gen5 9mm 15rd FS', ['brand' => 'Glock'], 'Glock 19 Gen 5'],
            ['Glock 43X 9mm 3.41in 10rd Black', [], 'Glock 43X'],
            ['GLOCK 9MM PISTOL 15RD', ['brand' => 'Glock', 'mpn' => 'PA195S203'], 'Glock 19 Gen 5'],
            ['Sig Sauer P365 XL 9mm 3.7in 12rd Pistol', [], 'Sig Sauer P365 XL'],
            ['Ruger GP100 .357 Mag 4.2in 6rd Revolver', [], 'Ruger GP100'],
        ];
        foreach ($cases as list($title, $opts, $model)) {
            $m = Parser::identifyModel($title, $opts);
            $this->assertNotNull($m, $title);
            $this->assertSame($model, $m['model'], $title);
        }
        $this->assertSame('mpn', Parser::identifyModel('GLOCK 9MM PISTOL 15RD', ['brand' => 'Glock', 'mpn' => 'PA195S203'])['source']);
    }

    public function testIdentifyModelRejectsAirgunsAccessoriesAndUnknownTitles(): void
    {
        foreach (['Umarex Glock 19 CO2 BB Pistol .177', 'Hornady 9mm 115gr FTX Critical Defense 25rd', 'Glock 19 holster',
            'Sig P365 XL 12rd Magazine 9mm', 'Magpul PMAG for Glock 19', '', null] as $t) {
            $this->assertNull(Parser::identifyModel($t), (string) $t);
        }
        $this->assertSame('Glock 19 Gen 5', Parser::identifyModel('Glock 19 Gen 5 9mm 15rd w/ 3 magazines')['model']);
    }

    public function testFindCalibers(): void
    {
        $cases = [
            ['Ruger GP100 .357 Mag / .38 Special 4.2in Revolver', ['.357 Mag', '.38 S&W Special']],
            ['Federal American Eagle 9mm Luger 115gr FMJ 50rd', ['9×19mm Parabellum']],
            ['Hornady 6.5 Creedmoor 143gr ELD-X', ['6.5mm Creedmoor']],
            ['PSA AR-15 300 Blackout 16in Rifle', ['.300 AAC Blackout']],
            ['Kent 12G Fasteel 3in BBB', ['12 Gauge']],
            ['Beretta 686 O/U 12/28 BL/WD 3"', ['12 Gauge']],
            ['Winchester 308 150gr PowerMax Bonded 20rds', ['.308 Win']],
            ['Remington 7mm RM 26" Bolt Rifle', ['7mm Remington Magnum']],
            ['Model 1911 Range Officer Parkerized', []],
            ['Umarex 12g CO2 Cartridges 15 pack', []],
        ];
        foreach ($cases as list($t, $names)) {
            $this->assertSame($names, array_column(Parser::findCalibers($t), 'name'), $t);
        }
        $this->assertSame('357mag', Parser::findCaliber('Ruger GP100 .357 Mag / .38 Special')['id']);
        $this->assertNull(Parser::findCaliber('Model 1911 Range Officer'));
    }

    public function testNormalizeCaliberAndLists(): void
    {
        $this->assertSame('9x19mmparabellum', Parser::normalizeCaliber('9mm Luger')['id']);
        $this->assertSame('12 Gauge', Parser::normalizeCaliber('12 GA')['name']);
        $this->assertNull(Parser::normalizeCaliber('nothing here'));
        $this->assertCount(50, Parser::listBrands());
        $cals = Parser::listCalibers();
        $this->assertGreaterThan(250, count($cals));
        $this->assertCount(count($cals), array_unique(array_column($cals, 'id')));
        $names = array_column($cals, 'name');
        $sorted = $names;
        sort($sorted, SORT_STRING);
        $this->assertSame($sorted, $names);
        $this->assertSame(['id', 'name', 'type', 'url'], array_keys($cals[0]));
    }

    public function testNumbers(): void
    {
        $this->assertSame(15, Parser::magazineCapacity('Glock 19 Gen5 9mm 4.02in 15+1'));
        $this->assertSame(15, Parser::magazineCapacity('Kimber CDS9 9mm 3.18in 13rd/15rd Black'));
        $this->assertNull(Parser::magazineCapacity('Ruger 10/22 Carbine 22LR'));
        $this->assertSame(28.0, Parser::barrelLength('Beretta A300 Ultima 12 GA 3" Chamber 28" Barrel'));
        $this->assertSame(20.0, Parser::barrelLength('Mossberg 590 Persuader 12/20 BL/SY 3"'));
        $this->assertSame(4.25, Parser::barrelLength('Colt Python 4 1/4" .357'));
        $this->assertSame(8.0, Parser::barrelLength('Ruger 10/22 SBR 22LR 8in 15rd'));
        $this->assertSame(50, Parser::roundsPerBox('Federal American Eagle 9mm 115gr FMJ, 50/Box'));
        $this->assertSame(1000, Parser::roundsPerBox('Prvi Partizan 5.56x45mm M193 55gr 1,000 Rounds'));
        $this->assertSame(20, Parser::roundsPerBox('Barnaul 7.62x39 123gr FMJ 20/25'));
        $this->assertSame(0.3798, Parser::costPerRound(18.99, 50));
        $this->assertSame(0.5312, Parser::costPerRound(425, 800)); // exact tie 0.53125: half to even, like Python
        $this->assertSame(0.0938, Parser::costPerRound(3, 32)); // exact tie 0.09375
        $this->assertSame(0.0312, Parser::costPerRound(1, 32)); // exact tie 0.03125
        $this->assertNull(Parser::costPerRound(0, 50));
        $this->assertNull(Parser::costPerRound(18.99, null));
        $this->assertSame(0.3798, Parser::costPerRound('18.99', 50));
        $this->assertTrue(Parser::looksLikeAmmo('Hornady 9mm 115gr FTX Critical Defense 25rd'));
        $this->assertFalse(Parser::looksLikeAmmo('Glock 19 Gen 5 9mm 15rd Pistol'));
    }

    public function testParseTitle(): void
    {
        $gun = Parser::parseTitle('Glock 19 Gen5 MOS 9mm 4.02in 15+1 Black');
        $this->assertSame(
            ['title', 'kind', 'brand', 'model', 'caliber', 'calibers', 'capacity', 'barrelLength', 'rounds', 'costPerRound'],
            array_keys($gun)
        );
        $this->assertSame('firearm', $gun['kind']);
        $this->assertSame('Glock 19 Gen 5 MOS', $gun['model']['model']);
        $this->assertSame(['brand', 'family', 'model', 'generation', 'configs', 'features', 'source'], array_keys($gun['model']));
        $this->assertSame([15, 4.02, null], [$gun['capacity'], $gun['barrelLength'], $gun['rounds']]);
        $ammo = Parser::parseTitle('Federal American Eagle 9mm Luger 115gr FMJ, 50/Box', ['price' => 18.99]);
        $this->assertSame(['ammo', 50, 0.3798, '9x19mmparabellum'], [$ammo['kind'], $ammo['rounds'], $ammo['costPerRound'], $ammo['caliber']['id']]);
        $this->assertNull($ammo['model']);
        $forced = Parser::parseTitle('Federal American Eagle 9mm Luger 115gr FMJ, 50/Box', ['kind' => 'firearm']);
        $this->assertSame('firearm', $forced['kind']);
        $this->assertNull($forced['rounds']);
        $this->assertSame('Acme', Parser::parseTitle('Acme Thing 9mm', ['brand' => 'Acme'])['brand']);
    }

    public function testInputHandling(): void
    {
        $empty = Parser::parseTitle(null);
        $this->assertSame(['', 'firearm', '', null, null, []], [$empty['title'], $empty['kind'], $empty['brand'], $empty['model'], $empty['caliber'], $empty['calibers']]);
        // invalid UTF-8 is replaced with U+FFFD instead of failing inside PCRE
        $bad = Parser::parseTitle("Glock 19 Gen 5 9mm \xFF\xFE 15rd");
        $this->assertSame('Glock 19 Gen 5', $bad['model']['model']);
        $this->assertSame("Glock 19 Gen 5 9mm \u{FFFD}\u{FFFD} 15rd", $bad['title']);
        $this->assertNotFalse(json_encode($bad));
        // HTML entities as they come in product feeds
        $this->assertSame(26.0, Parser::barrelLength('Barrett MRAD Tungsten .300 PRC 26&quot; Barrel 10-Rounds'));
        $this->assertSame(10, Parser::magazineCapacity('MRAD 300 Norma Mag 26&quot; 10&#x2B;1 Tungsten'));
        // JavaScript (non-unicode) regex semantics: U+017F is not an "s" (PCRE's /iu would say it is);
        // U+212A lowercases to "k"; U+00A0 is whitespace. Expected values are the JavaScript package's answers.
        $this->assertSame(['3006springfield'], array_column(Parser::findCalibers("Sellier & Bellot .30-06\u{17F} Spfld"), 'id'));
        $this->assertSame(['6-5mmcreedmoor'], array_column(Parser::findCalibers("Tikka 6.5mm Creedmoor\u{17F} 22in"), 'id'));
        $this->assertSame(['6-5mmcreedmoor'], array_column(Parser::findCalibers("Tikka 6.5mm Creedmoor\u{A0}22in"), 'id'));
        $this->assertNull(Parser::normalizeCaliber("12 Gauge\u{212A}"));
    }

    public function testReadmeExamples(): void
    {
        $info = Parser::parseTitle('Glock 19 Gen 5 MOS 9mm 4.02in 15rd Pistol PA195S203MOS');
        $this->assertSame(['Glock 19 Gen 5 MOS', '9×19mm Parabellum', 15, 4.02], [$info['model']['model'], $info['caliber']['name'], $info['capacity'], $info['barrelLength']]);

        $info = Parser::parseTitle('Federal American Eagle 5.56x45mm 55gr FMJ 1000 Rounds', ['price' => 449.99]);
        $this->assertSame(['ammo', '5.56×45mm NATO', 1000, 0.45], [$info['kind'], $info['caliber']['name'], $info['rounds'], $info['costPerRound']]);

        $this->assertSame(
            ['brand' => 'Glock', 'family' => 'Glock 43', 'model' => 'Glock 43', 'generation' => '', 'configs' => [], 'features' => [], 'source' => 'mpn'],
            Parser::identifyModel('GLOCK 9MM PISTOL 15RD', ['brand' => 'Glock', 'mpn' => 'UA4350201'])
        );
        $m = Parser::identifyModel('Sig Sauer P365 XL 9mm 3.7" 12+1 Optic Ready');
        $this->assertSame(['Sig Sauer P365', 'Sig Sauer P365 XL'], [$m['family'], $m['model']]);

        $this->assertSame(['.44 Magnum', '.44 S&W Special'], array_column(Parser::findCalibers('Henry Big Boy .44 Mag/.44 Spl 20in'), 'name'));
        $this->assertSame(
            ['id' => '308win', 'name' => '.308 Win', 'type' => 'rifle', 'url' => 'https://gunsnprices.com/ammo/rifle-ammo/308-win'],
            Parser::normalizeCaliber('308 WIN (7.62X51 NATO)')
        );

        $this->assertSame(17, Parser::magazineCapacity('Springfield Hellcat Pro OSP 9mm 3.7in 15rd/17rd'));
        $this->assertSame(20.0, Parser::barrelLength('Mossberg 590 Persuader 12/20 BL/SY 3"'));
        $this->assertSame(20, Parser::roundsPerBox('Winchester 308 150gr PowerMax Bonded 20rds'));
        $this->assertSame(1.5995, Parser::costPerRound(31.99, 20));
    }

    public function testVersion(): void
    {
        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', Parser::VERSION);
        $composer = json_decode((string) file_get_contents(__DIR__ . '/../composer.json'), true);
        $this->assertArrayNotHasKey('version', $composer); // Packagist takes the version from the git tag
        $this->assertStringContainsString('## ' . Parser::VERSION . ' ', (string) file_get_contents(__DIR__ . '/../CHANGELOG.md'));
    }
}
