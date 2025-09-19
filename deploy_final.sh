#!/bin/bash

# Script de déploiement final pour LoanMaster
# Auteur: Prudence ASSOGBA

echo "🚀 Déploiement final LoanMaster"
echo "============================="

cd /workspace/loanmaster

echo "⚙️  Configuration de l'environnement de production..."
cat > .env.prod << 'EOF'
APP_ENV=prod
APP_SECRET=2f9f0b36f34f83e20c61d5e877a3c273
DATABASE_URL="mysql://mrjoker_loanmaster:eAaGl6vpl|c7Gv5P9@localhost:3306/mrjoker_loanmaster?serverVersion=8.0.32&charset=utf8mb4"
MAILER_DSN=smtp://localhost:587
LOCALE=fr
API_URL="http://45.93.137.66:5000"
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
EOF

echo "📂 Préparation des fichiers..."
rm -rf final_deploy/
mkdir -p final_deploy

# Copier les fichiers essentiels
echo "📋 Copie des fichiers..."
cp -r bin final_deploy/
cp -r config final_deploy/
cp -r public final_deploy/
cp -r src final_deploy/
cp -r templates final_deploy/
cp -r translations final_deploy/
cp -r vendor final_deploy/ 2>/dev/null || echo "Vendor non copié - sera installé sur le serveur"
cp composer.json final_deploy/
cp composer.lock final_deploy/
cp symfony.lock final_deploy/
cp importmap.php final_deploy/
cp .env.prod final_deploy/.env

# Créer les répertoires var
mkdir -p final_deploy/var/{cache,log,sessions}

echo "🔧 Configuration des permissions..."
chmod -R 755 final_deploy/
chmod -R 777 final_deploy/var/

echo "📤 Upload vers le serveur..."
lftp -c "
set ftp:ssl-allow no
set net:timeout 60
set net:max-retries 3
open -u mrjoker_loanmaster,eAaGl6vpl\|c7Gv5P9 ftp://46.202.129.197
cd /home/mrjoker/web/loanmaster.achatrembourse.online/public_html
lcd final_deploy
mirror -R --delete --verbose --exclude-glob .git* . .
quit
"

if [ $? -eq 0 ]; then
    echo "✅ Upload réussi!"
else
    echo "❌ Erreur d'upload, tentative alternative..."
    # Upload fichier par fichier si nécessaire
    lftp -c "
    set ftp:ssl-allow no
    open -u mrjoker_loanmaster,eAaGl6vpl\|c7Gv5P9 ftp://46.202.129.197
    cd /home/mrjoker/web/loanmaster.achatrembourse.online/public_html
    lcd final_deploy
    mput -R *
    quit
    "
fi

echo "🎯 Configuration finale sur le serveur..."
sshpass -p "j20U5HrazAo|0F9dwmAUY" ssh -o StrictHostKeyChecking=no mrjoker@46.202.129.197 << 'EOSSH'
cd /home/mrjoker/web/loanmaster.achatrembourse.online/public_html

echo "Installation des dépendances Composer..."
if command -v composer >/dev/null 2>&1; then
    composer install --optimize-autoloader --no-dev --no-interaction
elif command -v php >/dev/null 2>&1; then
    curl -sS https://getcomposer.org/installer | php
    php composer.phar install --optimize-autoloader --no-dev --no-interaction
fi

echo "Configuration des permissions..."
chmod -R 755 .
mkdir -p var/{cache,log,sessions}
chmod -R 777 var/

echo "Configuration finale terminée!"
EOSSH

echo "✅ Déploiement terminé!"
echo "🌐 Site: https://loanmaster.achatrembourse.online"

# Nettoyage
rm -rf final_deploy/
rm -f .env.prod

echo "🎉 LoanMaster déployé avec succès!"
