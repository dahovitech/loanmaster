#!/bin/bash

# Script pour générer les icônes PWA pour LoanMaster
# Utilise ImageMagick pour créer des icônes de différentes tailles

ICON_DIR="/workspace/loanmaster/public/icons"
BRAND_COLOR="#2563eb"
BACKGROUND_COLOR="#ffffff"

# Créer le répertoire si nécessaire
mkdir -p "$ICON_DIR"

echo "🎨 Génération des icônes PWA pour LoanMaster..."

# Fonction pour créer une icône avec texte
create_icon() {
    local size=$1
    local filename=$2
    local font_size=$((size / 8))
    local corner_radius=$((size / 8))
    
    convert -size ${size}x${size} xc:"$BACKGROUND_COLOR" \
        -fill "$BRAND_COLOR" \
        -draw "roundrectangle 0,0 $((size-1)),$((size-1)) $corner_radius,$corner_radius" \
        -fill white \
        -font Liberation-Sans-Bold \
        -pointsize $font_size \
        -gravity center \
        -annotate +0-$((font_size/4)) "LM" \
        -pointsize $((font_size/2)) \
        -annotate +0+$((font_size/2)) "💰" \
        "$ICON_DIR/$filename"
}

# Générer les icônes principales
create_icon 72 "icon-72x72.png"
create_icon 96 "icon-96x96.png"
create_icon 128 "icon-128x128.png"
create_icon 144 "icon-144x144.png"
create_icon 152 "icon-152x152.png"
create_icon 192 "icon-192x192.png"
create_icon 384 "icon-384x384.png"
create_icon 512 "icon-512x512.png"

# Icônes spéciales pour iOS
create_icon 180 "apple-touch-icon.png"
create_icon 120 "icon-120x120.png"
create_icon 114 "icon-114x114.png"
create_icon 76 "icon-76x76.png"
create_icon 60 "icon-60x60.png"
create_icon 57 "icon-57x57.png"
create_icon 32 "icon-32x32.png"
create_icon 16 "icon-16x16.png"

# Icône de badge pour les notifications
convert -size 72x72 xc:"$BRAND_COLOR" \
    -fill white \
    -font Liberation-Sans-Bold \
    -pointsize 24 \
    -gravity center \
    -annotate +0+0 "!" \
    "$ICON_DIR/badge-72x72.png"

# Créer les icônes pour les raccourcis
create_shortcut_icon() {
    local filename=$1
    local emoji=$2
    local color=$3
    
    convert -size 96x96 xc:"$color" \
        -fill white \
        -font Liberation-Sans-Bold \
        -pointsize 40 \
        -gravity center \
        -annotate +0+0 "$emoji" \
        "$ICON_DIR/$filename"
}

create_shortcut_icon "shortcut-new-loan.png" "+" "#10b981"
create_shortcut_icon "shortcut-my-loans.png" "📋" "#f59e0b"
create_shortcut_icon "shortcut-profile.png" "👤" "#8b5cf6"
create_shortcut_icon "shortcut-support.png" "💬" "#ef4444"

# Créer les icônes d'actions pour les notifications
convert -size 32x32 xc:"#10b981" \
    -fill white \
    -font Liberation-Sans-Bold \
    -pointsize 16 \
    -gravity center \
    -annotate +0+0 "👁" \
    "$ICON_DIR/action-view.png"

convert -size 32x32 xc:"#ef4444" \
    -fill white \
    -font Liberation-Sans-Bold \
    -pointsize 16 \
    -gravity center \
    -annotate +0+0 "✕" \
    "$ICON_DIR/action-dismiss.png"

echo "✅ Icônes PWA générées avec succès dans $ICON_DIR"
echo "📱 Total: $(ls -1 "$ICON_DIR" | wc -l) icônes créées"

# Lister les icônes créées
echo -e "\n📋 Icônes créées:"
ls -la "$ICON_DIR" | grep "\.png$" | awk '{print "   " $9 " (" $5 " bytes)"}'
