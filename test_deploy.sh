#!/bin/bash

# Script de déploiement étape par étape pour LoanMaster
# Auteur: Prudence ASSOGBA

echo "🔍 Test de déploiement par étapes"
echo "================================"

cd /workspace/loanmaster

echo "📋 Étape 1: Test de connexion FTP..."
lftp -c "
set ftp:ssl-allow no
set net:timeout 10
open -u mrjoker_loanmaster,eAaGl6vpl\|c7Gv5P9 ftp://46.202.129.197
ls
quit
" && echo "✅ FTP: Connexion réussie" || echo "❌ FTP: Échec de connexion"

echo ""
echo "📋 Étape 2: Test de connexion SSH..."
sshpass -p "j20U5HrazAo|0F9dwmAUY" ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 mrjoker@46.202.129.197 "echo 'SSH OK'; pwd; ls -la" && echo "✅ SSH: Connexion réussie" || echo "❌ SSH: Échec de connexion"

echo ""
echo "📋 Étape 3: Préparation d'un déploiement minimal..."

# Créer un fichier index.php simple pour tester
mkdir -p test_deploy
cat > test_deploy/index.php << 'EOF'
<?php
phpinfo();
?>
EOF

cat > test_deploy/test.html << 'EOF'
<!DOCTYPE html>
<html>
<head>
    <title>LoanMaster - Test Deployment</title>
</head>
<body>
    <h1>LoanMaster Deployment Test</h1>
    <p>This is a test page to verify deployment.</p>
    <p>Current time: <?php echo date('Y-m-d H:i:s'); ?></p>
</body>
</html>
EOF

echo "📋 Étape 4: Upload du test..."
lftp -c "
set ftp:ssl-allow no
set net:timeout 30
open -u mrjoker_loanmaster,eAaGl6vpl\|c7Gv5P9 ftp://46.202.129.197
cd /home/mrjoker/web/loanmaster.achatrembourse.online/public_html
put test_deploy/test.html
put test_deploy/index.php
quit
" && echo "✅ Upload de test réussi" || echo "❌ Upload de test échoué"

echo ""
echo "📋 Étape 5: Test d'accès web..."
echo "Visitez: https://loanmaster.achatrembourse.online/test.html"
echo "Ou: https://loanmaster.achatrembourse.online/index.php"

rm -rf test_deploy
