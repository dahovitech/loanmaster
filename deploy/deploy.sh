#!/bin/bash

# Script de déploiement automatique pour LoanMaster
# Auteur: Prudence ASSOGBA

set -e

# Configuration
SERVER="46.202.129.197"
USER="mrjoker"
PASS="j20U5HrazAo|0F9dwmAUY"
REMOTE_PATH="/home/mrjoker/web/loanmaster.achatrembourse.online/public_html"
LOCAL_PATH="/workspace/loanmaster"
BRANCH="dev"

echo "🚀 Début du déploiement automatique de LoanMaster"
echo "==============================================="

# Configuration pour l'environnement de production
echo "⚙️  Configuration de l'environnement de production..."
cp .env .env.backup
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

echo "📦 Installation des dépendances..."
php composer.phar install --optimize-autoloader --no-dev

echo "🗃️  Création du répertoire de déploiement..."
mkdir -p deploy

echo "📂 Préparation des fichiers pour le déploiement..."
# Copier tous les fichiers nécessaires
rsync -av --exclude='.git' --exclude='node_modules' --exclude='var/cache' --exclude='var/log' --exclude='deploy' --exclude='.env.backup' . deploy/

# Utiliser la configuration de production
cp .env.prod deploy/.env

echo "🔧 Configuration des permissions..."
chmod -R 755 deploy/
chmod -R 777 deploy/var/

echo "📤 Déploiement vers le serveur..."
# Créer un script de synchronisation
cat > sync_to_server.sh << 'EOF'
#!/bin/bash
# Script de synchronisation FTP sécurisé

# Utilisation d'lftp pour un transfert robuste
lftp -c "
set ftp:ssl-allow no
set sftp:auto-confirm yes
open -u mrjoker_loanmaster,eAaGl6vpl|c7Gv5P9 ftp://46.202.129.197
mirror -R --delete --verbose deploy/ /home/mrjoker/web/loanmaster.achatrembourse.online/public_html/
quit
"
EOF

chmod +x sync_to_server.sh
./sync_to_server.sh

echo "🎯 Configuration finale sur le serveur..."
# Exécuter les commandes finales via SSH
sshpass -p "j20U5HrazAo|0F9dwmAUY" ssh -o StrictHostKeyChecking=no mrjoker@46.202.129.197 << 'EOF'
cd /home/mrjoker/web/loanmaster.achatrembourse.online/public_html
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod
php bin/console doctrine:migrations:migrate --no-interaction
chmod -R 755 .
chmod -R 777 var/
EOF

echo "✅ Déploiement terminé avec succès!"
echo "🌐 Site accessible à: https://loanmaster.achatrembourse.online"
echo "==============================================="

# Restaurer l'environnement local
mv .env.backup .env
