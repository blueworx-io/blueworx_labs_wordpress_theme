#!/usr/bin/env node
// Local mirror of the foundation header<->package.json version-sync guardrail.
import { readFileSync } from 'node:fs';

let header;
try {
  header = readFileSync('style.css', 'utf8');
} catch (err) {
  console.error(`version:check FAILED — cannot read the theme's style.css: ${err.message}`);
  process.exit(1);
}
const headerMatch = header.match(/^\s*\*?\s*Version:\s*(.+)$/im);
const headerVersion = headerMatch ? headerMatch[1].trim() : null;
const pkgVersion = JSON.parse(readFileSync('package.json', 'utf8')).version;

if (!headerVersion) {
  console.error('version:check FAILED — no "Version:" header found in style.css.');
  process.exit(1);
}
if (headerVersion !== pkgVersion) {
  console.error(`version:check FAILED — style.css ${headerVersion} !== package.json ${pkgVersion}.`);
  process.exit(1);
}

// The version constant client plugins read. It is set in code rather than read
// from style.css, so a bump that misses it ships a theme that still reports the
// version before.
const bootstrap = readFileSync('includes/bootstrap.php', 'utf8');
const constMatch = bootstrap.match(/define\(\s*'BLUEWORX_LABS_VERSION',\s*'([^']+)'\s*\)/);
const constVersion = constMatch ? constMatch[1] : null;

if (constVersion !== pkgVersion) {
  console.error(`version:check FAILED — BLUEWORX_LABS_VERSION in includes/bootstrap.php ${constVersion} !== package.json ${pkgVersion}.`);
  process.exit(1);
}

// readme.txt's stable tag, which the two above do not imply. Nothing reads it
// until the theme is served from an update endpoint, and then it is the ONLY
// number that decides which release a site is offered — a stale tag offers an
// old version, or none at all. Because nothing breaks in the meantime it drifts
// silently: it sat at 1.61.5 through fourteen releases. Checked here so a bump
// that forgets it fails at the bump rather than at the deploy.
const readme = readFileSync('readme.txt', 'utf8');
const stableMatch = readme.match(/^Stable tag:\s*(.+)$/im);
const stableTag = stableMatch ? stableMatch[1].trim() : null;

if (!stableTag) {
  console.error('version:check FAILED — no "Stable tag:" line found in readme.txt.');
  process.exit(1);
}
if (stableTag !== pkgVersion) {
  console.error(`version:check FAILED — readme.txt stable tag ${stableTag} !== package.json ${pkgVersion}.`);
  process.exit(1);
}

console.log(`version:check OK — style.css, the version constant, package.json and readme.txt agree (${headerVersion}).`);
