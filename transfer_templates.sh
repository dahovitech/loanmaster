#!/bin/bash

# Informations de connexion
HOST="loanmaster.achatrembourse.online"
PORT="60827"
USER="mrjoker"
PASSWORD="j20U5HrazAo|0F9dwmAUY"
REMOTE_PATH="public_html"

# Template de base
echo "=== Copie du template de base ==="
sshpass -p "$PASSWORD" ssh -o StrictHostKeyChecking=no -p $PORT $USER@$HOST "cat > $REMOTE_PATH/templates/base.html.twig" < base.html.twig
if [ $? -eq 0 ]; then
    echo "✓ base.html.twig copié avec succès"
else
    echo "✗ Erreur lors de la copie de base.html.twig"
fi

# Template index
echo "=== Copie du template index ==="
sshpass -p "$PASSWORD" ssh -o StrictHostKeyChecking=no -p $PORT $USER@$HOST "cat > $REMOTE_PATH/templates/front/index.html.twig" < index.html.twig
if [ $? -eq 0 ]; then
    echo "✓ index.html.twig copié avec succès"
else
    echo "✗ Erreur lors de la copie de index.html.twig"
fi

# Template about
echo "=== Copie du template about ==="
sshpass -p "$PASSWORD" ssh -o StrictHostKeyChecking=no -p $PORT $USER@$HOST "cat > $REMOTE_PATH/templates/front/about.html.twig" < about.html.twig
if [ $? -eq 0 ]; then
    echo "✓ about.html.twig copié avec succès"
else
    echo "✗ Erreur lors de la copie de about.html.twig"
fi

# Template services
echo "=== Copie du template services ==="
sshpass -p "$PASSWORD" ssh -o StrictHostKeyChecking=no -p $PORT $USER@$HOST "cat > $REMOTE_PATH/templates/front/services.html.twig" < services.html.twig
if [ $? -eq 0 ]; then
    echo "✓ services.html.twig copié avec succès"
else
    echo "✗ Erreur lors de la copie de services.html.twig"
fi

# Template contact
echo "=== Copie du template contact ==="
sshpass -p "$PASSWORD" ssh -o StrictHostKeyChecking=no -p $PORT $USER@$HOST "cat > $REMOTE_PATH/templates/front/contact.html.twig" < contact.html.twig
if [ $? -eq 0 ]; then
    echo "✓ contact.html.twig copié avec succès"
else
    echo "✗ Erreur lors de la copie de contact.html.twig"
fi

echo "=== Transfert terminé ==="
