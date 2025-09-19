#!/bin/bash

# Script de déploiement minimal pour LoanMaster
# Auteur: Prudence ASSOGBA

set -e

echo "🚀 Déploiement minimal de LoanMaster"
echo "==================================="

cd /workspace/loanmaster

echo "⚙️  Configuration de l'environnement de production..."
# Créer un .env de production
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
# Supprimer et recréer le répertoire de déploiement
rm -rf deploy/
mkdir -p deploy

# Copier les fichiers essentiels (en excluant les fichiers de développement)
echo "📋 Copie des fichiers essentiels..."
rsync -av \
    --exclude='.git' \
    --exclude='node_modules' \
    --exclude='var/cache' \
    --exclude='var/log' \
    --exclude='var/sessions' \
    --exclude='deploy' \
    --exclude='tests' \
    --exclude='composer.phar' \
    --exclude='.env.local*' \
    --exclude='phpunit.xml*' \
    --exclude='.phpunit*' \
    . deploy/

# Copier la configuration de production
cp .env.prod deploy/.env

echo "🔧 Configuration des permissions..."
chmod -R 755 deploy/
mkdir -p deploy/var/{cache,log,sessions}
chmod -R 777 deploy/var/

echo "📤 Synchronisation FTP..."
# Script FTP simplifié
lftp -c "
set ftp:ssl-allow no
set net:timeout 30
set net:max-retries 3
open -u mrjoker_loanmaster,eAaGl6vpl\|c7Gv5P9 ftp://46.202.129.197
cd /home/mrjoker/web/loanmaster.achatrembourse.online/public_html
mirror -R --delete --verbose --exclude-glob .git* deploy/ .
quit
" || echo "FTP synchronisation terminée avec avertissements"

echo "🎯 Configuration finale..."
# Commandes SSH simplifiées
sshpass -p "j20U5HrazAo|0F9dwmAUY" ssh -o StrictHostKeyChecking=no mrjoker@46.202.129.197 << 'EOSSH'
cd /home/mrjoker/web/loanmaster.achatrembourse.online/public_html

echo "Configuration des permissions finales..."
chmod -R 755 .
chmod -R 777 var/ 2>/dev/null || true

echo "Création des répertoires manquants..."
mkdir -p var/{cache,log,sessions} 2>/dev/null || true
chmod -R 777 var/ 2>/dev/null || true

echo "Vérification de la structure..."
ls -la
EOSSH

echo "✅ Déploiement terminé!"
echo "🌐 Site: https://loanmaster.achatrembourse.online"
echo "📋 Note: Les migrations et cache doivent être gérés manuellement sur le serveur"
echo "================================="

echo "🧹 Nettoyage local..."
rm -f .env.prod

echo "✨ Déploiement minimal réussi!"
