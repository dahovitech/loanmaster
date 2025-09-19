#!/bin/bash

# Script de déploiement via SSH/SCP pour LoanMaster
# Auteur: Prudence ASSOGBA

echo "🚀 Déploiement LoanMaster via SSH"
echo "================================"

cd /workspace/loanmaster

echo "📂 Création du package de déploiement..."
rm -rf deploy_package/
mkdir -p deploy_package

# Copier les fichiers essentiels
echo "📋 Préparation des fichiers..."
tar --exclude='.git' \
    --exclude='node_modules' \
    --exclude='var/cache' \
    --exclude='var/log' \
    --exclude='tests' \
    --exclude='deploy*' \
    --exclude='composer.phar' \
    -czf deploy_package.tar.gz .

echo "📤 Upload du package..."
sshpass -p "j20U5HrazAo|0F9dwmAUY" scp -o StrictHostKeyChecking=no deploy_package.tar.gz mrjoker@46.202.129.197:/home/mrjoker/

echo "🎯 Déploiement sur le serveur..."
sshpass -p "j20U5HrazAo|0F9dwmAUY" ssh -o StrictHostKeyChecking=no mrjoker@46.202.129.197 << 'EOSSH'
echo "Sauvegarde de l'index.html existant..."
cp /home/mrjoker/web/loanmaster.achatrembourse.online/public_html/index.html /home/mrjoker/index.html.backup 2>/dev/null || true

echo "Nettoyage du répertoire de destination..."
rm -rf /home/mrjoker/web/loanmaster.achatrembourse.online/public_html/*

echo "Extraction du package..."
cd /home/mrjoker/web/loanmaster.achatrembourse.online/public_html/
tar -xzf /home/mrjoker/deploy_package.tar.gz

echo "Configuration de l'environnement de production..."
cat > .env << 'EOF'
APP_ENV=prod
APP_SECRET=2f9f0b36f34f83e20c61d5e877a3c273
DATABASE_URL="mysql://mrjoker_loanmaster:eAaGl6vpl|c7Gv5P9@localhost:3306/mrjoker_loanmaster?serverVersion=8.0.32&charset=utf8mb4"
MAILER_DSN=smtp://localhost:587
LOCALE=fr
API_URL="http://45.93.137.66:5000"
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
EOF

echo "Installation de Composer..."
if [ ! -f composer.phar ]; then
    curl -sS https://getcomposer.org/installer | php
fi

echo "Installation des dépendances..."
php composer.phar install --optimize-autoloader --no-dev --no-interaction || echo "Installation Composer échouée"

echo "Configuration des permissions..."
chmod -R 755 .
mkdir -p var/{cache,log,sessions}
chmod -R 777 var/

echo "Test de l'application..."
if [ -f public/index.php ]; then
    echo "✅ Application Symfony détectée"
else
    echo "❌ index.php non trouvé"
fi

echo "Nettoyage..."
rm -f /home/mrjoker/deploy_package.tar.gz

echo "Déploiement terminé!"
EOSSH

echo "🧹 Nettoyage local..."
rm -f deploy_package.tar.gz

echo "✅ Déploiement SSH terminé!"
echo "🌐 Site: https://loanmaster.achatrembourse.online"
