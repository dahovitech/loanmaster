#!/bin/bash

echo "=== UPLOAD DES IMAGES ESSENTIELLES ==="

FTP_USER="mrjoker_loanmaster"
FTP_PASS="eAaGl6vpl|c7Gv5P9"
FTP_HOST="loanmaster.achatrembourse.online"
BASE_PATH="public_html/public"

# Images critiques pour le design
IMAGES=(
    "deployment_package/public/assets/images/logo-dark.png:logo-dark.png"
    "deployment_package/public/assets/images/logo-light.png:logo-light.png"
    "deployment_package/public/assets/images/loader.png:loader.png"
    "deployment_package/public/assets/images/favicons/apple-touch-icon.png:apple-touch-icon.png"
    "deployment_package/public/assets/images/favicons/favicon-16x16.png:favicon-16x16.png"
)

for image_pair in "${IMAGES[@]}"; do
    IFS=':' read -r local_path remote_name <<< "$image_pair"
    
    if [ -f "$local_path" ]; then
        echo "Upload: $remote_name..."
        curl -u "$FTP_USER:$FTP_PASS" -T "$local_path" "ftp://$FTP_HOST/$BASE_PATH/$remote_name" 2>/dev/null
        if [ $? -eq 0 ]; then
            echo "✓ $remote_name uploadé"
        else
            echo "✗ Erreur upload $remote_name"
        fi
    else
        echo "⚠ Fichier non trouvé: $local_path"
    fi
done

echo "=== Upload images terminé ==="
