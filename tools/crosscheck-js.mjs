#!/usr/bin/env node
// Runs the JavaScript package on store titles and writes its answers as JSON lines, for tools/crosscheck.php.
//
//   node tools/crosscheck-js.mjs <path-to-gunsnprices-js> <titles.tsv>... > js.jsonl
//   php tools/crosscheck.php js.jsonl
//
// A TSV needs a header row with a "name" column (the title); "manufacturer", "mpn", "sku", "instock_min" (price)
// and "caliber" are used when present. Every title goes through parseTitle() and also through identifyModel(),
// the number helpers and looksLikeAmmo() on their own, so firearm rules get checked on ammo titles and vice versa.
import { readFileSync } from "node:fs";
import { join, resolve } from "node:path";
import { pathToFileURL } from "node:url";

const [jsDir, ...tsvs] = process.argv.slice(2);
if (!jsDir || !tsvs.length) {
  console.error("usage: node tools/crosscheck-js.mjs <path-to-gunsnprices-js> <titles.tsv>...");
  process.exit(2);
}
const g = await import(pathToFileURL(join(resolve(jsDir), "src/index.js")).href);
const out = [];
for (const file of tsvs) {
  const [head, ...lines] = readFileSync(file, "utf8").split("\n");
  const cols = head.split("\t");
  for (const line of lines) {
    if (!line) continue;
    const f = Object.fromEntries(line.split("\t").map((v, i) => [cols[i], v]));
    if (!f.name) continue;
    const opts = {};
    if (f.manufacturer) opts.brand = f.manufacturer;
    if (f.mpn) opts.mpn = f.mpn;
    if (f.sku) opts.sku = f.sku;
    if (f.instock_min && f.instock_min !== "0.00") opts.price = parseFloat(f.instock_min);
    const title = f.name;
    out.push(JSON.stringify({
      title, opts,
      parse: g.parseTitle(title, opts),
      model: g.identifyModel(title, opts),
      capacity: g.magazineCapacity(title),
      barrel: g.barrelLength(title),
      rounds: g.roundsPerBox(title),
      ammo: g.looksLikeAmmo(title),
      caliberField: f.caliber ? [f.caliber, g.normalizeCaliber(f.caliber)] : null,
    }));
  }
}
process.stdout.write(out.join("\n") + "\n");
console.error(`${out.length} titles, gunsnprices JS ${g.VERSION}`);
