# Initialisation Laravel et services Docker — Design

## Périmètre

Cette étape initialise le socle technique de DLP Friends sans implémenter les fonctionnalités métier du MVP. Elle correspond à la tâche 1 de `docs/implementation-plan.md` : application Laravel/Inertia/Vue exécutable, services locaux reproductibles et base de déploiement compatible avec Coolify.

Les README et documents existants restent la source de vérité et ne sont pas remplacés par le scaffolding.

## Socle applicatif

Le projet est généré à partir du starter kit Vue officiel de Laravel avec TypeScript. Le starter fournit Laravel, l'authentification, Inertia, Vue 3, Ziggy, Tailwind CSS et Vite. Inertia ne sera pas installé manuellement.

Laravel Sail ne sera pas utilisé. Le projet maintient sa propre image applicative et son propre fichier Compose afin de respecter exactement la topologie documentée.

Le scaffolding utilise les dernières versions stables et mutuellement compatibles disponibles au moment de l'installation. Les versions réellement installées sont verrouillées dans `composer.lock` et `package-lock.json` puis documentées dans le README.

Le code applicatif est placé à la racine du dépôt sans écraser `README.md` ni `docs/`.

## Architecture Docker

Un seul fichier `compose.yaml` décrit la pile. Laravel Sail n'est pas utilisé.

Les services `web`, `worker`, `scheduler` et `reverb` partagent une même image applicative multi-stage. Une étape Node compile les assets Vite ; une étape PHP d'exécution contient PHP-FPM, les extensions requises et le code final. Nginx sert l'application et transmet PHP à PHP-FPM selon la structure retenue dans l'image.

Le Compose comprend :

- `web` pour HTTP ;
- `worker` pour `php artisan queue:work` avec limites explicites ;
- `scheduler` pour `php artisan schedule:work` en instance unique ;
- `reverb` pour le serveur WebSocket Laravel ;
- `mysql` pour les données applicatives ;
- `redis` pour cache, verrous et queues ;
- `minio` pour le stockage S3-compatible privé ;
- `mailpit` comme transport SMTP et interface de lecture en développement.

MySQL, Redis et les services applicatifs internes ne publient pas de port hôte inutile. En développement, seuls l'application, l'interface Mailpit, l'API MinIO si nécessaire et Reverb sont accessibles depuis l'hôte.

MySQL et MinIO utilisent des volumes persistants. Tous les services longs disposent d'un healthcheck et d'une politique de redémarrage appropriée. Les secrets ne figurent ni dans l'image ni dans Git ; `.env.example` décrit les variables sans valeur sensible.

## Démarrage et données

Le conteneur applicatif attend les dépendances nécessaires, prépare les caches Laravel compatibles avec l'environnement et démarre son processus dédié. Il n'exécute aucune migration automatiquement. Les migrations restent une action explicite avec `php artisan migrate` en développement et `php artisan migrate --force` lors du déploiement.

Le développement utilise MySQL, Redis, MinIO et Mailpit. Laravel envoie les courriels à Mailpit par SMTP. Le transport SMTP de production est volontairement différé.

## Santé et comportement en échec

La route publique Laravel `/up` sert de contrôle de santé sans exposer de configuration. Un test de fonctionnalité vérifie une réponse HTTP 200.

Les healthchecks empêchent de considérer comme sain un service qui ne répond pas. Les dépendances déclarées dans Compose utilisent les états de santé lorsque cela est pertinent. Les processus `worker`, `scheduler` et `reverb` échouent visiblement et sont redémarrés selon leur politique au lieu de masquer une configuration invalide.

## Vérification

L'initialisation est acceptée lorsque les contrôles suivants réussissent :

1. installation verrouillée des dépendances Composer et npm ;
2. compilation Vite ;
3. test Laravel ciblé de `/up`, puis suite Laravel générée ;
4. validation syntaxique de `compose.yaml` ;
5. construction des images Docker ;
6. démarrage de la pile de développement ;
7. réponse HTTP 200 de `/up` depuis l'hôte ;
8. santé confirmée de MySQL, Redis, MinIO et Mailpit ;
9. arrêt propre de la pile sans supprimer les volumes.

Le README est mis à jour avec les prérequis, commandes de démarrage, ports locaux, commandes Artisan dans Docker et limites connues de cette première étape.

## Hors périmètre

Cette initialisation n'ajoute pas encore Socialite, les règles de majorité, les profils, le matching, la messagerie métier, le catalogue, l'administration, la suppression de compte ni les workflows CI. Ces éléments restent dans les tâches suivantes de `docs/implementation-plan.md`.
