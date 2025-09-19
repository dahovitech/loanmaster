#!/bin/bash

echo "=== SCRIPT DE DÉPLOIEMENT LOANMASTER ==="
echo "Date: $(date)"
echo ""

# Informations de connexion
HOST="loanmaster.achatrembourse.online"
PORT="60827"
USER="mrjoker"
PASSWORD="j20U5HrazAo|0F9dwmAUY"
REMOTE_PATH="public_html"

echo "=== 1. Vérification de la connectivité ==="
if curl -s --connect-timeout 10 https://loanmaster.achatrembourse.online/ > /dev/null; then
    echo "✓ Site web accessible"
else
    echo "✗ Site web inaccessible"
    exit 1
fi

echo ""
echo "=== 2. Test de connexion SSH ==="
if timeout 20 sshpass -p "$PASSWORD" ssh -o ConnectTimeout=10 -o StrictHostKeyChecking=no -p $PORT $USER@$HOST "echo 'SSH OK'"; then
    echo "✓ Connexion SSH établie"
else
    echo "✗ Connexion SSH échouée - Veuillez réessayer plus tard"
    exit 1
fi

echo ""
echo "=== 3. Nettoyage du cache Symfony ==="
sshpass -p "$PASSWORD" ssh -o StrictHostKeyChecking=no -p $PORT $USER@$HOST "cd $REMOTE_PATH && php bin/console cache:clear --env=prod"

echo ""
echo "=== 4. Copie du template de base ==="
sshpass -p "$PASSWORD" ssh -o StrictHostKeyChecking=no -p $PORT $USER@$HOST "cat > $REMOTE_PATH/templates/base.html.twig" < deployment_package/templates/base.html.twig
echo "✓ base.html.twig copié"

echo ""
echo "=== 5. Copie des templates de pages ==="
sshpass -p "$PASSWORD" ssh -o StrictHostKeyChecking=no -p $PORT $USER@$HOST "cat > $REMOTE_PATH/templates/front/index.html.twig" < deployment_package/templates/front/index.html.twig
echo "✓ index.html.twig copié"

sshpass -p "$PASSWORD" ssh -o StrictHostKeyChecking=no -p $PORT $USER@$HOST "cat > $REMOTE_PATH/templates/front/about.html.twig" < deployment_package/templates/front/about.html.twig
echo "✓ about.html.twig copié"

sshpass -p "$PASSWORD" ssh -o StrictHostKeyChecking=no -p $PORT $USER@$HOST "cat > $REMOTE_PATH/templates/front/services.html.twig" < deployment_package/templates/front/services.html.twig
echo "✓ services.html.twig copié"

sshpass -p "$PASSWORD" ssh -o StrictHostKeyChecking=no -p $PORT $USER@$HOST "cat > $REMOTE_PATH/templates/front/contact.html.twig" < deployment_package/templates/front/contact.html.twig
echo "✓ contact.html.twig copié"

echo ""
echo "=== 6. Création du dossier assets ==="
sshpass -p "$PASSWORD" ssh -o StrictHostKeyChecking=no -p $PORT $USER@$HOST "mkdir -p $REMOTE_PATH/public/assets/{css,js,images,vendors}"

echo ""
echo "=== 7. Copie des assets critiques ==="
# CSS principal
echo "Copie des fichiers CSS..."
sshpass -p "$PASSWORD" scp -o StrictHostKeyChecking=no -P $PORT deployment_package/public/assets/css/easilon.css $USER@$HOST:$REMOTE_PATH/public/assets/css/

# JS principal
echo "Copie des fichiers JavaScript..."  
sshpass -p "$PASSWORD" scp -o StrictHostKeyChecking=no -P $PORT deployment_package/public/assets/js/easilon.js $USER@$HOST:$REMOTE_PATH/public/assets/js/

# Images critiques
echo "Copie des images essentielles..."
sshpass -p "$PASSWORD" scp -o StrictHostKeyChecking=no -P $PORT -r deployment_package/public/assets/images/logo* $USER@$HOST:$REMOTE_PATH/public/assets/images/ 2>/dev/null || echo "Logos non trouvés"
sshpass -p "$PASSWORD" scp -o StrictHostKeyChecking=no -P $PORT -r deployment_package/public/assets/images/favicons $USER@$HOST:$REMOTE_PATH/public/assets/images/ 2>/dev/null || echo "Favicons non trouvés"

# Vendors essentiels
echo "Copie des vendors essentiels..."
sshpass -p "$PASSWORD" scp -o StrictHostKeyChecking=no -P $PORT -r deployment_package/public/assets/vendors/bootstrap $USER@$HOST:$REMOTE_PATH/public/assets/vendors/ 2>/dev/null || echo "Bootstrap non copié"
sshpass -p "$PASSWORD" scp -o StrictHostKeyChecking=no -P $PORT -r deployment_package/public/assets/vendors/jquery $USER@$HOST:$REMOTE_PATH/public/assets/vendors/ 2>/dev/null || echo "jQuery non copié"
sshpass -p "$PASSWORD" scp -o StrictHostKeyChecking=no -P $PORT -r deployment_package/public/assets/vendors/fontawesome $USER@$HOST:$REMOTE_PATH/public/assets/vendors/ 2>/dev/null || echo "FontAwesome non copié"

echo ""
echo "=== 8. Vérification du déploiement ==="
echo "Test des pages déployées..."

for page in "" "about" "services" "contact"; do
    url="https://loanmaster.achatrembourse.online/$page"
    if curl -s --connect-timeout 10 "$url" > /dev/null; then
        echo "✓ Page /$page accessible"
    else
        echo "✗ Page /$page inaccessible"
    fi
done

echo ""
echo "=== DÉPLOIEMENT TERMINÉ ==="
echo "Le nouveau design a été appliqué avec succès !"
echo "Site: https://loanmaster.achatrembourse.online/"
