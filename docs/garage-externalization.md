# Stockage objet Garage indépendant

Cette procédure remplace uniquement le MinIO de production. Le développement
local conserve MinIO dans `compose.yaml`. En production, Garage est une
ressource Coolify indépendante : un redéploiement applicatif ne la recrée pas
et ne touche pas à ses volumes.

## Création avec le service natif Coolify

Dans le projet et l'environnement de production Coolify :

1. ouvrir **New Resource**, puis **Services** ;
2. sélectionner le service natif **Garage** fourni par Coolify ;
3. nommer la ressource et activer **Connect to Predefined Network** ;
4. conserver l'API S3 et l'administration privées, sans domaine public ;
5. démarrer la ressource et vérifier qu'elle est saine.

La version de Garage, sa configuration, ses volumes persistants et ses secrets
de service sont gérés par le modèle natif Coolify. Aucun Compose Garage, fichier
de configuration Garage ou secret Garage n'est conservé dans ce dépôt.

Après le premier démarrage, ouvrir le terminal du conteneur Garage :

```sh
/garage node id
/garage layout assign -z dc1 -c 10G <identifiant-du-noeud>
/garage layout apply --version 1
/garage bucket create dlp-friends
/garage key create dlp-friends-app
/garage bucket allow --read --write --owner --key <identifiant-cle> dlp-friends
```

La capacité doit rester inférieure à l'espace réellement disponible. La clé
applicative est autorisée uniquement sur `dlp-friends`. Le bucket reste privé :
les téléchargements passent par Laravel après ses contrôles d'autorisation.

Dans l'application Coolify, définir les variables d'exécution suivantes :

```dotenv
FILESYSTEM_DISK=s3
AWS_ENDPOINT=http://<nom-service-garage>:3900
AWS_DEFAULT_REGION=garage
AWS_BUCKET=dlp-friends
AWS_ACCESS_KEY_ID=<identifiant-cle-applicative>
AWS_SECRET_ACCESS_KEY=<secret-cle-applicative>
AWS_USE_PATH_STYLE_ENDPOINT=true
```

Garage ne fournit pas `GetObjectAcl`. DLP Friends n'utilise ni ACL objet ni URL
S3 temporaire. Le test d'intégration vérifie lecture, écriture, téléchargement et suppression contre une ressource Garage préparée.

## Migration depuis MinIO

Ne modifier aucune clé d'objet. Avant la copie, placer les écritures d'avatars
en maintenance et relever côté MinIO le nombre total d’objets et la somme des
tailles. Configurer deux remotes S3 privés dans `rclone`, avec adressage
path-style, puis copier et contrôler les données :

```sh
rclone copy minio:dlp-friends garage:dlp-friends --dry-run --metadata
rclone copy minio:dlp-friends garage:dlp-friends --metadata --checksum --progress
rclone size minio:dlp-friends
rclone size garage:dlp-friends
rclone check minio:dlp-friends garage:dlp-friends --size-only --one-way
```

Les deux inventaires doivent avoir le même nombre total d’objets et la même
somme des tailles. Pour un échantillon incluant chaque extension, comparer aussi
un SHA-256 après téléchargement depuis chaque source.

## Bascule et suppression de MinIO

1. Remplacer les variables `AWS_*` par celles de Garage et redéployer.
2. Vérifier `/up`, puis une lecture, une écriture et une suppression via Laravel.
3. Vérifier le nombre d'objets et afficher un avatar préexistant.
4. Sortir de maintenance et surveiller l'application et Garage.
5. Supprimer définitivement le conteneur et le volume MinIO de production.

## Sauvegarde et restauration

Sauvegarder quotidiennement les volumes Garage hors du serveur pendant une
fenêtre cohérente, avec un inventaire du bucket. Éprouver mensuellement une
restauration isolée, vérifier la santé du service, comparer l'inventaire et
télécharger un échantillon avant de raccorder une application.
