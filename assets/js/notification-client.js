/**
 * Client JavaScript pour les notifications temps réel via Mercure
 * LoanMaster - Système de notification push
 */
class LoanMasterNotificationClient {
    constructor(options = {}) {
        this.options = {
            mercureUrl: options.mercureUrl || 'http://localhost:3000/.well-known/mercure',
            jwtToken: options.jwtToken || null,
            userId: options.userId || null,
            userRoles: options.userRoles || [],
            debug: options.debug || false,
            reconnectDelay: options.reconnectDelay || 3000,
            maxReconnectAttempts: options.maxReconnectAttempts || 5,
            ...options
        };

        this.eventSource = null;
        this.reconnectAttempts = 0;
        this.isConnected = false;
        this.subscriptions = new Map();
        this.handlers = new Map();

        // Gestionnaires d'événements par défaut
        this.defaultHandlers = {
            'loan_status_update': this.handleLoanStatusUpdate.bind(this),
            'risk_alert': this.handleRiskAlert.bind(this),
            'payment_notification': this.handlePaymentNotification.bind(this),
            'system_notification': this.handleSystemNotification.bind(this),
            'audit_alert': this.handleAuditAlert.bind(this)
        };

        this.init();
    }

    /**
     * Initialisation du client
     */
    init() {
        this.log('Initializing LoanMaster Notification Client...');
        
        // Auto-abonnement selon l'utilisateur et ses rôles
        this.setupAutoSubscriptions();
        
        // Connexion à Mercure
        this.connect();
        
        // Gestion de la visibilité de la page pour reconnexion
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden && !this.isConnected) {
                this.connect();
            }
        });
    }

    /**
     * Configuration automatique des abonnements selon l'utilisateur
     */
    setupAutoSubscriptions() {
        if (!this.options.userId) return;

        // Abonnement personnalisé à l'utilisateur
        this.subscribe(`/users/${this.options.userId}`);

        // Abonnements selon les rôles
        this.options.userRoles.forEach(role => {
            this.subscribe(`/roles/${role}`);
        });

        // Abonnements aux topics généraux
        this.subscribe('/notifications/loan_status_updates');
        this.subscribe('/notifications/payment_notifications');
        
        // Abonnements selon les rôles spécifiques
        if (this.hasRole('ROLE_ANALYST') || this.hasRole('ROLE_MANAGER')) {
            this.subscribe('/notifications/risk_alerts');
        }
        
        if (this.hasRole('ROLE_ADMIN') || this.hasRole('ROLE_MANAGER')) {
            this.subscribe('/notifications/audit_alerts');
            this.subscribe('/notifications/system_notifications');
        }
    }

    /**
     * Connexion à Mercure
     */
    connect() {
        if (this.eventSource) {
            this.eventSource.close();
        }

        try {
            // Construction de l'URL avec les topics
            const topics = Array.from(this.subscriptions.keys());
            const url = new URL(this.options.mercureUrl);
            
            topics.forEach(topic => {
                url.searchParams.append('topic', topic);
            });

            // Headers d'autorisation
            const eventSourceOptions = {};
            if (this.options.jwtToken) {
                eventSourceOptions.headers = {
                    'Authorization': `Bearer ${this.options.jwtToken}`
                };
            }

            this.log('Connecting to Mercure...', { url: url.toString(), topics });

            this.eventSource = new EventSource(url.toString());

            this.eventSource.onopen = (event) => {
                this.isConnected = true;
                this.reconnectAttempts = 0;
                this.log('Connected to Mercure successfully');
                this.emit('connected', { event });
            };

            this.eventSource.onmessage = (event) => {
                this.handleMessage(event);
            };

            this.eventSource.onerror = (event) => {
                this.isConnected = false;
                this.log('Mercure connection error', event);
                this.emit('error', { event });
                
                if (this.reconnectAttempts < this.options.maxReconnectAttempts) {
                    setTimeout(() => {
                        this.reconnectAttempts++;
                        this.log(`Reconnection attempt ${this.reconnectAttempts}/${this.options.maxReconnectAttempts}`);
                        this.connect();
                    }, this.options.reconnectDelay);
                } else {
                    this.log('Max reconnection attempts reached');
                    this.emit('maxReconnectAttemptsReached');
                }
            };

        } catch (error) {
            this.log('Error creating EventSource', error);
            this.emit('error', { error });
        }
    }

    /**
     * Traitement des messages reçus
     */
    handleMessage(event) {
        try {
            const data = JSON.parse(event.data);
            this.log('Received notification', data);

            // Émission de l'événement générique
            this.emit('notification', data);

            // Traitement spécifique selon le type
            if (data.type && this.defaultHandlers[data.type]) {
                this.defaultHandlers[data.type](data);
            }

            // Traitement par les handlers personnalisés
            if (this.handlers.has(data.type)) {
                this.handlers.get(data.type).forEach(handler => {
                    try {
                        handler(data);
                    } catch (error) {
                        this.log('Handler error', error);
                    }
                });
            }

        } catch (error) {
            this.log('Error parsing message', error);
        }
    }

    /**
     * Gestionnaire pour les mises à jour de statut de prêt
     */
    handleLoanStatusUpdate(data) {
        const notification = {
            title: 'Mise à jour de votre prêt',
            body: `Votre prêt #${data.data.loanId} est maintenant "${data.data.newStatus}"`,
            icon: '/assets/icons/loan-update.png',
            tag: 'loan-status',
            actions: data.metadata?.actions || []
        };

        this.showNotification(notification);
        this.updateUI('loan-status', data.data);
    }

    /**
     * Gestionnaire pour les alertes de risque
     */
    handleRiskAlert(data) {
        const notification = {
            title: '⚠️ Alerte de Risque',
            body: `Niveau ${data.data.riskLevel} détecté - Score: ${data.data.riskScore}`,
            icon: '/assets/icons/risk-alert.png',
            tag: 'risk-alert',
            requireInteraction: true
        };

        this.showNotification(notification);
        this.showRiskAlertModal(data.data);
    }

    /**
     * Gestionnaire pour les notifications de paiement
     */
    handlePaymentNotification(data) {
        const isOverdue = data.data.is_overdue || false;
        const notification = {
            title: isOverdue ? '🚨 Paiement en retard' : '💰 Rappel de paiement',
            body: `${data.data.amount}€ ${isOverdue ? 'en retard' : 'à payer'} pour votre prêt #${data.data.loanId}`,
            icon: isOverdue ? '/assets/icons/payment-overdue.png' : '/assets/icons/payment-reminder.png',
            tag: 'payment',
            actions: [
                { action: 'pay', title: 'Payer maintenant' },
                { action: 'view', title: 'Voir détails' }
            ]
        };

        this.showNotification(notification);
        this.updateUI('payment', data.data);
    }

    /**
     * Gestionnaire pour les notifications système
     */
    handleSystemNotification(data) {
        const notification = {
            title: data.data.title || 'Notification Système',
            body: data.data.message,
            icon: '/assets/icons/system.png',
            tag: 'system',
            actions: data.data.actions || []
        };

        this.showNotification(notification);
        
        // Affichage en tant que toast si c'est une notification système
        this.showToast(notification.title, notification.body, data.metadata?.priority || 'info');
    }

    /**
     * Gestionnaire pour les alertes d'audit
     */
    handleAuditAlert(data) {
        // Alertes d'audit sensibles - affichage seulement pour les rôles autorisés
        if (!this.hasRole('ROLE_ADMIN') && !this.hasRole('ROLE_AUDITOR')) {
            return;
        }

        const notification = {
            title: '🔒 Alerte de Sécurité',
            body: `${data.data.severity}: ${data.data.eventType} détecté`,
            icon: '/assets/icons/security.png',
            tag: 'security',
            requireInteraction: true
        };

        this.showNotification(notification);
        this.showSecurityAlert(data.data);
    }

    /**
     * Affichage d'une notification navigateur
     */
    async showNotification(options) {
        if (!('Notification' in window)) {
            this.log('Browser does not support notifications');
            return;
        }

        let permission = Notification.permission;
        
        if (permission === 'default') {
            permission = await Notification.requestPermission();
        }

        if (permission === 'granted') {
            const notification = new Notification(options.title, {
                body: options.body,
                icon: options.icon,
                tag: options.tag,
                requireInteraction: options.requireInteraction || false,
                silent: options.silent || false,
                data: options.data || {}
            });

            // Gestion des clics sur la notification
            notification.onclick = (event) => {
                window.focus();
                if (options.onclick) {
                    options.onclick(event);
                } else {
                    this.handleNotificationClick(options.tag, options.data);
                }
                notification.close();
            };

            // Auto-fermeture
            if (!options.requireInteraction) {
                setTimeout(() => notification.close(), 5000);
            }
        }
    }

    /**
     * Affichage d'un toast
     */
    showToast(title, message, type = 'info') {
        // Recherche d'un conteneur de toast existant ou création
        let toastContainer = document.getElementById('loanmaster-toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'loanmaster-toast-container';
            toastContainer.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                z-index: 10000;
                max-width: 350px;
            `;
            document.body.appendChild(toastContainer);
        }

        const toastId = `toast-${Date.now()}`;
        const toast = document.createElement('div');
        toast.id = toastId;
        toast.className = `toast toast-${type}`;
        toast.style.cssText = `
            background: white;
            border: 1px solid #ddd;
            border-left: 4px solid ${this.getTypeColor(type)};
            border-radius: 4px;
            padding: 12px 16px;
            margin-bottom: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease;
        `;

        toast.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <strong style="color: #333; display: block; margin-bottom: 4px;">${title}</strong>
                    <div style="color: #666; font-size: 14px;">${message}</div>
                </div>
                <button onclick="this.parentElement.parentElement.remove()" 
                        style="background: none; border: none; color: #999; cursor: pointer; padding: 0; margin-left: 12px;">
                    ✕
                </button>
            </div>
        `;

        toastContainer.appendChild(toast);

        // Animation d'entrée
        setTimeout(() => {
            toast.style.opacity = '1';
            toast.style.transform = 'translateX(0)';
        }, 10);

        // Auto-suppression
        setTimeout(() => {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }

    /**
     * Gestion des clics sur les notifications
     */
    handleNotificationClick(tag, data) {
        switch (tag) {
            case 'loan-status':
                window.location.href = `/loans/${data.loanId}`;
                break;
            case 'payment':
                window.location.href = `/payments/${data.loanId}`;
                break;
            case 'risk-alert':
                window.location.href = `/admin/risk-alerts/${data.loanId}`;
                break;
            case 'security':
                window.location.href = '/admin/audit-logs';
                break;
            default:
                window.location.href = '/dashboard';
        }
    }

    /**
     * Mise à jour de l'interface utilisateur
     */
    updateUI(type, data) {
        this.emit('ui-update', { type, data });
        
        // Mise à jour des badges/compteurs
        this.updateNotificationBadges();
        
        // Mise à jour spécifique selon le type
        switch (type) {
            case 'loan-status':
                this.updateLoanStatusUI(data);
                break;
            case 'payment':
                this.updatePaymentUI(data);
                break;
        }
    }

    /**
     * Mise à jour des badges de notification
     */
    updateNotificationBadges() {
        // Logique pour mettre à jour les compteurs de notifications
        const badge = document.querySelector('.notification-badge');
        if (badge) {
            const count = parseInt(badge.textContent || '0') + 1;
            badge.textContent = count > 99 ? '99+' : count.toString();
            badge.style.display = count > 0 ? 'inline' : 'none';
        }
    }

    /**
     * Souscription à un topic
     */
    subscribe(topic, handler = null) {
        this.subscriptions.set(topic, true);
        if (handler) {
            this.addHandler(topic, handler);
        }
        this.log(`Subscribed to topic: ${topic}`);
    }

    /**
     * Désouscription d'un topic
     */
    unsubscribe(topic) {
        this.subscriptions.delete(topic);
        this.handlers.delete(topic);
        this.log(`Unsubscribed from topic: ${topic}`);
    }

    /**
     * Ajout d'un gestionnaire personnalisé
     */
    addHandler(type, handler) {
        if (!this.handlers.has(type)) {
            this.handlers.set(type, []);
        }
        this.handlers.get(type).push(handler);
    }

    /**
     * Émission d'événements personnalisés
     */
    emit(eventName, data = {}) {
        const event = new CustomEvent(`loanmaster:${eventName}`, { detail: data });
        document.dispatchEvent(event);
    }

    /**
     * Vérification de rôle
     */
    hasRole(role) {
        return this.options.userRoles.includes(role);
    }

    /**
     * Couleur selon le type de toast
     */
    getTypeColor(type) {
        const colors = {
            info: '#17a2b8',
            success: '#28a745',
            warning: '#ffc107',
            error: '#dc3545',
            urgent: '#e74c3c'
        };
        return colors[type] || colors.info;
    }

    /**
     * Logging avec debug
     */
    log(message, data = null) {
        if (this.options.debug) {
            console.log(`[LoanMaster Notifications] ${message}`, data || '');
        }
    }

    /**
     * Modales spécialisées
     */
    showRiskAlertModal(data) {
        // Implémentation d'une modale spécialisée pour les alertes de risque
        console.log('Risk Alert Modal should be shown', data);
    }

    showSecurityAlert(data) {
        // Implémentation d'une alerte de sécurité
        console.log('Security Alert should be shown', data);
    }

    updateLoanStatusUI(data) {
        // Mise à jour de l'interface pour les statuts de prêt
        const statusElement = document.querySelector(`[data-loan-id="${data.loanId}"] .loan-status`);
        if (statusElement) {
            statusElement.textContent = data.newStatus;
            statusElement.className = `loan-status status-${data.newStatus}`;
        }
    }

    updatePaymentUI(data) {
        // Mise à jour de l'interface pour les paiements
        const paymentElement = document.querySelector(`[data-loan-id="${data.loanId}"] .payment-status`);
        if (paymentElement) {
            paymentElement.innerHTML = `<strong>${data.amount}€</strong> due ${data.due_date}`;
        }
    }

    /**
     * Déconnexion propre
     */
    disconnect() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
        this.isConnected = false;
        this.log('Disconnected from Mercure');
    }
}

// Export pour utilisation
window.LoanMasterNotificationClient = LoanMasterNotificationClient;

// Auto-initialisation si les paramètres sont présents
document.addEventListener('DOMContentLoaded', () => {
    // Recherche des paramètres dans les meta tags ou variables globales
    const mercureUrl = document.querySelector('meta[name="mercure-url"]')?.content;
    const jwtToken = document.querySelector('meta[name="mercure-jwt"]')?.content;
    const userId = document.querySelector('meta[name="user-id"]')?.content;
    const userRoles = document.querySelector('meta[name="user-roles"]')?.content?.split(',') || [];

    if (mercureUrl && userId) {
        window.loanMasterNotifications = new LoanMasterNotificationClient({
            mercureUrl,
            jwtToken,
            userId,
            userRoles,
            debug: document.querySelector('meta[name="app-env"]')?.content === 'dev'
        });
    }
});
