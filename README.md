# DLP Friends

DLP Friends est un projet d'application de rencontres amicales entre fans
majeurs de Disneyland Paris. Son MVP vise à permettre de créer un profil,
découvrir des membres ayant des passions communes et échanger après un match
réciproque. Le périmètre prévu et l'état du code sont distingués dans la
documentation.

Le projet est indépendant et n'est affilié ni à Disney ni à Disneyland Paris.

## Stack

- Laravel 13 et PHP 8.4 ;
- Inertia 3, Vue 3 et TypeScript ;
- Tailwind CSS et Reka UI ;
- MySQL, Redis et Laravel Reverb ;
- Bun 1.3.14 pour les dépendances et commandes frontend ;
- Docker Compose pour l'environnement local.

## Prérequis

- PHP 8.4 et Composer 2 ;
- Bun 1.3.14 ;
- Docker Desktop avec Docker Compose.

## Installation

```sh
composer install
cp .env.example .env
php artisan key:generate
bun install --frozen-lockfile
docker compose up --build -d
docker compose exec web php artisan migrate --seed --force
```

L'application est ensuite disponible sur <http://localhost:8000>.

Le seeder crée un compte local vérifié :

- e-mail : `test@example.com` ;
- mot de passe : `password` ;
- rôles : `user` et `admin`.

Les migrations restent explicites et ne sont jamais lancées automatiquement au
démarrage des conteneurs.

## Services locaux

| Service | Adresse |
| --- | --- |
| Application | <http://localhost:8000> |
| Route de santé | <http://localhost:8000/up> |
| Reverb | `ws://localhost:8080` |
| Mailpit | <http://localhost:8025> |
| MinIO | <http://localhost:9000> (console sur le port `9001`) |

## Commandes utiles

```sh
# Contrôles PHP et frontend
composer ci:check
bun run build

# Environnement Docker
docker compose ps
docker compose logs -f web worker reverb
docker compose exec web php artisan migrate --force
docker compose down
```

Les contrôles individuels sont décrits dans
[`CONTRIBUTING.md`](CONTRIBUTING.md).

## Documentation

| Document | Sujet |
| --- | --- |
| [`docs/product-vision.md`](docs/product-vision.md) | Positionnement et principes produit |
| [`docs/mvp-v1.md`](docs/mvp-v1.md) | Périmètre fonctionnel de la V1 |
| [`docs/roadmap.md`](docs/roadmap.md) | Évolutions envisagées après le MVP |
| [`docs/data-model.md`](docs/data-model.md) | Modèle métier et matching |
| [`docs/technical-architecture.md`](docs/technical-architecture.md) | Architecture et services |
| [`docs/ux-design.md`](docs/ux-design.md) | Expérience et direction visuelle |
| [`docs/security-privacy.md`](docs/security-privacy.md) | Sécurité et données personnelles |
| [`docs/operations.md`](docs/operations.md) | Exploitation et fiabilité |
| [`docs/quality-ci-cd.md`](docs/quality-ci-cd.md) | CI, branches et livraison |
| [`docs/engineering-principles.md`](docs/engineering-principles.md) | Principes de développement |

## Contribuer

Consultez [`CONTRIBUTING.md`](CONTRIBUTING.md) pour le workflow Git, les
contrôles à exécuter et le processus de release.
