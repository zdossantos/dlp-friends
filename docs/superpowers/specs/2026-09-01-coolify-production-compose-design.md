# Stack Docker Compose de production pour Coolify

## Objectif

Fournir une configuration Docker Compose dédiée à la production, versionnée
dans le dépôt et directement déployable par Coolify depuis `main`.

## Architecture

La stack de production conserve les quatre processus applicatifs existants :
`web`, `worker`, `scheduler` et `reverb`. Ils partagent une image construite une
seule fois depuis la cible `runtime` du `Dockerfile`. MySQL 8.4, Redis 7.4 et
MinIO restent privés sur le réseau Compose ; seuls HTTP et WebSocket sont
adressables par le proxy Coolify.

Le fichier de production est distinct de `compose.yaml`, qui reste la stack de
développement local avec Mailpit et ses ports d’administration. Les variables
critiques utilisent l’interpolation Compose obligatoire `${VARIABLE:?}` afin
que Coolify refuse une configuration incomplète avant de créer les conteneurs.

## Données et secrets

MySQL et MinIO utilisent des volumes nommés persistants. Redis ne persiste pas
les données, conformément à son rôle actuel de cache, sessions et files. Aucun
port de données n’est publié sur l’hôte.

Les secrets Laravel, MySQL, MinIO, Reverb et Resend sont fournis uniquement par
les variables d’environnement Coolify. Aucun secret ou défaut utilisable en
production ne figure dans le dépôt. Resend devient le transport transactionnel
de production via le mailer Laravel natif et la dépendance officielle requise.

## Déploiement

Coolify construit la cible `runtime` et réutilise l’image résultante pour les
quatre processus applicatifs. Le service `web` expose le port HTTP interne 80
et `reverb` le port interne 8080 au proxy Coolify. Les autres services restent
strictement internes.

Chaque processus long possède un healthcheck, une politique de redémarrage et
une limite de ressources. La route `/up` contrôle le service HTTP. Les
migrations ne sont jamais lancées au démarrage : l’opérateur exécute
explicitement `php artisan migrate --force`, puis redémarre les workers après
un déploiement.

## Vérification

Un test Pest protège le contrat de la stack : services attendus, image
partagée, variables obligatoires, absence de Mailpit et de ports de données,
volumes persistants, healthchecks et migrations non automatiques. La
configuration est également validée par `docker compose config`, puis l’image
runtime est construite. La documentation décrit la création de la ressource
Coolify, les variables, Resend, les migrations, la santé, les sauvegardes et la
restauration.

## Hors périmètre

La stack n’ajoute ni migrations automatiques, ni serveur SMTP, ni haute
disponibilité, ni Kubernetes, ni déclenchement Coolify depuis GitHub Actions.

