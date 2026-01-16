#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

// Load package.json to get current version
const packageJsonPath = path.join(__dirname, '../package.json');
const packageJson = require(packageJsonPath);
const version = packageJson.version;

// Define paths
const rootDir = path.join(__dirname, '/../');
const buildDir = path.join(rootDir, 'build');
const sourceZip = path.join(rootDir, 'aucteeno-nexus.zip');
const targetZip = path.join(buildDir, `aucteeno-nexus-${version}.zip`);

// Check if source zip exists
if (!fs.existsSync(sourceZip)) {
  console.error(`Error: ${sourceZip} not found`);
  process.exit(1);
}

// Rename the zip file
fs.renameSync(sourceZip, targetZip);
console.log(`✓ Created ${path.basename(targetZip)} in build directory`);
