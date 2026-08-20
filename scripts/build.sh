#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "${BASH_SOURCE[0]}")/.."

VERSION=$(grep -m1 '<version>' iquix.xml | sed -E 's/.*<version>(.*)<\/version>.*/\1/')
OUT="releases/com_iquix_${VERSION}.zip"

rm -rf releases/tmp "$OUT"
mkdir -p releases/tmp/admin

# iquix.xml declares <administration><files folder="admin">, so the admin
# PHP files, setup wizard, and manifest must land under admin/ in the zip.
# languages/ and iquix.xml itself stay at the package root.
cp -R setup iquix.php script.php config.xml iquix.xml access.xml releases/tmp/admin/
rm -rf releases/tmp/admin/setup/tmp

cp iquix.xml releases/tmp/iquix.xml
cp -R languages releases/tmp/languages

(cd releases/tmp && zip -qr "../com_iquix_${VERSION}.zip" .)
rm -rf releases/tmp

echo "Built $OUT"
