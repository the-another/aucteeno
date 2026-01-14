#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

// Get the new version from package.json
const packageJson = require('../package.json');
const newVersion = packageJson.version;

console.log(`Updating version to ${newVersion}...`);

// Update aucteeno.php
const pluginFile = path.join(__dirname, '../aucteeno.php');
let pluginContent = fs.readFileSync(pluginFile, 'utf8');

// Update Version in header comment
pluginContent = pluginContent.replace(
  /(\* Version:\s+)[\d.]+/,
  `$1${newVersion}`
);

// Update AUCTEENO_VERSION constant
pluginContent = pluginContent.replace(
  /(define\(\s*'AUCTEENO_VERSION',\s*')[\d.]+('\s*\);)/,
  `$1${newVersion}$2`
);

fs.writeFileSync(pluginFile, pluginContent, 'utf8');
console.log('✓ Updated aucteeno.php');

// Update readme.txt
const readmeFile = path.join(__dirname, '../readme.txt');
let readmeContent = fs.readFileSync(readmeFile, 'utf8');

// Update Stable tag
readmeContent = readmeContent.replace(
  /(Stable tag:\s+)[\d.]+/,
  `$1${newVersion}`
);

// Add changelog entry
const today = new Date().toISOString().split('T')[0];
const changelogEntry = `= ${newVersion} - ${today} =\n* Version bump\n\n`;

// Find the changelog section and add the new entry
readmeContent = readmeContent.replace(
  /(== Changelog ==\s*\n)/,
  `$1\n${changelogEntry}`
);

fs.writeFileSync(readmeFile, readmeContent, 'utf8');
console.log('✓ Updated readme.txt');

console.log(`\nVersion ${newVersion} update complete!`);
