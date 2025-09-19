# Documentation Sécurité Avancée - LoanMaster

## Vue d'ensemble

LoanMaster implémente une sécurité de niveau bancaire avec authentification multi-facteur obligatoire, chiffrement avancé et audit complet.

## Authentification JWT

### Configuration
- **Algorithme** : RS256 (Clés asymmétriques)
- **Durée Access Token** : 1 heure
- **Durée Refresh Token** : 30 jours
- **Rotation automatique** : Oui

### Génération des clés
```bash
# Générer la clé privée
openssl genpkey -out config/jwt/private.pem -aes256 -algorithm rsa -pkeyopt rsa_keygen_bits:4096

# Générer la clé publique
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout
```

### Headers requis
```
Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9...
Content-Type: application/json
```

## Authentification Double Facteur (2FA)

### Méthodes supportées
1. **Google Authenticator** (TOTP)
2. **Authentificateur TOTP** générique
3. **Codes de secours** (10 codes uniques)

### Activation 2FA
```bash
# Activer la 2FA
POST /api/2fa/enable
{
  "method": "google"
}

# Réponse
{
  "success": true,
  "data": {
    "qrCode": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAA...",
    "backupCodes": ["1234-5678", "9012-3456", ...],
    "method": "google"
  }
}
```

### Vérification 2FA
```bash
POST /api/2fa/verify
{
  "code": "123456"
}
```

### Politiques 2FA
- **ROLE_ADMIN** : 2FA obligatoire
- **ROLE_LOAN_OFFICER** : 2FA obligatoire
- **ROLE_USER** : 2FA optionnelle

## Contrôle d'accès granulaire

### Hiérarchie des rôles
```yaml
ROLE_USER:
  - Accès lecture à ses propres prêts
  - Création de demandes de prêt
  - Modification de ses prêts en attente

ROLE_LOAN_OFFICER:
  - Tout ROLE_USER
  - Examen des demandes de prêt
  - Approbation/rejet des prêts
  - Accès aux prêts en cours d'examen

ROLE_ADMIN:
  - Tout ROLE_LOAN_OFFICER
  - Financement des prêts
  - Gestion des utilisateurs
  - Accès aux logs de sécurité
  - Configuration système
```

### Permissions spécifiques
```php
// Voters de sécurité
loan.view     // Consulter un prêt
loan.edit     // Modifier un prêt
loan.approve  // Approuver un prêt
loan.fund     // Financer un prêt
loan.admin    // Accès administrateur
```

## Protection contre les attaques

### Rate Limiting
- **Connexions** : 5 tentatives / 15 minutes par IP
- **Connexions** : 5 tentatives / 15 minutes par email
- **API** : 100 requêtes / minute par utilisateur
- **API publique** : 20 requêtes / minute par IP

### Sécurité des mots de passe
- **Longueur minimale** : 12 caractères
- **Complexité** : Majuscules, minuscules, chiffres, caractères spéciaux
- **Vérification fuite** : HaveIBeenPwned API
- **Historique** : Pas de réutilisation des 12 derniers mots de passe

### Protection CSRF
- **Tokens CSRF** : Obligatoires pour toutes les formes web
- **SameSite cookies** : Configuration stricte
- **Headers sécurité** : CSP, HSTS, X-Frame-Options

## Audit et logging

### Événements audités
```php
// Authentification
- Connexion réussie
- Échec de connexion
- Déconnexion
- Activation/désactivation 2FA

// Actions métier
- Création de prêt
- Modification de statut
- Accès aux données sensibles

// Administration
- Modification de permissions
- Export de données
- Accès aux logs
```

### Format des logs
```json
{
  "timestamp": "2025-09-18T10:30:00Z",
  "level": "info",
  "channel": "security",
  "message": "Successful login",
  "context": {
    "user_id": "123e4567-e89b-12d3-a456-426614174000",
    "ip": "192.168.1.100",
    "user_agent": "Mozilla/5.0...",
    "session_id": "sess_abc123"
  }
}
```

## Chiffrement et hachage

### Données au repos
- **Algorithme** : AES-256-GCM
- **Gestion des clés** : AWS KMS / Azure Key Vault
- **Rotation** : Automatique tous les 90 jours

### Données en transit
- **TLS** : Version 1.3 minimum
- **Perfect Forward Secrecy** : Obligatoire
- **Certificate Pinning** : Application mobile

### Hachage des mots de passe
```php
// Configuration Symfony
security:
    password_hashers:
        App\Entity\User:
            algorithm: sodium
            memory_cost: 65536      # 64 MB
            time_cost: 4           # 4 itérations
            threads: 3             # 3 threads
```

## Conformité et réglementations

### RGPD
- **Minimisation des données** : Collecte strictement nécessaire
- **Droit à l'oubli** : Suppression automatique après 7 ans
- **Portabilité** : Export des données en JSON
- **Consentement** : Traçabilité complète

### DSP2 (Services de paiement)
- **Authentification forte** : 2FA obligatoire
- **Audit trail** : Traçabilité complète
- **Notification** : Alertes temps réel

## Incident Response

### Procédures automatisées
1. **Détection** : Monitoring en temps réel
2. **Isolation** : Blocage automatique des IPs suspectes
3. **Notification** : Alertes équipe sécurité
4. **Investigation** : Collecte automatique des preuves
5. **Recovery** : Procédures de restauration

### Contacts d'urgence
- **Équipe sécurité** : security@loanmaster.com
- **DPO** : dpo@loanmaster.com
- **Direction** : incident@loanmaster.com

## Tests de sécurité

### Tests automatisés
```bash
# Tests de sécurité
php bin/phpunit tests/Security/

# Analyse statique
php bin/console security:check

# Scan des dépendances
composer audit
```

### Pentest réguliers
- **Fréquence** : Trimestrielle
- **Scope** : Application + infrastructure
- **Standards** : OWASP Top 10, SANS
