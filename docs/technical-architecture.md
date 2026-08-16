# Architecture technique détaillée

## Socle obligatoire

Créer le projet à partir du **starter kit Vue officiel de Laravel**. Ne pas installer Inertia manuellement. Le starter kit fournit la structure Laravel/Inertia/Vue, TypeScript, Tailwind et les bases d'authentification ; le projet l'étend pour les besoins de DLP Friends.

## Versions et stack

- Dernière version stable de Laravel et de PHP compatible au moment du scaffolding. Les versions exactes sont verrouillées dans `composer.lock` et `package-lock.json`; aucune dépendance de production n'utilise une version flottante.
- Inertia avec ziggy avec Vue 3 Composition API et TypeScript.
- Tailwind CSS, shadcn-vue comme bibliothèque de composants principale, Reka UI comme complément accessible.
- MySQL comme base relationnelle.
- Redis pour cache, verrouillage et queues Laravel.
- Laravel Socialite pour les fournisseurs Google et Apple.
- Laravel Reverb + Laravel Echo pour le temps réel de messagerie.
- Stockage des fichiers via le driver S3-compatible de Laravel en production. MinIO privé est le choix MVP sur Coolify ; le disque local est réservé au développement et aux tests.
- Mailpit capture les e-mails en développement. Le transport SMTP de production est différé et n'est pas inclus dans le socle initial.

## Services Docker

Le `compose` de développement et le déploiement Coolify séparent les responsabilités :

| Service | Responsabilité |
| --- | --- |
| `web` | Application Laravel HTTP, PHP-FPM et serveur web |
| `worker` | Exécution continue des Laravel Queues avec le même code applicatif |
| `scheduler` | Exécution unique des tâches Laravel planifiées |
| `reverb` | Serveur WebSocket Laravel des événements privés de messagerie |
| `mysql` | Données persistantes MySQL |
| `redis` | Cache, verrouillage et files de jobs, uniquement en réseau privé |
| `minio` | Stockage objet privé des photos de profil et avatars téléversés |
| `mailpit` | Capture SMTP et interface de consultation strictement locales |

`web`, `worker`, `scheduler` et `reverb` sont construits depuis la même image versionnée ; seul leur point d'entrée change. MySQL et MinIO utilisent des volumes persistants. Redis n'est persistant que si la stratégie de queue l'exige. Aucun secret n'est inclus dans l'image ou dans Git.

Seul `web` reçoit le domaine public de l'application. MySQL, Redis, MinIO, worker et scheduler restent strictement sur le réseau privé. Reverb n'expose que le point d'entrée WebSocket indispensable via le proxy. Mailpit n'est jamais déployé en production. Le futur transport SMTP de production utilisera des identifiants stockés dans Coolify.

## Images et exécution

- Dockerfile multi-stage : compilation des assets Vite dans une étape Node, exécution PHP dans une étape séparée et minimale.
- Le conteneur web démarre avec la configuration Laravel mise en cache ; il ne lance pas de migration au démarrage.
- Les migrations sont exécutées une seule fois lors du déploiement avec `php artisan migrate --force`. Elles doivent être rétrocompatibles avec la version applicative précédente; les migrations destructrices sont réalisées en plusieurs déploiements.
- Le worker exécute `php artisan queue:work` avec limites de mémoire, tentatives et délai explicites ; `queue:restart` suit chaque déploiement.
- Le scheduler exécute `php artisan schedule:work` en une seule instance. Toute tâche non idempotente utilise un verrouillage Laravel.
- Le traitement des images est un job : validation, suppression EXIF, redimensionnement et écriture MinIO. L'interface ne doit pas attendre ce traitement.

## Déploiement Coolify

- Coolify est connecté au dépôt et déploie automatiquement la configuration Docker Compose versionnée dès qu'un nouveau commit arrive sur `main`. Ce fichier Compose est la source de vérité de la stack de production.
- Des environnements séparés existent au minimum pour `develop` et `main`; `main` est la production.
- Le déploiement de production intervient automatiquement après fusion
  volontaire de la PR `automation/promote-develop → main`, construite depuis le
  dernier `main` et intégrant `develop`; aucun workflow GitHub ne déclenche
  Coolify.
- Définir une route de santé non authentifiée, par exemple `/up`, qui ne divulgue aucun secret.
- Définir des `healthcheck` Docker pour chaque service long-vivant et des limites CPU/mémoire par service.
- Les variables critiques sont déclarées comme requises dans Compose et définies dans Coolify, jamais commitées.
- La V1 utilise un déploiement Compose mono-instance. Le zéro interruption et le rolling update ne sont pas une promesse du MVP; ils ne seront envisagés qu'avec une topologie compatible.

## Configuration par environnement

| Sujet | Développement | Intégration / production |
| --- | --- | --- |
| Base de données | MySQL Docker local | MySQL privé persistant Coolify |
| Cache / queue | Redis Docker local | Redis privé Coolify |
| Fichiers | disque local | MinIO S3-compatible privé |
| Mail | Mailpit | Transport SMTP à définir avant la mise en production |
| URL applicative | localhost | HTTPS et domaine Coolify |
| Débogage | activé localement | `APP_DEBUG=false` |

## Frontière applicative

Laravel est la source de vérité : authentification, autorisations, règles de match, conversations et suppression de données sont côté serveur. Vue/Inertia affiche les pages et appelle les routes Laravel ; ne pas créer d'API séparée sans besoin démontré. Chaque action sensible utilise une Policy Laravel explicite.

## Comptes, profils et rôles

- `users` porte uniquement les données de compte privées; `profiles` porte le nom d'affichage et les informations publiques.
- La vérification d'e-mail précède l'onboarding. Le middleware `profile.complete` protège ensuite l'espace membre et redirige les profils incomplets vers leur création.
- Les rôles normalisés `user` et `admin` sont stockés dans `roles` et `user_roles`. Toute inscription reçoit `user`.
- Le middleware `role:admin` protège le dashboard côté serveur; masquer son lien dans Vue n'est qu'un complément d'interface.
- `php artisan user:assign-role {email} {role}` utilise l'action idempotente `AssignRole` pour l'administration locale.
- Le dashboard admin ne reçoit que des agrégats et une projection limitée des inscriptions récentes, sans date de naissance, secret ni mot de passe.

## Simplicité et abstraction

Toute implémentation suit `engineering-principles.md`. La séparation en modèles, Policies, Form Requests, Actions, jobs et composants Vue sert à clarifier un besoin actuel, jamais à anticiper une architecture future. Une couche supplémentaire doit démontrer qu'elle réduit la complexité globale avant d'être introduite.
