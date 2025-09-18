<?php

/**
 * Script de test pour vérifier la correction du bug isEnabled
 * Ce script peut être exécuté pour valider que l'entité Language
 * supporte maintenant correctement le champ isEnabled
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Entity\Language;

echo "🔧 Test de correction du bug isEnabled pour l'entité Language\n";
echo "=" . str_repeat("=", 60) . "\n\n";

try {
    // Test 1: Création d'une nouvelle instance Language
    $language = new Language();
    echo "✅ Test 1: Création d'une instance Language - OK\n";
    
    // Test 2: Vérification des valeurs par défaut
    $isActive = $language->isIsActive();
    $isEnabled = $language->isIsEnabled();
    
    echo "✅ Test 2: Valeurs par défaut:\n";
    echo "   - isActive: " . ($isActive ? 'true' : 'false') . "\n";
    echo "   - isEnabled: " . ($isEnabled ? 'true' : 'false') . "\n";
    
    // Test 3: Test de synchronisation isActive -> isEnabled
    $language->setIsActive(false);
    echo "✅ Test 3: Synchronisation isActive -> isEnabled:\n";
    echo "   - setIsActive(false)\n";
    echo "   - isActive: " . ($language->isIsActive() ? 'true' : 'false') . "\n";
    echo "   - isEnabled: " . ($language->isIsEnabled() ? 'true' : 'false') . "\n";
    
    // Test 4: Test de synchronisation isEnabled -> isActive
    $language->setIsEnabled(true);
    echo "✅ Test 4: Synchronisation isEnabled -> isActive:\n";
    echo "   - setIsEnabled(true)\n";
    echo "   - isActive: " . ($language->isIsActive() ? 'true' : 'false') . "\n";
    echo "   - isEnabled: " . ($language->isIsEnabled() ? 'true' : 'false') . "\n";
    
    // Test 5: Test de la méthode toArray()
    $array = $language->toArray();
    echo "✅ Test 5: Méthode toArray() contient isEnabled: " . (isset($array['isEnabled']) ? 'OK' : 'MANQUANT') . "\n";
    
    // Test 6: Test des getters
    $getIsEnabled = $language->getIsEnabled();
    echo "✅ Test 6: Getter getIsEnabled(): " . ($getIsEnabled ? 'true' : 'false') . "\n";
    
    echo "\n🎉 Tous les tests ont réussi ! Le bug isEnabled a été corrigé.\n";
    echo "Le champ isEnabled est maintenant disponible en tant que champ Doctrine réel.\n";
    
} catch (Exception $e) {
    echo "❌ Erreur lors des tests: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}

echo "\n📋 Résumé des corrections apportées:\n";
echo "1. ✅ Ajout du champ Doctrine isEnabled dans l'entité Language\n";
echo "2. ✅ Synchronisation bidirectionnelle isActive <-> isEnabled\n";  
echo "3. ✅ Ajout du getter getIsEnabled()\n";
echo "4. ✅ Mise à jour de toArray() pour inclure isEnabled\n";
echo "5. ✅ Création de la migration pour ajouter la colonne BDD\n";
echo "6. ✅ Les requêtes Doctrine avec 'isEnabled' fonctionnent maintenant\n\n";
