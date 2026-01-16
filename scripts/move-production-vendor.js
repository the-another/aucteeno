#!/usr/bin/env node
/**
 * Move selected vendor packages into ./packages (copy + optional delete).
 * Works on Windows/macOS/Linux.
 */
const fs = require("fs");
const path = require("path");

const projectRoot = path.resolve(__dirname, "..");

const moves = [
];

const REMOVE_SOURCE = true; // set false if you want to copy but keep vendor intact

function ensureDir(dir) {
    fs.mkdirSync(dir, {recursive: true});
}

function copyDir(src, dest) {
    ensureDir(dest);
    fs.cpSync(src, dest, {recursive: true});
}

function removeDir(p) {
    fs.rmSync(p, {recursive: true, force: true});
}

function existsDir(p) {
    try {
        return fs.statSync(p).isDirectory();
    } catch {
        return false;
    }
}

let hadError = false;

for (const [fromRel, toRel] of moves) {
    const from = path.join(projectRoot, fromRel);
    const to = path.join(projectRoot, toRel);

    if (!existsDir(from)) {
        console.warn(`⚠️  Not found: ${fromRel}`);
        continue;
    }

    console.log(`➡️  Copy: ${fromRel} -> ${toRel}`);
    copyDir(from, to);

    if (REMOVE_SOURCE) {
        console.log(`🧹 Remove: ${fromRel}`);
        removeDir(from);
    }
}

if (hadError) process.exit(1);
console.log("✅ Done");
