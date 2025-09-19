#!/bin/bash

# Script de déploiement manuel LoanMaster - Fichier par fichier
# Auteur: Prudence ASSOGBA

echo "🛠️ Déploiement manuel LoanMaster"
echo "==============================="

# Fonction pour créer un fichier via SSH
deploy_file() {
    local file=$1
    local remote_path=$2
    
    if [ -f "$file" ]; then
        echo "📤 Déploiement de $file..."
        sshpass -p "j20U5HrazAo|0F9dwmAUY" ssh -o StrictHostKeyChecking=no mrjoker@46.202.129.197 \
            "cat > $remote_path" < "$file"
        if [ $? -eq 0 ]; then
            echo "✅ $file déployé"
        else
            echo "❌ Échec pour $file"
        fi
    else
        echo "⚠️  $file n'existe pas localement"
    fi
}

# Fonction pour créer un répertoire via SSH
create_remote_dir() {
    local dir=$1
    echo "📁 Création du répertoire $dir..."
    sshpass -p "j20U5HrazAo|0F9dwmAUY" ssh -o StrictHostKeyChecking=no mrjoker@46.202.129.197 \
        "mkdir -p /home/mrjoker/web/loanmaster.achatrembourse.online/public_html/$dir"
}

cd /workspace/loanmaster

REMOTE_BASE="/home/mrjoker/web/loanmaster.achatrembourse.online/public_html"

echo "🏗️ Création de la structure des répertoires..."
create_remote_dir "config"
create_remote_dir "src"
create_remote_dir "public"
create_remote_dir "templates"
create_remote_dir "bin"
create_remote_dir "migrations"

echo "📋 Déploiement des fichiers de configuration principaux..."
deploy_file "composer.json" "$REMOTE_BASE/composer.json"
deploy_file "composer.lock" "$REMOTE_BASE/composer.lock"
deploy_file "symfony.lock" "$REMOTE_BASE/symfony.lock"
deploy_file "importmap.php" "$REMOTE_BASE/importmap.php"

echo "🔧 Déploiement du point d'entrée Symfony..."
deploy_file "public/index.php" "$REMOTE_BASE/public/index.php"

echo "⚙️ Déploiement des configurations..."
for config_file in config/*.yaml config/*.php; do
    if [ -f "$config_file" ]; then
        filename=$(basename "$config_file")
        deploy_file "$config_file" "$REMOTE_BASE/config/$filename"
    fi
done

echo "📜 Déploiement des scripts bin..."
for bin_file in bin/*; do
    if [ -f "$bin_file" ]; then
        filename=$(basename "$bin_file")
        deploy_file "$bin_file" "$REMOTE_BASE/bin/$filename"
    fi
done

echo "🎨 Déploiement des templates..."
# Nous devrons faire cela par batch car il y a beaucoup de fichiers
sshpass -p "j20U5HrazAo|0F9dwmAUY" ssh -o StrictHostKeyChecking=no mrjoker@46.202.129.197 \
    "mkdir -p $REMOTE_BASE/templates"

echo "🚀 Finalisation du déploiement..."
sshpass -p "j20U5HrazAo|0F9dwmAUY" ssh -o StrictHostKeyChecking=no mrjoker@46.202.129.197 << 'EOSSH'
cd /home/mrjoker/web/loanmaster.achatrembourse.online/public_html

echo "Configuration des permissions..."
chmod -R 755 .
chmod +x bin/console 2>/dev/null || true
mkdir -p var/{cache,log,sessions}
chmod -R 777 var/

echo "Test de Composer..."
if [ -f composer.json ]; then
    echo "✅ composer.json présent"
else
    echo "❌ composer.json manquant"
fi

if [ -f public/index.php ]; then
    echo "✅ Point d'entrée Symfony présent"
else
    echo "❌ Point d'entrée Symfony manquant"
fi

echo "Finalisation terminée!"
EOSSH

echo "✅ Déploiement manuel terminé!"
echo "🔍 Vérifiez: https://loanmaster.achatrembourse.online?test=files"
