# 🔔 Système de Notifications Temps Réel - LoanMaster

## Vue d'ensemble

Le système de notifications temps réel de LoanMaster utilise **Mercure** pour fournir des mises à jour instantanées aux utilisateurs via WebSockets/Server-Sent Events. Il s'intègre parfaitement avec le système d'Event Sourcing pour déclencher automatiquement des notifications lors des événements métier.

## 🏗️ Architecture

### Composants principaux

```
┌─────────────────────────────────────────────────────────────┐
│                    Frontend (JavaScript)                    │
│  ┌─────────────────┐  ┌─────────────────┐  ┌──────────────┐ │
│  │ Web Notifications │  │     Toasts      │  │  UI Updates  │ │
│  └─────────────────┘  └─────────────────┘  └──────────────┘ │
└─────────────────────────────────────────────────────────────┘
                              │
                    ┌─────────▼─────────┐
                    │   Mercure Hub     │
                    │ (Server-Sent Events)│
                    └─────────▲─────────┘
                              │
┌─────────────────────────────▼─────────────────────────────────┐
│                    Backend (PHP/Symfony)                     │
│  ┌─────────────────┐  ┌─────────────────┐  ┌──────────────┐  │
│  │ Event Subscriber │  │ Notification    │  │   Channels   │  │
│  │                 │  │ Orchestrator    │  │              │  │
│  │ • LoanStatus    │──┤                 ├──┤ • Mercure    │  │
│  │ • Payment       │  │ Multi-Channel   │  │ • Email      │  │
│  │ • Risk          │  │ Dispatcher      │  │ • SMS        │  │
│  │ • Audit         │  │                 │  │ • Push       │  │
│  └─────────────────┘  └─────────────────┘  └──────────────┘  │
└───────────────────────────────────────────────────────────────┘
                              │
                    ┌─────────▼─────────┐
                    │   Event Store     │
                    │ (Event Sourcing)  │
                    └───────────────────┘
```

### Canaux de notification

1. **Mercure** (Temps réel)
   - WebSockets/Server-Sent Events
   - Notifications instantanées
   - Interface utilisateur reactive

2. **Email** (Persistant)
   - Notifications importantes
   - Récapitulatifs
   - Documentation

3. **SMS** (Urgent)
   - Alertes critiques
   - Paiements en retard
   - Sécurité

4. **Push** (Mobile/Web)
   - Notifications navigateur
   - Applications mobiles
   - Engagement utilisateur

## 🚀 Fonctionnalités

### Types de notifications supportés

| Type | Déclencheur | Canaux | Priorité |
|------|------------|--------|----------|
| `loan_status_update` | Changement statut prêt | Mercure, Email, Push | Normal/High |
| `risk_alert` | Score de risque élevé | Mercure, Email | High/Urgent |
| `payment_reminder` | Échéance proche/dépassée | Email, SMS, Mercure | Normal/High |
| `payment_failed` | Échec de paiement | Mercure, Email, SMS | High |
| `loan_approved` | Approbation prêt | Mercure, Email, Push | High |
| `loan_funded` | Déblocage fonds | Mercure, Email, Push | High |
| `audit_alert` | Activité suspecte | Mercure, Email | Urgent |
| `system_notification` | Maintenance/Info | Mercure, Push | Normal |

### Fonctionnalités avancées

- **🔄 Temps réel** : Mises à jour instantanées via Mercure
- **📱 Multi-canal** : Distribution intelligente selon le contexte
- **🎯 Ciblage** : Par utilisateur, rôle, ou groupe
- **🔒 Sécurisé** : JWT tokens, authentification, autorisation
- **📊 Métriques** : Statistiques de livraison et performance
- **♻️ Retry** : Nouvelle tentative en cas d'échec
- **🧹 Nettoyage** : Suppression automatique des notifications anciennes
- **⚙️ Configurable** : Templates, priorités, canaux personnalisables

## 📋 Configuration

### Variables d'environnement

```bash
# Mercure
MERCURE_URL=http://localhost:3000/.well-known/mercure
MERCURE_PUBLIC_URL=http://localhost:3000/.well-known/mercure
MERCURE_JWT_SECRET=!ChangeMe!

# Email
MAILER_DSN=smtp://localhost:1025

# SMS (Twilio)
SMS_ENABLED=false
SMS_API_KEY=your_twilio_account_sid
SMS_API_SECRET=your_twilio_auth_token
SMS_FROM_NUMBER=+33123456789

# Push Notifications (Firebase)
PUSH_NOTIFICATIONS_ENABLED=false
FIREBASE_SERVER_KEY=your_firebase_server_key
FIREBASE_PROJECT_ID=your_project_id
VAPID_PUBLIC_KEY=your_vapid_public_key
VAPID_PRIVATE_KEY=your_vapid_private_key

# Retention
NOTIFICATION_RETENTION_DAYS=30
```

### Configuration Symfony

Les configurations sont dans :
- `config/packages/mercure.yaml` - Configuration Mercure
- `config/services_notification.yaml` - Services de notification
- `config/packages/monolog.yaml` - Logging (ajout du canal notification)

## 🛠️ Utilisation

### Backend - Envoi de notifications

```php
// Via l'Event Subscriber (automatique)
$event = new LoanStatusChanged($loanId, $customerId, 'pending', 'approved');
$eventDispatcher->dispatch($event);

// Via le service directement
$notificationOrchestrator->sendLoanStatusNotification(
    $loanId,
    $customerId, 
    $customerEmail,
    $customerPhone,
    'pending',
    'approved',
    'Félicitations ! Votre prêt a été approuvé.'
);

// Notification personnalisée
$result = $notificationOrchestrator->sendNotification(
    'custom_notification',
    [
        ['type' => 'customer', 'id' => $customerId, 'email' => $email]
    ],
    [
        'title' => 'Message important',
        'message' => 'Contenu de la notification',
        'action_url' => '/dashboard'
    ],
    [
        'channels' => ['mercure', 'email'],
        'priority' => 'high',
        'template' => 'custom_template'
    ]
);
```

### Frontend - Réception des notifications

```javascript
// Auto-initialisation (recommandé)
// Ajoutez ces meta tags dans votre template de base :
<meta name="mercure-url" content="{{ mercure_url }}">
<meta name="mercure-jwt" content="{{ mercure_jwt_token }}">
<meta name="user-id" content="{{ app.user.id }}">
<meta name="user-roles" content="{{ app.user.roles|join(',') }}">

// Le client se connecte automatiquement au chargement de la page

// Ou initialisation manuelle
const client = new LoanMasterNotificationClient({
    mercureUrl: 'ws://localhost:3000/.well-known/mercure',
    jwtToken: 'your-jwt-token',
    userId: 'user-123',
    userRoles: ['ROLE_CUSTOMER'],
    debug: true
});

// Gestionnaires personnalisés
client.addHandler('custom_type', (data) => {
    console.log('Custom notification received:', data);
    // Votre logique personnalisée
});

// Écoute des événements
document.addEventListener('loanmaster:notification', (event) => {
    console.log('Notification reçue:', event.detail);
});

document.addEventListener('loanmaster:connected', () => {
    console.log('Connecté au système de notifications');
});
```

## 👨‍💼 Interface d'administration

### Dashboard administrateur

Accessible via `/admin/notifications`, le dashboard fournit :

- **📊 Statistiques temps réel**
  - Notifications envoyées aujourd'hui
  - Taux de succès moyen
  - Distribution par type et statut
  - Connexions Mercure actives

- **📋 Historique complet**
  - Toutes les notifications envoyées
  - Détails de livraison par canal
  - Codes d'erreur et diagnostics
  - Temps d'exécution

- **🎛️ Outils de gestion**
  - Test d'envoi de notifications
  - Diffusion système (broadcast)
  - Gestion des abonnements
  - Nettoyage des données anciennes

- **🔧 Monitoring**
  - Santé du système Mercure
  - Statistiques de performance
  - Erreurs récentes
  - Métriques détaillées

### Actions disponibles

```bash
# Test d'une notification
POST /admin/notifications/test

# Diffusion système
POST /admin/notifications/broadcast
{
    "message": "Maintenance programmée à 23h",
    "priority": "high", 
    "target_roles": ["ROLE_USER"]
}

# Nettoyage automatique
POST /admin/notifications/cleanup
{
    "days": 30
}

# Statistiques en temps réel
GET /admin/notifications/api/stats
```

## 🔧 Installation et démarrage

### 1. Installation des dépendances

```bash
composer install
```

### 2. Migration de la base de données

```bash
php bin/console doctrine:migrations:migrate
```

### 3. Démarrage du hub Mercure

```bash
# Via Docker (recommandé)
docker run \
    -e MERCURE_PUBLISHER_JWT_KEY='!ChangeMe!' \
    -e MERCURE_SUBSCRIBER_JWT_KEY='!ChangeMe!' \
    -p 3000:80 \
    dunglas/mercure

# Ou installation locale
go install github.com/dunglas/mercure/cmd/mercure@latest
MERCURE_PUBLISHER_JWT_KEY='!ChangeMe!' \
MERCURE_SUBSCRIBER_JWT_KEY='!ChangeMe!' \
mercure run --config Caddyfile.dev
```

### 4. Configuration frontend

```bash
# Copie du client JavaScript
cp assets/js/notification-client.js public/js/

# Ou via Asset Mapper
php bin/console importmap:require notification-client
```

## 🧪 Tests

### Test manuel

1. **Connexion au dashboard admin** : `/admin/notifications`
2. **Envoi d'une notification de test** via l'interface
3. **Vérification dans l'historique** et les logs
4. **Test de réception** dans un autre onglet/navigateur

### Test des Event Subscribers

```php
// Déclenchement d'un événement de test
$event = new LoanStatusChanged('loan-123', 'customer-456', 'pending', 'approved');
$this->eventDispatcher->dispatch($event);

// Vérification dans les logs
tail -f var/log/notification.log
```

### Test de la connectivité Mercure

```bash
# Test de l'endpoint Mercure
curl -X GET "http://localhost:3000/.well-known/mercure?topic=/test" \
     -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

## 📊 Monitoring et métriques

### Logs

- **Fichier** : `var/log/notification.log`
- **Format** : JSON structuré avec contexte
- **Niveaux** : INFO (succès), WARNING (tentatives), ERROR (échecs)

### Métriques disponibles

- Nombre de notifications par type/jour
- Taux de succès par canal
- Temps de réponse moyen
- Connexions Mercure actives
- Erreurs par type

### Alertes recommandées

- Taux d'échec > 10%
- Temps de réponse > 5s
- Hub Mercure inaccessible
- Accumulation d'erreurs

## 🔒 Sécurité

### Authentification

- JWT tokens pour Mercure
- Validation des rôles utilisateur
- Chiffrement des communications

### Autorisation

- Topics sécurisés par rôle
- Validation côté serveur
- Filtrage des données sensibles

### Bonnes pratiques

- Tokens avec expiration courte
- Validation stricte des entrées
- Audit des accès
- Rate limiting

## 🚨 Dépannage

### Problèmes courants

**Connexion Mercure échouée**
```bash
# Vérifier que le hub est démarré
curl http://localhost:3000/.well-known/mercure

# Vérifier les tokens JWT
echo "YOUR_JWT_TOKEN" | jwt-cli decode
```

**Notifications non reçues**
```bash
# Vérifier les abonnements
grep "Subscribed to topic" var/log/notification.log

# Vérifier l'envoi
grep "Notification published" var/log/notification.log
```

**Erreurs de permission**
```bash
# Vérifier les rôles utilisateur
php bin/console debug:container security.token_storage

# Vérifier la configuration des topics
grep -A 5 "notification_topics" config/packages/mercure.yaml
```

### Logs utiles

```bash
# Monitoring en temps réel
tail -f var/log/notification.log | jq

# Erreurs récentes
grep ERROR var/log/notification.log | tail -10

# Statistiques de performance
grep "execution_time" var/log/notification.log | awk '{print $5}' | sort -n
```

---

## 📝 Licence

Système propriétaire - LoanMaster 2025
