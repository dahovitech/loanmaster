# Système de Conformité RGPD et Audit Trail

## Vue d'ensemble

Le système de conformité RGPD et audit trail de LoanMaster fournit une solution complète pour :
- **Audit Trail automatique** : Journalisation de toutes les actions importantes
- **Gestion des consentements RGPD** : Suivi et gestion des consentements utilisateurs
- **Conformité réglementaire** : Respect des exigences RGPD
- **Dashboard de conformité** : Interface d'administration centralisée

## Architecture

### Composants principaux

1. **AuditLoggerService** : Service central pour l'enregistrement des logs d'audit
2. **GDPRService** : Service de gestion des consentements et conformité RGPD
3. **AuditTrailSubscriber** : Subscriber automatique pour l'audit des événements
4. **ComplianceDashboardController** : Interface d'administration
5. **GDPRController** : API REST pour les demandes RGPD utilisateurs

### Entités de base de données

- **AuditLog** : Stockage des logs d'audit
- **UserConsent** : Gestion des consentements utilisateurs

## Fonctionnalités

### 1. Audit Trail Automatique

#### Types d'événements audités :
- Connexions/déconnexions
- Modifications d'entités (CRUD)
- Actions administratives
- Événements RGPD
- Requêtes HTTP sensibles
- Exceptions système

#### Configuration de l'audit :
```yaml
# config/services_audit_gdpr.yaml
services:
    App\Service\Audit\AuditLoggerService:
        arguments:
            $auditEnabled: '%env(bool:AUDIT_ENABLED)%'
```

#### Utilisation manuelle :
```php
// Dans un contrôleur ou service
$this->auditLogger->log(
    'user_action',
    'User',
    $user->getId(),
    $oldData,
    $newData,
    'Utilisateur mis à jour',
    AuditLoggerService::SEVERITY_MEDIUM
);
```

### 2. Gestion des Consentements RGPD

#### Types de consentements supportés :
- `data_processing` : Traitement des données personnelles
- `marketing` : Communications marketing
- `analytics` : Analyses et statistiques
- `cookies` : Cookies non-essentiels
- `profiling` : Profilage utilisateur
- `data_sharing` : Partage avec des tiers
- `automated_decision` : Décisions automatisées

#### API des consentements :
```http
POST /api/gdpr/consents/grant
{
    "consentType": "marketing",
    "consentText": "J'accepte de recevoir des communications marketing",
    "durationDays": 365
}

POST /api/gdpr/consents/withdraw
{
    "consentType": "marketing",
    "reason": "Je ne souhaite plus recevoir d'emails"
}

GET /api/gdpr/consents
# Retourne tous les consentements de l'utilisateur connecté
```

### 3. Droits RGPD

#### Droit d'accès :
```http
POST /api/gdpr/data-export
{
    "entityTypes": ["User", "LoanApplication"]
}
```

#### Droit à l'effacement :
```http
POST /api/gdpr/data-deletion
# Crée une demande de suppression
```

#### Droit à la portabilité :
```http
POST /api/gdpr/data-portability
{
    "format": "json",
    "entityTypes": ["User"]
}
```

## Configuration

### Variables d'environnement

Copier le fichier `.env.audit_gdpr.example` et ajuster les valeurs :

```bash
# Activation de l'audit
AUDIT_ENABLED=true
AUDIT_DOCTRINE_ENABLED=true
AUDIT_REQUESTS_ENABLED=false

# Entités à auditer (vide = toutes)
AUDIT_ENTITIES=[]

# Consentements requis
GDPR_REQUIRED_CONSENTS=["data_processing", "cookies"]

# Rétention des données (jours)
AUDIT_RETENTION_DAYS=365
GDPR_RETENTION_DEFAULT=2555
```

### Routes

Les routes sont automatiquement chargées depuis :
- `config/routes/compliance.yaml`

### Services

Services configurés dans :
- `config/services_audit_gdpr.yaml`

## Dashboard d'Administration

### Accès
URL : `/admin/compliance/`
Rôle requis : `ROLE_ADMIN`

### Fonctionnalités du dashboard :
- **Vue d'ensemble** : Métriques et statistiques
- **Logs d'audit** : Consultation et filtrage des logs
- **Gestion des consentements** : Suivi des consentements
- **Demandes RGPD** : Traitement des demandes utilisateurs
- **Statistiques** : Analyses détaillées
- **Export** : Export CSV/JSON des données

## Commandes Console

### Nettoyage automatique
```bash
# Nettoyer les anciens logs (365 jours par défaut)
php bin/console app:audit:cleanup

# Nettoyage avec paramètres personnalisés
php bin/console app:audit:cleanup --audit-retention-days=90 --consent-retention-days=1000

# Simulation (dry-run)
php bin/console app:audit:cleanup --dry-run
```

### Traitement des consentements expirés
```bash
# Traiter les consentements expirés
php bin/console app:gdpr:process-expired-consents

# Avec notifications par email
php bin/console app:gdpr:process-expired-consents --send-notifications

# Notification 15 jours avant expiration
php bin/console app:gdpr:process-expired-consents --notification-days=15
```

## Sécurité et Performance

### Données sensibles
- Les mots de passe et tokens sont automatiquement masqués dans les logs
- Les données personnelles sont chiffrées si nécessaire
- Accès restreint par rôles utilisateurs

### Performance
- Index optimisés sur les tables d'audit
- Requêtes paginées dans l'interface
- Nettoyage automatique des anciens logs
- Option de compression des logs archivés

### Anonymisation
```php
// Anonymiser les données d'un utilisateur
$result = $this->gdprService->anonymizeUserData($userId);
```

## Personnalisation

### Ajouter de nouveaux types de consentement

1. Modifier `UserConsent::getAvailableTypes()`
2. Ajouter les descriptions dans `UserConsent::getTypeDescription()`
3. Mettre à jour `GDPR_REQUIRED_CONSENTS` si nécessaire

### Étendre l'audit automatique

```php
// Dans un EventSubscriber personnalisé
public function onCustomEvent(CustomEvent $event): void
{
    $this->auditLogger->log(
        'custom_action',
        'CustomEntity',
        $event->getEntityId(),
        null,
        $event->getData(),
        'Action personnalisée exécutée'
    );
}
```

## Conformité Réglementaire

### RGPD
- ✅ Droit d'accès (Article 15)
- ✅ Droit de rectification (Article 16)
- ✅ Droit à l'effacement (Article 17)
- ✅ Droit à la portabilité (Article 20)
- ✅ Droit d'opposition (Article 21)
- ✅ Consentement éclairé (Article 7)
- ✅ Audit trail (Article 30)

### Durées de conservation
- Logs d'audit : 1 an par défaut
- Consentements actifs : 7 ans maximum
- Données personnelles : selon la base légale

## Monitoring et Alertes

### Événements critiques
- Échecs de connexion répétés
- Modifications d'entités sensibles
- Erreurs système
- Violations potentielles de données

### Notifications automatiques
- Consentements expirant bientôt
- Demandes RGPD en attente
- Anomalies dans les logs
- Seuils de sécurité dépassés

## Maintenance

### Tâches régulières recommandées :
1. Exécution quotidienne : traitement des consentements expirés
2. Exécution hebdomadaire : nettoyage des anciens logs
3. Exécution mensuelle : audit de conformité
4. Sauvegarde régulière : export des logs critiques

### Cron jobs suggérés :
```bash
# Traitement quotidien des consentements
0 2 * * * php /path/to/app/bin/console app:gdpr:process-expired-consents

# Nettoyage hebdomadaire
0 3 * * 0 php /path/to/app/bin/console app:audit:cleanup
```

## Dépannage

### Problèmes courants

1. **Logs non créés** : Vérifier `AUDIT_ENABLED=true`
2. **Performance dégradée** : Activer la compression ou réduire la rétention
3. **Erreurs de consentement** : Vérifier la configuration des types requis
4. **Export échoue** : Vérifier les permissions et l'espace disque

### Debug
```bash
# Activer le mode debug pour l'audit
AUDIT_DEBUG_MODE=true

# Vérifier les logs
tail -f var/log/audit.log
tail -f var/log/gdpr.log
```

## Migration et Mise à jour

### Installation sur un projet existant :
```bash
# Exécuter les migrations
php bin/console doctrine:migrations:migrate

# Charger la configuration
# Copier les fichiers de config dans config/
# Ajouter les variables d'environnement

# Première synchronisation
php bin/console app:audit:cleanup --dry-run
```

Cette documentation fournit un guide complet pour utiliser et maintenir le système de conformité RGPD et audit trail de LoanMaster.
