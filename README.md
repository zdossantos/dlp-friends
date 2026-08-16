# DLP Friends

## But de ce dossier

Ce dépôt contient le projet DLP Friends, une application de rencontres amicales entre fans majeurs de Disneyland Paris. L'application est indépendante et non affiliée à Disney ou Disneyland Paris.

Les documents dans `docs/` sont la source de vérité fonctionnelle et technique. Ils sont écrits pour être lus aussi bien par une équipe humaine que par une IA qui doit planifier ou implémenter le projet.

Avant toute modification, lire dans cet ordre :

1. `docs/product-vision.md`
2. `docs/mvp-v1.md`
3. le document concerné par la modification (`docs/data-model.md`, `docs/technical-architecture.md`, etc.)

## Démarrage local

Prérequis : PHP 8.4, Composer 2, Node.js avec npm et Docker Desktop avec Docker Compose.

```sh
composer install
cp .env.example .env
php artisan key:generate
npm ci
docker compose up --build -d
docker compose exec web php artisan migrate --force
```

Les migrations restent une action explicite : aucun conteneur ne modifie le schéma au démarrage. Laravel Sail n'est ni installé ni utilisé ; `compose.yaml`, le `Dockerfile` et les points d'entrée du dépôt constituent l'environnement Docker.

Services exposés localement :

| Service             | URL ou port                              |
| ------------------- | ---------------------------------------- |
| Application Laravel | `http://localhost:8000`                  |
| Santé Laravel       | `http://localhost:8000/up`               |
| Laravel Reverb      | `ws://localhost:8080`                    |
| Mailpit             | `http://localhost:8025` (SMTP `1025`)    |
| MinIO               | `http://localhost:9000` (console `9001`) |

MySQL et Redis ne publient aucun port sur l'hôte. Les commandes courantes sont :

```sh
docker compose ps
docker compose logs -f web worker reverb
docker compose exec web php artisan migrate --force
docker compose down
```

Pour les contrôles applicatifs exécutés sur l'hôte :

```sh
composer lint:check
composer analyse
php artisan test
npm run lint:check
npm run format:check
npm run types:check
npm run test
npm run build
docker build --target runtime --tag dlp-friends:ci .
```

Ces commandes correspondent aux contrôles exécutés dans GitHub Actions. La
stratégie de branches, les checks requis et le versioning Release Please sont
documentés dans [`docs/quality-ci-cd.md`](docs/quality-ci-cd.md).

L'infrastructure SMTP de production est volontairement différée. Mailpit est l'unique transport SMTP local.

## Règles de travail

- Ne pas ajouter une fonctionnalité hors MVP sans l'inscrire d'abord dans `docs/roadmap.md` ou sans validation produit.
- DLP Friends est exclusivement une application de rencontres amicales entre majeurs ; ne jamais introduire de vocabulaire, critères ou mécanismes romantiques.
- Préserver la maîtrise des données : le membre peut modifier, masquer et supprimer son compte depuis le MVP.
- Toute règle métier importante doit être testée et documentée.
- Appliquer les principes de `docs/engineering-principles.md` : code lisible, simple, sans abstraction ou dépendance non justifiée.
- En cas de question, d'ambiguïté, de contradiction ou de décision susceptible de modifier le périmètre, demander explicitement validation au porteur du projet avant d'implémenter ou de supposer une réponse.
- Le transport des e-mails de production sera configuré ultérieurement, après une décision d'infrastructure explicite.
- Ne pas utiliser de personnages, logos ou illustrations Disney sans droits d'utilisation démontrés. Le produit est non affilié à Disney.

## Index

| Fichier                          | Contenu                                                 |
| -------------------------------- | ------------------------------------------------------- |
| `docs/product-vision.md`         | Positionnement et principes produit                     |
| `docs/mvp-v1.md`                 | Fonctionnalités livrées en version 1                    |
| `docs/roadmap.md`                | Évolutions explicitement postérieures au MVP            |
| `docs/ux-design.md`              | Direction artistique et règles d'interface              |
| `docs/data-model.md`             | Modèle métier et matching                               |
| `docs/technical-architecture.md` | Stack, services Docker et déploiement                   |
| `docs/quality-ci-cd.md`          | Tests, qualité, branches et automatisations GitHub      |
| `docs/security-privacy.md`       | Sécurité, majorité, blocage et cycle de vie des données |
| `docs/operations.md`             | Sauvegardes, santé, journaux et exploitation            |
| `docs/engineering-principles.md` | Règles de simplicité, clean code et abstraction         |
