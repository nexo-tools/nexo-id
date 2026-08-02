// Extracts every __('…') / trans_choice('…') English source string from the
// codebase and builds lang/es.json and lang/pt.json from the maps kept in
// scripts/translations/. Fails loudly (exit 1) if a string has no translation,
// so the guardian test and CI --check keep the lang files in sync with the code.
// Usage: node scripts/generate-translations.mjs [--check]
import { readFileSync, readdirSync, statSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';

const ROOTS = ['app', 'resources/views'];
const LOCALES = ['es', 'pt'];

function phpFiles(dir) {
    let entries;
    try {
        entries = readdirSync(dir);
    } catch {
        return [];
    }
    return entries.flatMap((entry) => {
        const full = join(dir, entry);
        return statSync(full).isDirectory() ? phpFiles(full) : full.endsWith('.php') ? [full] : [];
    });
}

const keys = new Set();
const pattern = /(?:__|trans_choice)\(\s*'((?:[^'\\]|\\.)*)'/g;

for (const root of ROOTS) {
    for (const file of phpFiles(root)) {
        for (const match of readFileSync(file, 'utf8').matchAll(pattern)) {
            const key = match[1].replace(/\\'/g, "'");
            // Skip lang-file lookups like nexo.foo.bar — only literal texts.
            if (!/^[a-z0-9_.-]+$/.test(key)) keys.add(key);
        }
    }
}

// The consent screen translates the OAuth scope descriptions at render time
// (`__($scope->description)` in auth/oauth/authorize.blade.php) — a variable,
// so the literal scanner above cannot see them. They are collected from their
// real source instead: config/openid.php's tokens_can block. The config file
// itself stays untranslated (it is the OIDC surface).
const openid = readFileSync('config/openid.php', 'utf8');
const tokensCan = openid.match(/'tokens_can'\s*=>\s*\[([\s\S]*?)\]/);
if (tokensCan) {
    for (const match of tokensCan[1].matchAll(/=>\s*'((?:[^'\\]|\\.)*)'/g)) {
        keys.add(match[1].replace(/\\'/g, "'"));
    }
}

const sorted = [...keys].sort((a, b) => a.localeCompare(b, 'en'));
let failed = false;

for (const locale of LOCALES) {
    const map = JSON.parse(readFileSync(`scripts/translations/${locale}.json`, 'utf8'));
    const missing = sorted.filter((key) => !(key in map));
    const stale = Object.keys(map).filter((key) => !keys.has(key));

    if (missing.length > 0) {
        failed = true;
        console.error(`\n[${locale}] Missing translation for ${missing.length} string(s):`);
        missing.forEach((key) => console.error(`  - ${key}`));
    }

    if (stale.length > 0) {
        console.warn(`\n[${locale}] ${stale.length} unused string(s) in the map (clean up when convenient):`);
        stale.forEach((key) => console.warn(`  - ${key}`));
    }

    if (!process.argv.includes('--check') && missing.length === 0) {
        const output = Object.fromEntries(sorted.map((key) => [key, map[key]]));
        writeFileSync(`lang/${locale}.json`, JSON.stringify(output, null, 4) + '\n');
        console.log(`[${locale}] lang/${locale}.json generated with ${sorted.length} string(s).`);
    }
}

process.exit(failed ? 1 : 0);
