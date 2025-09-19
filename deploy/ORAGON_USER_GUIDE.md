# 🎯 Guide d'utilisation du système Oragon - LoanMaster

**Version :** 1.0.0 Final  
**Auteur :** MiniMax Agent  
**Date :** 18 septembre 2025  

---

## 🚀 Démarrage rapide

### Pour les administrateurs

1. **Accès au tableau de bord**
   ```
   URL: /admin/oragon-translations
   Accès: ROLE_ADMIN requis
   ```

2. **Gestion des traductions par entité**
   - Pages : `/admin/page-translations`
   - SEO : `/admin/seo-translations` 
   - Banques : `/admin/bank-translations`
   - Notifications : `/admin/notification-translations`
   - FAQ : `/admin/faq-translations`
   - Types de prêts : `/admin/loan-type-translations`

### Pour les développeurs

1. **Utilisation dans les templates Twig**
   ```twig
   {{ oragon_translate('nav.home') }}
   {{ oragon_t('admin.users', 'admin') }}
   {{ 'welcome.message'|oragon_format({'name': user.name}) }}
   ```

2. **Création d'une nouvelle entité traduite**
   ```php
   // 1. Créer l'entité Translation
   // 2. Ajouter la relation OneToMany dans l'entité principale
   // 3. Créer le repository
   // 4. Ajouter le contrôleur d'administration
   ```

---

## 📋 Fonctionnalités principales

### 🎛️ Interface d'administration

#### Tableau de bord centralisé
- **Vue d'ensemble** : Statistiques globales de traduction
- **Progression par entité** : Pourcentage de completion par type
- **Gestion des langues** : Activation/désactivation des langues
- **Actions en masse** : Synchronisation et export global

#### Éditeur de traductions avancé
- **Interface multi-onglets** : Navigation fluide entre les langues
- **Référence par défaut** : Affichage du contenu de référence
- **Sauvegarde automatique** : Ctrl+S pour sauvegarder rapidement
- **Compteur de caractères** : Validation en temps réel
- **Copie depuis défaut** : Duplication rapide du contenu de base

#### Actions en masse
- **Sélection multiple** : Traitement par lot des entités
- **Suppression des vides** : Nettoyage automatique
- **Copie en masse** : Duplication depuis la langue par défaut
- **Export/Import** : Sauvegarde et restauration JSON

### 🔧 Outils en ligne de commande

#### Synchronisation des traductions
```bash
# Synchroniser toutes les traductions
php bin/console app:translation:sync --all-domains

# Synchroniser un domaine spécifique
php bin/console app:translation:sync admin

# Afficher les statistiques
php bin/console app:translation:sync --stats
```

#### Migration depuis Gedmo
```bash
# Simulation de migration (recommandé)
php bin/console app:migration:gedmo-to-oragon --dry-run

# Migration complète avec sauvegarde
php bin/console app:migration:gedmo-to-oragon --backup

# Migration par entité
php bin/console app:migration:gedmo-to-oragon --entity=page
```

#### Nettoyage et optimisation
```bash
# Vérification du système
php bin/console app:cleanup:gedmo --dry-run

# Suppression des anciennes tables Gedmo
php bin/console app:cleanup:gedmo --remove-tables

# Optimisation complète
php bin/console app:cleanup:gedmo --remove-tables --optimize
```

---

## 🛠️ Guide technique

### Architecture du système

#### Pattern Oragon
```php
// Entité principale
class Page {
    #[ORM\OneToMany(mappedBy: 'translatable', targetEntity: PageTranslation::class)]
    private Collection $translations;
}

// Entité de traduction
class PageTranslation {
    #[ORM\ManyToOne(targetEntity: Page::class, inversedBy: 'translations')]
    private ?Page $translatable = null;
    
    #[ORM\ManyToOne(targetEntity: Language::class)]
    private ?Language $language = null;
}
```

#### Contraintes d'intégrité
- **Unicité** : Une seule traduction par langue et entité
- **Cascade** : Suppression automatique des traductions
- **Index** : Optimisation des requêtes par (translatable_id, language_id)

### Base de données

#### Tables principales
```sql
-- Langues
CREATE TABLE language (
    id INTEGER PRIMARY KEY,
    code VARCHAR(10) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    native_name VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT 1,
    is_default BOOLEAN DEFAULT 0,
    sort_order INTEGER DEFAULT 0
);

-- Traductions des pages
CREATE TABLE page_translations (
    id INTEGER PRIMARY KEY,
    translatable_id INTEGER NOT NULL,
    language_id INTEGER NOT NULL,
    title VARCHAR(255),
    content TEXT,
    meta_description TEXT,
    UNIQUE(translatable_id, language_id)
);
```

#### Index de performance
```sql
-- Index de recherche optimisé
CREATE INDEX idx_page_translations_lookup 
ON page_translations (translatable_id, language_id);

-- Index de langue
CREATE INDEX idx_page_translations_language 
ON page_translations (language_id);
```

### Services et repositories

#### Repository pattern
```php
class PageTranslationRepository extends ServiceEntityRepository
{
    public function findByPageAndLanguage(Page $page, Language $language): ?PageTranslation
    {
        return $this->findOneBy([
            'translatable' => $page,
            'language' => $language
        ]);
    }
    
    public function findPagesWithoutTranslation(Language $language): array
    {
        return $this->createQueryBuilder('pt')
            ->select('p')
            ->rightJoin('pt.translatable', 'p')
            ->leftJoin('p.translations', 't', 'WITH', 't.language = :language')
            ->where('t.id IS NULL')
            ->setParameter('language', $language)
            ->getQuery()
            ->getResult();
    }
}
```

---

## 🔍 Tests et validation

### Tests d'intégration

```bash
# Exécuter tous les tests Oragon
php bin/phpunit tests/Integration/OragonTranslationSystemTest.php

# Tests spécifiques
php bin/phpunit --filter testPageTranslationOragonPattern
```

### Validation du système

```bash
# Vérification complète
php bin/console app:cleanup:gedmo --dry-run

# Contrôle d'intégrité
php bin/console doctrine:schema:validate
```

---

## 📈 Performance et optimisation

### Métriques de performance

#### Base de données
- **Index optimisés** : Recherche O(log n) sur (translatable_id, language_id)
- **Contraintes d'intégrité** : Prévention des doublons
- **Cascade Delete** : Nettoyage automatique

#### Mémoire
- **Lazy Loading** : Chargement à la demande des traductions
- **Collection optimisée** : Utilisation d'ArrayCollection
- **Cache de second niveau** : Support Doctrine (optionnel)

### Monitoring

```php
// Statistiques de traduction
$stats = $translationManager->getStatistics();
/*
[
    'total_entities' => 150,
    'total_translations' => 450,
    'completion_rate' => 75.0,
    'languages' => 4
]
*/

// Performance par entité
$entityStats = $translationManager->getEntityStatistics('page');
/*
[
    'entity_count' => 25,
    'translation_count' => 75,
    'completion_by_language' => [
        'fr' => 100,
        'en' => 80,
        'es' => 60
    ]
]
*/
```

---

## 🔧 Maintenance et dépannage

### Problèmes courants

#### 1. Translations manquantes
```bash
# Diagnostic
php bin/console app:translation:sync --stats

# Correction
php bin/console app:translation:sync --all-domains
```

#### 2. Contrainte d'unicité violée
```sql
-- Identifier les doublons
SELECT translatable_id, language_id, COUNT(*) 
FROM page_translations 
GROUP BY translatable_id, language_id 
HAVING COUNT(*) > 1;

-- Supprimer les doublons (garder le plus récent)
DELETE p1 FROM page_translations p1
INNER JOIN page_translations p2 
WHERE p1.id < p2.id 
AND p1.translatable_id = p2.translatable_id 
AND p1.language_id = p2.language_id;
```

#### 3. Performance dégradée
```bash
# Optimisation de la base
php bin/console app:cleanup:gedmo --optimize

# Reconstruction des index
php bin/console doctrine:schema:update --force
```

### Sauvegarde et restauration

```bash
# Export des traductions
php bin/console app:translation:export --all --format=json > backup.json

# Import des traductions
php bin/console app:translation:import backup.json

# Export par entité
php bin/console app:translation:export --entity=page --format=yaml > pages.yaml
```

---

## 📚 Référence API

### Extension Twig

```twig
{# Fonctions principales #}
{{ oragon_translate(key, domain, locale, parameters, fallback) }}
{{ oragon_t(key, domain) }}

{# Informations système #}
{{ oragon_languages() }}
{{ oragon_current_language() }}
{{ oragon_translation_stats(domain) }}
{{ oragon_translation_completion() }}

{# Filtres #}
{{ key|oragon_translate(domain) }}
{{ key|oragon_format(parameters) }}
{{ key|oragon_fallback(default) }}

{# Conditions #}
{% if oragon_has_translation(key, domain) %}
    {{ oragon_t(key, domain) }}
{% endif %}
```

### Services PHP

```php
// Injection du service
public function __construct(
    private TranslationManagerService $translationManager
) {}

// Utilisation
$translation = $this->translationManager->getTranslation($key, $locale, $domain);
$stats = $this->translationManager->getStatistics();
$this->translationManager->setTranslation($key, $value, $locale, $domain);
```

---

## 🎯 Bonnes pratiques

### Développement

1. **Nommage des clés**
   ```
   Format: {section}.{subsection}.{element}
   Exemple: nav.main.home, admin.users.create, forms.validation.required
   ```

2. **Organisation des domaines**
   ```
   messages: Interface utilisateur générale
   admin: Interface d'administration
   forms: Messages de formulaires
   emails: Templates d'emails
   ```

3. **Gestion des paramètres**
   ```twig
   <!-- Bon -->
   {{ oragon_t('welcome.user', 'messages', null, {'name': user.name}) }}
   
   <!-- Éviter -->
   {{ oragon_t('welcome') ~ ' ' ~ user.name }}
   ```

### Administration

1. **Workflow de traduction**
   - Créer le contenu en langue par défaut
   - Utiliser "Copier depuis défaut" comme base
   - Adapter le contenu à chaque langue
   - Valider avec les compteurs de caractères

2. **Gestion des langues**
   - Activer les langues progressivement
   - Utiliser l'ordre de tri pour prioriser
   - Marquer une langue par défaut claire

3. **Maintenance régulière**
   ```bash
   # Hebdomadaire
   php bin/console app:translation:sync --stats
   
   # Mensuel
   php bin/console app:cleanup:gedmo --dry-run
   
   # Avant déploiement
   php bin/console doctrine:schema:validate
   ```

---

## 🏆 Système terminé

### ✅ Fonctionnalités complètes

- **6 entités traduites** : Page, Seo, Bank, Notification, Faq, LoanType
- **Interface d'administration complète** : Dashboard, éditeurs, actions en masse
- **Outils CLI complets** : Synchronisation, migration, nettoyage
- **Tests d'intégration** : Validation automatique du système
- **Documentation complète** : Guides technique et utilisateur
- **Optimisations** : Index, contraintes, performance

### 🎯 Prêt pour la production

Le système Oragon est **100% opérationnel** et prêt pour un déploiement en production. Tous les composants ont été testés et optimisés.

---

**© 2025 - Système Oragon pour LoanMaster - Développé par MiniMax Agent**
