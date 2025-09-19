# API REST LoanMaster - Documentation

## Vue d'ensemble

L'API REST LoanMaster offre une interface moderne et sécurisée pour la gestion des prêts financiers. Elle suit les standards REST et utilise API Platform v3 pour une documentation automatique et des fonctionnalités avancées.

## Endpoints Principaux

### Authentification

#### POST /api/auth/login
Authentification utilisateur avec JWT
```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

#### POST /api/auth/refresh
Renouvellement du token JWT
```json
{
  "refresh_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiJ9..."
}
```

#### GET /api/auth/me
Informations de l'utilisateur connecté

### Prêts (Loans)

#### GET /api/loans
Liste des prêts (paginée)
- **Paramètres** : `page`, `limit`, `userId` (admin seulement)
- **Filtres** : Par statut, type, montant
- **Sécurité** : ROLE_USER (ses prêts), ROLE_ADMIN (tous)

#### POST /api/loans
Création d'une demande de prêt
```json
{
  "loanType": "personal",
  "amount": 15000,
  "durationMonths": 36,
  "projectDescription": "Achat d'une voiture"
}
```

#### GET /api/loans/{id}
Détails d'un prêt spécifique
- **Sécurité** : Propriétaire ou ROLE_ADMIN

#### PATCH /api/loans/{id}
Mise à jour du statut d'un prêt
```json
{
  "action": "approve",
  "actionComment": "Dossier complet, revenus suffisants"
}
```

### Utilitaires

#### POST /api/loans/calculate
Calcul des mensualités et éligibilité
```json
{
  "loanType": "personal",
  "amount": 15000,
  "durationMonths": 36,
  "userIncome": 45000
}
```

#### GET /api/loans/types
Liste des types de prêts disponibles avec leurs caractéristiques

## Sécurité

### Authentification JWT
- **Access Token** : Durée de vie 1 heure
- **Refresh Token** : Durée de vie 30 jours
- **Algorithme** : RS256 avec clés asymétriques

### Autorisations granulaires
- **ROLE_USER** : Gestion de ses propres prêts
- **ROLE_LOAN_OFFICER** : Examen et approbation des prêts
- **ROLE_ADMIN** : Accès complet + gestion utilisateurs

### Voters de sécurité
- `loan.view` : Consultation d'un prêt
- `loan.edit` : Modification d'un prêt
- `loan.approve` : Approbation d'un prêt
- `loan.fund` : Financement d'un prêt

## Validation

### Contraintes métier
- **Montant** : Entre 1 000€ et 1 000 000€ selon le type
- **Durée** : Entre 6 et 360 mois selon le type
- **Transitions d'état** : Workflow strict défini

### Validation des données
- Validation Symfony avec groupes de validation
- Messages d'erreur localisés en français
- Codes d'erreur HTTP appropriés

## Pagination et Filtres

### Pagination
- **Par défaut** : 20 éléments par page
- **Maximum** : 100 éléments par page
- **Paramètres** : `page`, `limit`

### Filtres disponibles
- **Statut** : `status=pending,approved`
- **Type** : `loanType=personal,auto`
- **Montant** : `amount[gte]=5000&amount[lte]=50000`
- **Date** : `createdAt[after]=2025-01-01`

## Formats supportés

### Entrée
- `application/json` (principal)
- `application/ld+json` (JSON-LD)

### Sortie
- `application/json` (principal)
- `application/ld+json` (JSON-LD avec contexte)
- `text/html` (documentation navigable)

## Codes de réponse

### Succès
- **200** : Opération réussie
- **201** : Ressource créée
- **204** : Mise à jour réussie sans contenu

### Erreurs
- **400** : Données invalides
- **401** : Non authentifié
- **403** : Non autorisé
- **404** : Ressource non trouvée
- **422** : Erreur de validation métier

## Exemples d'utilisation

### Création d'un prêt personnel
```bash
curl -X POST /api/loans \
  -H "Authorization: Bearer $JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "loanType": "personal",
    "amount": 15000,
    "durationMonths": 36,
    "projectDescription": "Travaux de rénovation"
  }'
```

### Approbation d'un prêt (loan officer)
```bash
curl -X PATCH /api/loans/123e4567-e89b-12d3-a456-426614174000 \
  -H "Authorization: Bearer $JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "action": "approve",
    "actionComment": "Dossier validé après vérification"
  }'
```

### Calcul de mensualités
```bash
curl -X POST /api/loans/calculate \
  -H "Authorization: Bearer $JWT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "loanType": "auto",
    "amount": 25000,
    "durationMonths": 48,
    "userIncome": 55000
  }'
```

## Documentation interactive

- **Swagger UI** : `/api/docs`
- **OpenAPI JSON** : `/api/docs.json`
- **Schema JSON-LD** : `/api/contexts/*`

## Tests API

### Collection Postman
Une collection Postman complète est disponible avec :
- Toutes les requêtes d'exemple
- Variables d'environnement
- Tests automatisés de réponse

### Tests automatisés
```bash
# Tests d'API avec PHPUnit
php bin/phpunit tests/Api/

# Tests de charge avec Artillery
artillery run tests/load/api-load-test.yml
```

## Monitoring et Métriques

### Logs API
- Toutes les requêtes sont loggées
- Temps de réponse trackés
- Erreurs détaillées avec contexte

### Métriques disponibles
- Nombre de requêtes par endpoint
- Temps de réponse moyen
- Taux d'erreur par endpoint
- Utilisation par utilisateur
