#!/usr/bin/env node

const fs = require('fs');
const path = require('path');

// Load package.json to get current version and package name
const packageJsonPath = path.join(__dirname, '../package.json');
const packageJson = require(packageJsonPath);
const version = packageJson.version;
const packageName = packageJson.name;

// Define paths
const rootDir = path.join(__dirname, '/../');
const buildDir = path.join(rootDir, 'build');
const sourceZip = path.join(rootDir, `${packageName}.zip`);
const versionedZip = path.join(buildDir, `${packageName}-${version}.zip`);
const latestZip = path.join(buildDir, `${packageName}.zip`);

// Check if source zip exists
if (!fs.existsSync(sourceZip)) {
  console.error(`Error: ${sourceZip} not found`);
  process.exit(1);
}

// Ensure build directory exists
if (!fs.existsSync(buildDir)) {
  fs.mkdirSync(buildDir, { recursive: true });
}

// Move source zip to versioned zip in build directory
fs.renameSync(sourceZip, versionedZip);
console.log(`✓ Created ${path.basename(versionedZip)} in build directory`);

// Copy versioned zip to latest zip (always overwrites)
fs.copyFileSync(versionedZip, latestZip);
console.log(`✓ Created ${path.basename(latestZip)} in build directory (latest version)`);
