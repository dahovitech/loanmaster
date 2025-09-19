#!/bin/bash

echo "=== DÉPLOIEMENT VIA FTP - ÉTAPE 3 ==="

# Credentials FTP
FTP_HOST="loanmaster.achatrembourse.online"
FTP_USER="mrjoker_loanmaster"
FTP_PASS="eAaGl6vpl|c7Gv5P9"

echo "1. Test de connexion FTP..."
lftp -c "
set ftp:ssl-allow no
set ssl:verify-certificate no
open ftp://$FTP_USER:$FTP_PASS@$FTP_HOST
pwd
quit
" 2>&1

if [ $? -eq 0 ]; then
    echo "✓ Connexion FTP établie"
else
    echo "✗ Échec connexion FTP - Tentative avec curl..."
    curl -u "$FTP_USER:$FTP_PASS" "ftp://$FTP_HOST/" -l
fi
