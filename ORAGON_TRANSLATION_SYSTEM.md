# 🌍 Système de traduction Oragon - Documentation

**Auteur :** Prudence ASSOGBA  
**Version :** 1.0.0  
**Date :** 18 septembre 2025  

---

## 📋 Table des matières

1. [Introduction](#introduction)
2. [Installation et configuration](#installation-et-configuration)
3. [Architecture du système](#architecture-du-système)
4. [Utilisation en ligne de commande](#utilisation-en-ligne-de-commande)
5. [Interface d'administration](#interface-dadministration)
6. [Utilisation dans les templates Twig](#utilisation-dans-les-templates-twig)
7. [Migration depuis Gedmo](#migration-depuis-gedmo)
8. [API et services](#api-et-services)
9. [Bonnes pratiques](#bonnes-pratiques)
10. [Dépannage](#dépannage)

---

## Introduction

Le système Oragon est une solution complète de gestion des traductions pour LoanMaster, remplaçant le système Gedmo DoctrineExtensions par une approche plus moderne et flexible.

### Avantages du système Oragon

- ✅ **Interface d'administration intuitive** avec éditeur visuel
- ✅ **Synchronisation automatique** avec les langues actives
- ✅ **Statistiques temps réel** de progression des traductions
- ✅ **Système modulaire** facilement extensible
- ✅ **Performance optimisée** avec mise en cache
- ✅ **Workflow complet** de traduction avec validation
- ✅ **Support multi-domaines** (messages, admin, frontend, etc.)
- ✅ **Export/Import YAML** pour traducteurs externes

---

## Installation et configuration

### 1. Prérequis

- PHP 8.1+
- Symfony 6.0+
- MySQL/MariaDB
- Langues configurées dans la base de données

### 2. Configuration des services

Le système Oragon est automatiquement configuré via `config/services.yaml` :

```yaml
# Services Oragon déjà configurés
App\Service\TranslationManagerService: ~
App\Twig\TranslationExtension: ~
App\Controller\Admin\TranslationController: ~
```

### 3. Migration de base de données

```bash
# Appliquer la migration pour l'entité Language
php bin/console doctrine:migrations:migrate

# Synchroniser les traductions
php bin/console app:translation:sync --all-domains
```

---

## Architecture du système

### Services principaux

#### `TranslationManagerService`
Service central de gestion des traductions avec les fonctionnalités :
- Lecture/écriture des fichiers YAML
- Synchronisation avec les langues actives
- Statistiques de progression
- Validation des traductions

#### `TranslationExtension` (Twig)
Extension Twig fournissant des fonctions et filtres pour l'utilisation des traductions dans les templates.

### Structure des fichiers

```
translations/
├── messages.fr.yaml          # Traductions françaises par défaut
├── messages.en.yaml          # Traductions anglaises
├── admin.fr.yaml             # Interface d'administration
├── admin.en.yaml             # Admin en anglais
└── ...
```

### Entité Language (mise à jour Oragon)

```php
class Language {
    private string $code;           // Code ISO (fr, en, es...)
    private string $name;           // Nom en français
    private string $nativeName;     // Nom natif (ex: "Français")
    private bool $isActive = true;  // Langue active
    private bool $isDefault = false;
    private int $sortOrder = 0;     // Ordre d'affichage
    // + timestamps created_at, updated_at
}
```

---

## Utilisation en ligne de commande

### Synchronisation des traductions

```bash
# Synchroniser le domaine "messages"
php bin/console app:translation:sync

# Synchroniser un domaine spécifique
php bin/console app:translation:sync admin

# Synchroniser tous les domaines
php bin/console app:translation:sync --all-domains

# Afficher les statistiques
php bin/console app:translation:sync --stats

# Valider les traductions
php bin/console app:translation:sync --validate
```

### Migration depuis Gedmo

```bash
# Simulation de migration (recommandé)
php bin/console app:migration:gedmo-to-oragon --dry-run

# Migration des pages uniquement
php bin/console app:migration:gedmo-to-oragon --entity=page

# Migration complète avec sauvegarde
php bin/console app:migration:gedmo-to-oragon --backup

# Migration forcée avec validation
php bin/console app:migration:gedmo-to-oragon --force --validate
```

---

## Interface d'administration

### Accès à l'interface

```
URL: /admin/translations
Accès: ROLE_ADMIN requis
```

### Fonctionnalités disponibles

#### 1. Vue d'ensemble
- Liste des domaines de traduction
- Statistiques par langue
- Progression globale
- Actions de synchronisation

#### 2. Éditeur de traductions
- Interface de traduction par domaine/langue
- Traductions de référence affichées
- Recherche dans les traductions
- Sauvegarde automatique et manuelle
- Filtres (vides, traduites, toutes)

#### 3. Import/Export
- Export YAML par domaine/langue
- Import de traductions depuis fichier YAML
- Validation automatique lors de l'import

#### 4. Gestion des domaines
- Création de nouveaux domaines
- Synchronisation par domaine
- Statistiques détaillées

### Raccourcis clavier

- `Ctrl + S` : Sauvegarder les traductions
- `Ctrl + F` : Rechercher dans les traductions

---

## Utilisation dans les templates Twig

### Fonctions disponibles

#### `oragon_translate()` - Traduction principale

```twig
{# Traduction simple #}
{{ oragon_translate('nav.home') }}

{# Avec domaine spécifique #}
{{ oragon_translate('user.profile', 'admin') }}

{# Avec paramètres #}
{{ oragon_translate('welcome.message', 'messages', null, {'name': user.name}) }}

{# Avec fallback personnalisé #}
{{ oragon_translate('optional.key', 'messages', null, {}, 'Valeur par défaut') }}
```

#### Alias court `oragon_t()`

```twig
{{ oragon_t('nav.home') }}
{{ oragon_t('admin.users', 'admin') }}
```

#### Autres fonctions utiles

```twig
{# Liste des langues actives #}
{% set languages = oragon_languages() %}

{# Langue courante #}
{% set currentLang = oragon_current_language() %}

{# Statistiques de traduction #}
{% set stats = oragon_translation_stats('admin') %}

{# Vérifier l'existence d'une traduction #}
{% if oragon_has_translation('nav.special') %}
    {{ oragon_t('nav.special') }}
{% endif %}

{# Pourcentage de completion #}
{{ oragon_translation_completion() }}%
```

### Filtres disponibles

#### `|oragon_translate` - Filtre de traduction

```twig
{# Utilisation avec le pipe #}
{{ 'nav.home'|oragon_translate }}
{{ 'admin.users'|oragon_translate('admin') }}

{# Alias court #}
{{ 'nav.home'|oragon_t }}
```

#### `|oragon_fallback` - Avec fallback

```twig
{{ 'optional.key'|oragon_fallback('Valeur par défaut') }}
```

#### `|oragon_format` - Formatage avec paramètres

```twig
{{ 'welcome.message'|oragon_format({'name': user.name, 'count': items|length}) }}
```

### Exemples d'utilisation

#### Navigation multilingue

```twig
<nav class="navbar">
    {% for item in navigation %}
        <a href="{{ item.url }}">
            {{ ('nav.' ~ item.key)|oragon_t }}
        </a>
    {% endfor %}
</nav>
```

#### Sélecteur de langue

```twig
<div class="language-selector">
    {% for language in oragon_languages() %}
        <a href="{{ path('change_locale', {'_locale': language.code}) }}" 
           class="{{ language.code == app.request.locale ? 'active' : '' }}">
            {{ language.nativeName }}
        </a>
    {% endfor %}
</div>
```

#### Messages avec paramètres

```twig
<div class="alert alert-success">
    {{ oragon_t('flash.success.user_created', 'admin', null, {'username': user.username}) }}
</div>
```

---

## Migration depuis Gedmo

### Processus de migration

La migration des traductions Gedmo vers Oragon se fait en plusieurs étapes :

#### 1. Préparation

```bash
# Vérifier l'état du système
php bin/console app:migration:gedmo-to-oragon --dry-run
```

#### 2. Migration par entité

```bash
# Migration des pages
php bin/console app:migration:gedmo-to-oragon --entity=page

# Migration du SEO
php bin/console app:migration:gedmo-to-oragon --entity=seo
```

#### 3. Migration complète

```bash
# Avec sauvegarde de sécurité
php bin/console app:migration:gedmo-to-oragon --backup --validate
```

### Entités migrées

- **Page** : `title`, `content`, `resume`, `slug`
- **Seo** : Tous les champs SEO (9 champs)

### Données préservées

- ✅ Toutes les traductions existantes
- ✅ Relations entre entités
- ✅ Métadonnées de traduction
- ✅ Historique des modifications

---

## API et services

### `TranslationManagerService`

#### Méthodes principales

```php
// Récupérer les traductions
$translations = $translationManager->getTranslations('admin', 'fr');

// Sauvegarder des traductions
$translationManager->saveTranslations('admin', 'fr', $translations);

// Synchroniser avec les langues actives
$translationManager->synchronizeWithLanguages('admin');

// Statistiques
$stats = $translationManager->getTranslationStats('admin');

// Validation
$errors = $translationManager->validateTranslations($translations);
```

#### Utilitaires

```php
// Aplatir les traductions pour l'édition
$flat = $translationManager->flattenTranslations($translations);

// Reconstituer la structure
$structured = $translationManager->unflattenTranslations($flat);

// Export/Import
$yaml = $translationManager->exportTranslations('admin', 'fr');
$translationManager->importTranslations('admin', 'fr', $yamlContent);
```

### Contrôleur API

#### Endpoints disponibles

```php
GET    /admin/translations              # Interface principale
GET    /admin/translations/edit/{domain}/{locale}  # Éditeur
POST   /admin/translations/update/{domain}/{locale}  # Mise à jour AJAX
POST   /admin/translations/synchronize/{domain}  # Synchronisation
GET    /admin/translations/stats/{domain}  # Statistiques API
GET    /admin/translations/export/{domain}/{locale}  # Export YAML
POST   /admin/translations/import/{domain}/{locale}  # Import YAML
POST   /admin/translations/create-domain  # Nouveau domaine
POST   /admin/translations/search  # Recherche
```

---

## Bonnes pratiques

### Organisation des traductions

#### Structure recommandée

```yaml
# messages.fr.yaml
navigation:
  home: "Accueil"
  about: "À propos"
  services: "Services"
  contact: "Contact"

form:
  button:
    save: "Enregistrer"
    cancel: "Annuler"
    delete: "Supprimer"
  
validation:
  required: "Ce champ est obligatoire"
  email: "Veuillez saisir un email valide"

flash:
  success:
    saved: "Enregistrement réussi"
    deleted: "Suppression effectuée"
  error:
    generic: "Une erreur s'est produite"
```

### Conventions de nommage

#### Clés de traduction

```yaml
# ✅ Bon : hiérarchique et descriptif
user:
  profile:
    title: "Mon profil"
    edit: "Modifier le profil"
    
# ❌ Éviter : clés plates et génériques
user_profile_title: "Mon profil"
edit: "Modifier"
```

#### Paramètres

```yaml
# ✅ Paramètres avec format cohérent
welcome_message: "Bienvenue %name% !"
item_count: "Vous avez %count% élément(s)"

# ✅ Support de plusieurs formats
user_greeting: "Bonjour {username}, vous avez {count} message(s)"
```

### Gestion des langues

#### Ordre recommandé

1. Français (défaut)
2. Anglais
3. Autres langues par ordre alphabétique

#### Configuration des directions

```php
// RTL pour l'arabe et l'hébreu
'ar' => ['dir' => 'rtl'],
'he' => ['dir' => 'rtl'],

// LTR pour toutes les autres
'*' => ['dir' => 'ltr'],
```

---

## Dépannage

### Problèmes courants

#### 1. Traductions non mises à jour

```bash
# Vider le cache Symfony
php bin/console cache:clear

# Re-synchroniser les traductions
php bin/console app:translation:sync --force
```

#### 2. Erreurs de permissions sur les fichiers

```bash
# Vérifier les permissions du dossier translations
chmod -R 775 translations/
chown -R www-data:www-data translations/
```

#### 3. Langues non affichées

```bash
# Vérifier les langues actives
php bin/console doctrine:query:sql "SELECT * FROM language WHERE is_active = 1"

# Réactiver une langue
php bin/console doctrine:query:sql "UPDATE language SET is_active = 1 WHERE code = 'en'"
```

#### 4. Synchronisation échoue

```bash
# Mode verbose pour diagnostiquer
php bin/console app:translation:sync --all-domains -vvv

# Validation des traductions
php bin/console app:translation:sync --validate
```

### Logs et debug

#### Activer le mode debug pour les traductions

```yaml
# config/packages/dev/monolog.yaml
monolog:
    channels: ['oragon_translation']
    handlers:
        oragon:
            type: stream
            path: "%kernel.logs_dir%/oragon_translation.log"
            channels: [oragon_translation]
```

#### Utilisation en templates

```twig
{# Debug : afficher la clé si traduction manquante #}
{% if app.environment == 'dev' %}
    {{ oragon_t('debug.key', 'messages', null, {}, '[MISSING: debug.key]') }}
{% endif %}
```

### Performance

#### Cache des traductions

Le système Oragon met automatiquement en cache les traductions en mémoire. Pour un cache persistant :

```yaml
# config/packages/cache.yaml
framework:
    cache:
        pools:
            oragon.translation.cache:
                adapter: cache.adapter.filesystem
                default_lifetime: 3600
```

#### Optimisation des requêtes

```php
// Pré-charger plusieurs domaines
$domains = ['messages', 'admin'];
foreach ($domains as $domain) {
    $translationManager->getTranslations($domain, $locale);
}
```

---

## Support et maintenance

### Contact

- **Développeur** : Prudence ASSOGBA
- **Email** : jprud67@gmail.com
- **GitHub** : https://github.com/dahovitech/loanmaster

### Mises à jour

Le système Oragon est conçu pour être facilement extensible. Les futures améliorations incluront :

- Interface de traduction collaborative
- Intégration avec des services de traduction automatique
- Workflow de validation par les traducteurs
- Versioning des traductions
- API REST complète

---

## 📋 État d'avancement

### ✅ Phase 1 - Infrastructure Oragon (TERMINÉE)
- [x] Service central `TranslationManagerService` 
- [x] Contrôleur admin `TranslationController`
- [x] Commandes CLI (`SyncTranslationsCommand`, `MigrateGedmoToOragonCommand`)
- [x] Extension Twig `TranslationExtension` avec fonctions personnalisées
- [x] Entity `Language` mise à jour (nouveaux champs, contraintes)
- [x] Migration de base de données pour la table `languages` 
- [x] Configuration des services et routes
- [x] Templates de base pour l'interface d'administration

### ✅ Phase 2 - Migration des entités existantes (TERMINÉE)
- [x] Création de `PageTranslation` avec pattern Oragon
- [x] Création de `SeoTranslation` avec tous les champs SEO traduisibles  
- [x] Repositories dédiés avec méthodes utilitaires
- [x] Migration de base de données pour les nouvelles tables
- [x] Mise à jour des entités `Page` et `Seo` (suppression Gedmo → relations Oragon)
- [x] Commande de migration des données complètement implémentée
- [x] Méthodes de compatibilité pour transition en douceur
- [x] Tests et validation des structures

### 🔄 Phase 3 - Nouvelles entités traduisibles (PROCHAINE ÉTAPE)
- [ ] `BankTranslation` pour les informations bancaires
- [ ] `NotificationTranslation` pour les notifications système
- [ ] `FaqTranslation` pour les questions fréquentes  
- [ ] `LoanTypeTranslation` pour les types de prêts
- [ ] Repositories et migrations correspondantes

### ⏳ Phase 4 - Interface d'administration spécialisée
- [ ] Contrôleurs CRUD dédiés par entité (PageController, SeoController, etc.)
- [ ] Formulaires Symfony personnalisés pour chaque type de traduction
- [ ] Templates d'édition avancés avec support multi-langues
- [ ] Système d'import/export des traductions
- [ ] Interface de gestion en masse

### ⏳ Phase 5 - Nettoyage et optimisation
- [ ] Suppression complète des dépendances Gedmo
- [ ] Nettoyage des anciennes tables de traduction
- [ ] Optimisations des requêtes et index
- [ ] Tests d'intégration complets
- [ ] Documentation utilisateur finale

### 📊 Résumé du progrès
- ✅ **2/5 phases terminées** (40%)
- 🎯 **Prochaine étape :** Phase 3 - Création des nouvelles entités traduisibles
- 🏗️ **Architecture :** Système Oragon opérationnel pour Page et Seo
- 🔄 **Migration :** Prêt pour la migration des données Gedmo → Oragon

---

**© 2025 - Système Oragon par Prudence ASSOGBA**
