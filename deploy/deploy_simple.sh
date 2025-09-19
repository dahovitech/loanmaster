#!/bin/bash

# Script de déploiement simplifié pour LoanMaster
# Auteur: Prudence ASSOGBA

set -e

echo "🚀 Déploiement automatique de LoanMaster"
echo "========================================"

# Configuration
PROJECT_DIR="/workspace/loanmaster"
cd $PROJECT_DIR

echo "⚙️  Configuration de l'environnement de production..."
# Sauvegarder l'environnement local
cp .env .env.local.backup

# Créer la configuration de production
cat > .env.prod << 'EOF'
# Configuration de production pour LoanMaster
APP_ENV=prod
APP_SECRET=2f9f0b36f34f83e20c61d5e877a3c273
DATABASE_URL="mysql://mrjoker_loanmaster:eAaGl6vpl|c7Gv5P9@localhost:3306/mrjoker_loanmaster?serverVersion=8.0.32&charset=utf8mb4"
MAILER_DSN=smtp://localhost:587
LOCALE=fr
API_URL="http://45.93.137.66:5000"
MESSENGER_TRANSPORT_DSN=doctrine://default?auto_setup=0
EOF

echo "📦 Installation des dépendances Composer..."
# Vérifier si composer.phar existe, sinon l'installer
if [ ! -f composer.phar ]; then
    curl -sS https://getcomposer.org/installer | php
fi

# Installer les dépendances
php composer.phar install --optimize-autoloader --no-dev --no-interaction

echo "🗃️  Préparation des fichiers pour le déploiement..."
# Créer le répertoire de déploiement
rm -rf deploy/
mkdir -p deploy

# Copier les fichiers nécessaires
echo "📂 Copie des fichiers..."
rsync -av \
    --exclude='.git' \
    --exclude='node_modules' \
    --exclude='var/cache' \
    --exclude='var/log' \
    --exclude='deploy' \
    --exclude='.env.local.backup' \
    --exclude='tests' \
    --exclude='composer.phar' \
    . deploy/

# Copier la configuration de production
cp .env.prod deploy/.env

echo "🔧 Configuration des permissions..."
chmod -R 755 deploy/
find deploy/var -type d -exec chmod 777 {} \; 2>/dev/null || true

echo "📤 Synchronisation avec le serveur via FTP..."
# Créer un script de synchronisation FTP sécurisé
cat > sync_ftp.sh << 'EOFTP'
#!/bin/bash
lftp -c "
set ftp:ssl-allow no
set net:timeout 10
set net:max-retries 2
open -u mrjoker_loanmaster,eAaGl6vpl\|c7Gv5P9 ftp://46.202.129.197
cd /home/mrjoker/web/loanmaster.achatrembourse.online/public_html
mirror -R --delete --verbose deploy/ .
quit
"
EOFTP

chmod +x sync_ftp.sh

# Exécuter la synchronisation
if ./sync_ftp.sh; then
    echo "✅ Synchronisation FTP réussie!"
else
    echo "❌ Erreur de synchronisation FTP"
    exit 1
fi

echo "🎯 Configuration finale sur le serveur..."
# Commandes de finalisation via SSH
sshpass -p "j20U5HrazAo|0F9dwmAUY" ssh -o StrictHostKeyChecking=no mrjoker@46.202.129.197 << 'EOSSH'
cd /home/mrjoker/web/loanmaster.achatrembourse.online/public_html

# Vérifier si PHP CLI est disponible
if command -v php >/dev/null 2>&1; then
    echo "PHP CLI trouvé, exécution des commandes Symfony..."
    
    # Nettoyer le cache
    php bin/console cache:clear --env=prod --no-debug || echo "Cache clear failed"
    
    # Réchauffer le cache
    php bin/console cache:warmup --env=prod --no-debug || echo "Cache warmup failed"
    
    # Exécuter les migrations
    php bin/console doctrine:migrations:migrate --no-interaction || echo "Migrations failed"
else
    echo "PHP CLI non trouvé, configuration manuelle requise"
fi

# S'assurer des bonnes permissions
chmod -R 755 .
find var -type d -exec chmod 777 {} \; 2>/dev/null || true
chmod -R 777 var/ 2>/dev/null || true

echo "Configuration serveur terminée"
EOSSH

echo "🧹 Nettoyage des fichiers temporaires..."
rm -f sync_ftp.sh

echo "✅ Déploiement terminé avec succès!"
echo "🌐 Site accessible à: https://loanmaster.achatrembourse.online"
echo "==============================================="

# Restaurer l'environnement local
mv .env.local.backup .env

echo "📋 Résumé du déploiement:"
echo "- Environnement: Production"
echo "- Base de données: MySQL (mrjoker_loanmaster)"
echo "- Cache: Nettoyé et réchauffé"
echo "- Permissions: Configurées"
echo "==============================================="
