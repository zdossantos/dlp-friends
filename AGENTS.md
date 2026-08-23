# AGENTS.md

## Projet

DLP Friends est une application Laravel/Inertia/Vue de rencontres strictement
amicales entre fans majeurs de Disneyland Paris. Le produit est indépendant et
non affilié à Disney ou Disneyland Paris.

Avant une modification métier, lire :

1. `docs/product-vision.md` ;
2. `docs/mvp-v1.md` ;
3. le document du domaine concerné dans `docs/`.

Ne pas confondre le périmètre produit documenté avec les fonctionnalités déjà
implémentées. Vérifier le code, les migrations et les tests avant de décrire un
comportement comme existant.

## Stack et structure

- Backend : PHP 8.4, Laravel 13, Fortify, Pest et PHPStan/Larastan.
- Frontend : Inertia 3, Vue 3 Composition API, TypeScript, Tailwind CSS,
  Reka UI et Vitest.
- Infrastructure : MySQL 8.4, Redis 7.4, Reverb, MinIO, Mailpit et Docker
  Compose.
- `app/` contient le domaine et les contrôleurs Laravel.
- `resources/js/` contient les pages, layouts, composants et tests Vue.
- `routes/` contient les routes web, de réglages et de diffusion.
- `tests/` contient les tests Pest unitaires et fonctionnels.
- `docs/` est la source de vérité produit, technique et opérationnelle.

## Installation et développement

```sh
composer install
cp .env.example .env
php artisan key:generate
npm ci
docker compose up --build -d
docker compose exec web php artisan migrate --seed --force
```

Commandes de développement courantes :

```sh
composer dev
npm run dev
docker compose ps
docker compose logs -f web worker reverb
docker compose exec web php artisan migrate --force
docker compose exec web php artisan db:seed --force
docker compose down
```

Les migrations sont toujours une action explicite. Ne pas modifier les points
d'entrée Docker pour les exécuter automatiquement.

## Build, lint et tests

Exécuter les contrôles ciblés pendant le développement, puis tous les contrôles
concernés avant de terminer :

```sh
# Backend
composer lint:check
composer analyse
php artisan test

# Frontend
php artisan wayfinder:generate --with-form
npm run lint:check
npm run format:check
npm run types:check
npm run test
npm run build

# Image de production
docker build --target runtime --tag dlp-friends:ci .
```

`composer ci:check` regroupe les contrôles PHP et frontend, hors génération
Wayfinder, build Vite et build Docker.

Pour un test ciblé :

```sh
php artisan test tests/Feature/MemberProfileTest.php
npm run test:unit -- resources/js/pages/Dashboard.spec.ts
```

## Conventions de code

- Suivre les conventions Laravel, Eloquent, Inertia et Vue déjà présentes.
- Garder la logique métier et les autorisations côté Laravel ; ne pas créer une
  API séparée sans besoin démontré.
- Utiliser une Policy pour une autorisation, une Form Request pour la validation
  HTTP, une Action pour un cas d'usage métier non trivial et un scope Eloquent
  pour une requête réutilisée.
- Préférer la Composition API et TypeScript dans les composants Vue.
- Réutiliser les composants existants et Reka UI avant d'ajouter une nouvelle
  primitive.
- Appliquer KISS et YAGNI. Ne pas ajouter d'abstraction ou de dépendance en
  prévision d'un besoin futur.
- Ne pas effectuer de refactorisation sans rapport avec la demande.

Les règles détaillées sont dans `docs/engineering-principles.md`.

## Règles de test

- Écrire ou adapter un test automatisé pour toute règle métier ou régression.
- Pour un changement de comportement, suivre le cycle rouge, vert,
  refactorisation et confirmer que le test échoue pour la bonne raison avant
  l'implémentation.
- Tester le comportement observable, pas les détails d'implémentation ni le
  framework.
- Les tests backend utilisent Pest ; les composants et composables Vue utilisent
  Vitest et Vue Test Utils.
- Exécuter les contrôles complets pertinents après les tests ciblés. Ne jamais
  annoncer un succès sans sortie de commande récente.

## Contraintes produit et sécurité

- Le service est réservé aux personnes majeures et aux rencontres amicales.
  Ne jamais introduire de vocabulaire ou de mécanique romantique.
- Préserver le contrôle des données : modification, masquage et suppression du
  compte font partie du périmètre MVP.
- Ne pas stocker ni journaliser de mot de passe, jeton, message privé ou donnée
  personnelle inutile.
- Protéger chaque action sensible côté serveur ; masquer un contrôle dans Vue ne
  remplace jamais une autorisation Laravel.
- Ne pas utiliser de personnage, logo ou illustration Disney sans droits
  d'utilisation démontrés.
- Le SMTP de production reste à décider. Mailpit est le seul transport local.
- En cas de contradiction documentaire ou de décision qui étend le périmètre,
  demander une validation produit avant d'implémenter.

## Git et pull requests

- Partir de `main` et travailler sur une branche `feature/*`, `fix/*`, `docs/*`,
  `chore/*` ou `refactor/*`.
- Ne jamais pousser directement ou forcer un push sur `main`.
- Utiliser Conventional Commits pour les commits et le titre de la pull request.
- Ouvrir les pull requests vers `main`, résoudre les conversations et attendre
  tous les checks requis.
- Le dépôt utilise uniquement Squash & Merge.
- Ne pas créer de tag ni de release manuellement. Release Please maintient la
  Release PR, dont le merge volontaire publie la version SemVer.

Le processus détaillé se trouve dans `CONTRIBUTING.md` et
`docs/quality-ci-cd.md`.
