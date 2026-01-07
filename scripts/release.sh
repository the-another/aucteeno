#!/bin/sh
# Release script for packaging plugin for WordPress.org distribution
#
# This script creates a clean package directory, copies plugin files
# (excluding dev files), and creates a zip file for distribution.

set -e

# Colors for output
GREEN='\033[0;32m'
NC='\033[0m' # No Color

# Plugin name and version
PLUGIN_NAME="aucteeno"
PLUGIN_VERSION=$(grep "Version:" aucteeno.php | sed 's/.*Version: *\([0-9.]*\).*/\1/')
PACKAGE_DIR="build/${PLUGIN_NAME}"
PACKAGE_FILE="build/${PLUGIN_NAME}-${PLUGIN_VERSION}.zip"

echo -e "${GREEN}Packaging plugin for WordPress.org...${NC}"
echo "Plugin version: ${PLUGIN_VERSION}"

# Create build directory
mkdir -p build
rm -rf "${PACKAGE_DIR}"
mkdir -p "${PACKAGE_DIR}"

# Copy plugin files (including only necessary files)
rsync -av --progress \
	--include='aucteeno.php' \
	--include='includes/' \
	--include='includes/**' \
	--include='dependencies/' \
	--include='dependencies/**' \
	--include='CHANGELOG.md' \
	--include='README.txt' \
	--include='README.md' \
	--include='languages/' \
	--include='languages/**' \
	--include='assets/' \
	--include='assets/**' \
	--exclude='*' \
	./ "${PACKAGE_DIR}/"

# Create zip file
cd build
zip -r "${PLUGIN_NAME}-${PLUGIN_VERSION}.zip" "${PLUGIN_NAME}" -q

echo -e "${GREEN}Package created: ${PACKAGE_FILE}${NC}"

