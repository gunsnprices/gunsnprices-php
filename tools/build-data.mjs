#!/usr/bin/env node
// Regenerates data/*.json from the JavaScript package (gunsnprices on npm), so the PHP port uses the same data.
//
//   node tools/build-data.mjs [path-to-gunsnprices-js]
//
// The path defaults to ../library/js (the monorepo checkout), then node_modules/gunsnprices.
// Output is deterministic: running it twice on the same JS package gives byte-identical files.
import { existsSync, readFileSync, writeFileSync } from "node:fs";
import { dirname, join, resolve } from "node:path";
import { fileURLToPath, pathToFileURL } from "node:url";

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), "..");
const candidates = process.argv[2] ? [resolve(process.argv[2])] : [resolve(ROOT, "../library/js"), join(ROOT, "node_modules/gunsnprices")];
const JS = candidates.find((p) => existsSync(join(p, "src/data/models.js")));
if (!JS) {
  console.error("gunsnprices JS package not found in: " + candidates.join(", "));
  process.exit(1);
}
const pkg = JSON.parse(readFileSync(join(JS, "package.json"), "utf8"));
const load = async (name) => (await import(pathToFileURL(join(JS, "src/data", name + ".js")).href)).default;

// Characters whose JavaScript toLowerCase() differs from the character itself (outside ASCII).
// PHP's strtolower() only knows ASCII and mb_strtolower() depends on the mbstring version, so the PHP port uses this table.
function lowercaseTable() {
  const out = {};
  for (let cp = 0x80; cp <= 0x10ffff; cp++) {
    if (cp >= 0xd800 && cp <= 0xdfff) continue;
    const ch = String.fromCodePoint(cp);
    const lo = ch.toLowerCase();
    if (lo !== ch) out[ch] = lo;
  }
  return out;
}

const files = {
  "models.json": await load("models"),
  "calibers.json": await load("calibers"),
  "entities.json": await load("entities"),
  "lowercase.json": lowercaseTable(),
  "source.json": {
    package: pkg.name,
    version: pkg.version,
    unicode: process.versions.unicode,
    license: "Data: CC BY 4.0, GunsNPrices (gunsnprices.com). See LICENSE-DATA.",
  },
};
for (const [name, obj] of Object.entries(files)) {
  const text = JSON.stringify(obj) + "\n";
  writeFileSync(join(ROOT, "data", name), text);
  console.log(`data/${name}  ${Math.round(Buffer.byteLength(text) / 1024)} KB`);
}
console.log(`from ${pkg.name} ${pkg.version} (${JS})`);
