# Tests Coverage Report

## Objectif de Couverture
Cet projet vise une couverture de tests de **80%+** pour assurer la qualité du code.

## Structure des Tests

### Tests Unitaires (`tests/Unit/`)
- **Domain/ValueObject/** : Tests des Value Objects (Amount, Duration, InterestRate, etc.)
- **Domain/Entity/** : Tests des entités métier (Loan, User)
- **Domain/Event/** : Tests des événements domaine
- **Application/Service/** : Tests des services applicatifs
- **Infrastructure/Service/** : Tests des services d'infrastructure

### Tests d'Intégration (`tests/Integration/`)
- **Application/Handler/** : Tests des handlers CQRS
- Tests d'intégration entre les couches

### Tests Fonctionnels (`tests/Functional/`)
- **Domain/Entity/** : Tests des workflows métier complets
- Scénarios end-to-end

## Commandes Utiles

```bash
# Exécuter tous les tests
php bin/phpunit

# Exécuter seulement les tests unitaires
php bin/phpunit --testsuite=unit

# Exécuter avec couverture de code
php bin/phpunit --coverage-html var/coverage/html

# Exécuter un test spécifique
php bin/phpunit tests/Unit/Domain/ValueObject/AmountTest.php

# Exécuter avec verbosité
php bin/phpunit --verbose
```

## Métriques de Qualité

- **Couverture de code** : Objectif 80%+
- **Tests unitaires** : Couverture des Value Objects, Entités, Services
- **Tests d'intégration** : Validation des interactions entre couches
- **Tests fonctionnels** : Validation des workflows métier

## Bonnes Pratiques

1. **Isolation** : Chaque test doit être indépendant
2. **Mocking** : Utiliser des mocks pour les dépendances externes
3. **Assertions** : Tester les comportements, pas l'implémentation
4. **Nomenclature** : Noms de tests explicites et descriptifs
5. **AAA Pattern** : Arrange, Act, Assert

## Configuration PHPUnit

Le fichier `phpunit.xml` configure :
- Suites de tests séparées
- Couverture de code avec exclusions
- Rapports HTML et XML
- Variables d'environnement de test

## Rapports Générés

- **HTML** : `var/coverage/html/index.html`
- **Text** : `var/coverage/coverage.txt`
- **Clover** : `var/coverage/clover.xml`
- **JUnit** : `var/log/junit.xml`
