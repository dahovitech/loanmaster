#!/bin/bash

# Script de déploiement du code source
# Auteur: Prudence ASSOGBA

echo "📂 Déploiement du code source PHP"
echo "================================"

cd /workspace/loanmaster

# Fonction pour déployer récursivement un répertoire
deploy_directory() {
    local src_dir=$1
    local remote_base_dir=$2
    
    echo "📁 Déploiement du répertoire $src_dir..."
    
    # Créer d'abord la structure des répertoires
    find "$src_dir" -type d | while read dir; do
        remote_dir="$remote_base_dir/$dir"
        sshpass -p "j20U5HrazAo|0F9dwmAUY" ssh -o StrictHostKeyChecking=no mrjoker@46.202.129.197 \
            "mkdir -p '$remote_dir'"
    done
    
    # Ensuite déployer tous les fichiers PHP
    find "$src_dir" -name "*.php" | while read file; do
        remote_file="$remote_base_dir/$file"
        echo "  📤 $file..."
        sshpass -p "j20U5HrazAo|0F9dwmAUY" ssh -o StrictHostKeyChecking=no mrjoker@46.202.129.197 \
            "cat > '$remote_file'" < "$file"
    done
    
    # Déployer les autres fichiers importants
    find "$src_dir" -name "*.yaml" -o -name "*.yml" -o -name "*.xml" -o -name "*.json" | while read file; do
        remote_file="$remote_base_dir/$file"
        echo "  📄 $file..."
        sshpass -p "j20U5HrazAo|0F9dwmAUY" ssh -o StrictHostKeyChecking=no mrjoker@46.202.129.197 \
            "cat > '$remote_file'" < "$file"
    done
}

REMOTE_BASE="/home/mrjoker/web/loanmaster.achatrembourse.online/public_html"

echo "🚀 Déploiement des fichiers source..."
deploy_directory "src" "$REMOTE_BASE"

echo "📚 Déploiement des templates..."
deploy_directory "templates" "$REMOTE_BASE"

echo "🗄️ Déploiement des migrations..."
deploy_directory "migrations" "$REMOTE_BASE"

echo "🌐 Déploiement des traductions..."
deploy_directory "translations" "$REMOTE_BASE"

echo "📦 Déploiement des fichiers publics..."
if [ -d "public" ]; then
    find "public" -name "*.css" -o -name "*.js" -o -name "*.ico" -o -name "*.png" -o -name "*.jpg" -o -name "*.gif" | while read file; do
        remote_file="$REMOTE_BASE/$file"
        echo "  🎨 $file..."
        sshpass -p "j20U5HrazAo|0F9dwmAUY" ssh -o StrictHostKeyChecking=no mrjoker@46.202.129.197 \
            "cat > '$remote_file'" < "$file"
    done
fi

echo "🎯 Test final..."
sshpass -p "j20U5HrazAo|0F9dwmAUY" ssh -o StrictHostKeyChecking=no mrjoker@46.202.129.197 << 'EOSSH'
cd /home/mrjoker/web/loanmaster.achatrembourse.online/public_html

echo "Vérification de la classe Kernel..."
if [ -f "src/Kernel.php" ]; then
    echo "✅ src/Kernel.php présent"
    head -5 src/Kernel.php
else
    echo "❌ src/Kernel.php manquant"
fi

echo "Test de l'autoloader..."
php -r "require 'vendor/autoload.php'; echo 'Autoloader OK'; echo PHP_EOL;"

echo "Configuration finale..."
chmod -R 755 .
chmod -R 777 var/
EOSSH

echo "✅ Déploiement du code source terminé!"
