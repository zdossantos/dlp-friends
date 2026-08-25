# Architecture technique

Ce document décrit l'architecture actuelle. Les fonctions prévues mais non
livrées restent décrites dans `mvp-v1.md` et `roadmap.md`.

## Stack applicative

- Laravel 13 sur PHP 8.4 ;
- Inertia 3, Vue 3 Composition API et TypeScript ;
- Tailwind CSS et composants accessibles fondés sur Reka UI ;
- Bun 1.3.14 pour les dépendances et commandes frontend ;
- MySQL 8.4 pour les données relationnelles ;
- Redis pour le cache, les sessions et les files ;
- Laravel Reverb et Echo pour le temps réel ;
- Pest, Pest Browser, Playwright, PHPStan/Larastan, Pint, ESLint et Prettier
  pour la qualité.

Les versions exactes sont verrouillées dans `composer.lock` et `bun.lock`. Bun
1.3.14 est épinglé dans `package.json`, GitHub Actions et le build Docker.

## Frontière applicative

Laravel porte l'authentification, les autorisations et les règles métier.
Vue/Inertia affiche les pages servies par Laravel ; aucune API distincte n'est
nécessaire actuellement. Toute action sensible doit être protégée côté serveur,
de préférence avec une Policy Laravel.

`users` contient les données privées de compte. `profiles` contient les données
publiques et l'état d'onboarding. La vérification de l'e-mail précède
l'onboarding, puis les middlewares limitent l'accès selon l'état du compte, du
profil et des rôles.

## Services Docker

| Service | Responsabilité |
| --- | --- |
| `web` | Application HTTP avec PHP-FPM et Nginx |
| `worker` | Exécution des files Laravel |
| `scheduler` | Exécution des tâches planifiées |
| `reverb` | Serveur WebSocket |
| `mysql` | Base relationnelle persistante |
| `redis` | Cache, sessions et files sur le réseau privé |
| `minio` | Stockage objet compatible S3 |
| `mailpit` | Capture locale des e-mails |

Les quatre services applicatifs utilisent la même image Docker. MySQL, Redis,
le worker et le scheduler ne publient aucun port sur l'hôte. `compose.yaml`
décrit la stack locale complète, dont Mailpit ; une configuration de production
doit exclure Mailpit et fournir un véritable transport SMTP.

Le conteneur applicatif ne lance aucune migration au démarrage. Les migrations
s'exécutent explicitement avec `php artisan migrate --force` et doivent rester
compatibles avec la version applicative précédente pendant un déploiement.

## Environnements

| Sujet | Développement | CI | Production |
| --- | --- | --- | --- |
| Base de données | MySQL Docker | MySQL de service pour les suites Pest | MySQL privé |
| Cache et files | Redis Docker | Stockages `array` et files synchrones | Redis privé |
| Fichiers | Disque local par défaut | Disque local éphémère | Stockage S3-compatible prévu |
| E-mails | Mailpit | Transport `array` | Transport à définir avant mise en ligne |

Les secrets sont fournis par variables d'environnement et ne sont jamais
intégrés à l'image ou au dépôt.

## Déploiement

`main` est l'unique branche de production. Après les contrôles de pull request,
Coolify utilise l'image et la configuration Compose versionnée comme base de
déploiement. La sélection des services de production et les secrets restent des
réglages opérateur ; Mailpit doit en être exclu. GitHub Actions vérifie
l'application et l'image Docker mais ne déclenche pas directement le
déploiement.

La route `/up` sert de contrôle de santé sans exposer de configuration. Les
services longs possèdent également un healthcheck Docker et des limites de
ressources.

## Principes de conception

La séparation en modèles, Policies, Form Requests, Actions et composants sert
un besoin actuel. Une nouvelle couche doit réduire une complexité mesurable ;
elle ne doit pas anticiper une extension hypothétique. Voir
`engineering-principles.md`.
