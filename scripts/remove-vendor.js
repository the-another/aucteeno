#!/usr/bin/env node
const fs = require("fs");
const path = require("path");

const vendorPath = path.resolve(__dirname, "..", "vendor");

if (fs.existsSync(vendorPath)) {
    fs.rmSync(vendorPath, { recursive: true, force: true });
    console.log("✅ vendor/ directory removed");
} else {
    console.log("ℹ️ vendor/ directory does not exist");
}
