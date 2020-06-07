#!/bin/bash
# Sets up tinymce for bludit

echo "Checking Dependencies..."
for cmd in curl unzip jq; do
  printf '%-10s' "$cmd"
  if hash "$cmd" 2>/dev/null; then
    echo OK
  else
    echo missing
    exit 1
  fi
done

# Cleanup previous directories if any
if [ -d "tinymce" ]; then
    rm -rf tinymce
fi

# Find latest version
LATEST_VERSION=$(curl -L "https://raw.githubusercontent.com/tinymce/tinymce-dist/master/package.json" | jq -r .version)
DOWNLOAD_URL=http://download.tiny.cloud/tinymce/community/tinymce_$LATEST_VERSION.zip

echo -e "\nDownloading Latest TinyMCE Version: $LATEST_VERSION from $DOWNLOAD_URL"

curl --compressed -L "$DOWNLOAD_URL" -o tinymce.zip
unzip tinymce.zip

# Download language packs
echo "Download language packs"
curl --compressed -L "https://www.tiny.cloud/tinymce-services-azure/1/i18n/download?langs=zh_CN,nl,fr_FR,de,hu_HU,ja,fa,pl,pt_BR,ro,ru,es,tr,uk" -o langs.zip
unzip langs.zip

DIR="tinymce/js/tinymce"
if [ -d "$DIR" ]; then
    mv $DIR tinymce-raw

    # Cleanup
    rm tinymce.zip langs.zip
    rm -rf tinymce

    # Setup
    mv tinymce-raw tinymce

    # Remove files not required by bludit
    rm tinymce/license.txt
    rm -rf tinymce/langs
    rm -rf tinymce/plugins/emoticons

    # Setup language packs
    mv langs tinymce
    # Rename lang files to bludit specific names
    mv tinymce/langs/zh_CN.js tinymce/langs/zh.js
    mv tinymce/langs/fr_FR.js tinymce/langs/fr.js
    mv tinymce/langs/hu_HU.js tinymce/langs/hu.js
    mv tinymce/langs/pt_BR.js tinymce/langs/pt.js

    # Fix permissions
    find . -type d -exec chmod 755 {} \;
    find . -type f -exec chmod 644 {} \;

    echo "TinyMCE Download Complete. :)"

else
    echo "Error: ${DIR} not found. Can not continue."
    exit 1
fi
