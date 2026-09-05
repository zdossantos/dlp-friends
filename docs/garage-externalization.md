# Stockage objet Garage indépendant

Cette procédure remplace uniquement le MinIO de production. Le développement
local conserve MinIO dans `compose.yaml`. La ressource Garage vit séparément de
l'application : un redéploiement applicatif ne la recrée pas et ne touche pas à
ses volumes.

## Topologie retenue

- image épinglée `dxflrs/garage:v2.3.0` ;
- un nœud et un facteur de réplication de 1, adaptés au VPS actuel ;
- métadonnées persistantes dans `/var/lib/garage/meta` ;
- objets persistants dans `/var/lib/garage/data` ;
- API S3 privée sur le port interne 3900 ;
- API d'administration privée sur le port interne 3903 ;
- contrôle de santé Garage sur `/health` et healthcheck CLI `/garage status` ;
- aucun port publié sur Internet ;
- raccordement par **Connect to Predefined Network** dans Coolify.

Le facteur 1 protège le cycle de vie du service mais ne rend pas les données
tolérantes à la perte du VPS. Une sauvegarde hors serveur reste obligatoire.

## Création dans Coolify

Créer une application Docker Compose distincte depuis le même dépôt, sur
`compose.garage.yaml`. Générer séparément trois valeurs aléatoires fortes pour
`GARAGE_RPC_SECRET` (64 caractères hexadécimaux), `GARAGE_ADMIN_TOKEN` et
`GARAGE_METRICS_TOKEN`. Les conserver uniquement dans les secrets Coolify.

Après le premier démarrage, ouvrir le terminal du conteneur Garage :

```sh
/garage node id
/garage layout assign -z production -c 20G <identifiant-du-noeud>
/garage layout apply --version 1
/garage bucket create dlp-friends
/garage key create dlp-friends-app
/garage bucket allow --read --write --owner --key <identifiant-cle> dlp-friends
```

La capacité doit rester inférieure à l'espace réellement disponible. La clé
applicative n'est autorisée que sur `dlp-friends`. Ne pas activer l'accès Web du
bucket : le téléchargement passe par `AvatarImageController` après les contrôles
Laravel.

Dans l'application Coolify, définir les secrets d'exécution suivants :

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
S3 temporaire : le bucket privé, les droits limités de la clé et la réponse
streamée par Laravel couvrent les opérations présentes. Le test d'intégration
vérifie lecture, écriture, téléchargement et suppression contre Garage.

## Migration depuis MinIO

Ne modifier aucune clé d'objet. Avant la copie, placer les écritures d'avatars
en maintenance et relever côté MinIO le nombre total d’objets et la somme des
tailles. Configurer deux remotes S3 privés dans `rclone`, avec adressage
path-style, puis effectuer d'abord une simulation :

```sh
rclone copy minio:dlp-friends garage:dlp-friends --dry-run --metadata
rclone copy minio:dlp-friends garage:dlp-friends --metadata --checksum --progress
rclone size minio:dlp-friends
rclone size garage:dlp-friends
rclone check minio:dlp-friends garage:dlp-friends --size-only --one-way
```

Les deux inventaires doivent avoir le même nombre total d’objets et la même
somme des tailles. `rclone check --size-only` évite de traiter un ETag multipart
comme un MD5 universel. Pour un échantillon incluant au moins un objet de chaque
extension, comparer aussi un SHA-256 calculé après téléchargement depuis chaque
source. Les fichiers migrés doivent conserver leur clé et leur type de contenu.

Avec les variables Garage injectées dans un conteneur `web` isolé, exécuter le
test d'intégration puis lire un avatar existant.

## Bascule et validation

1. Conserver l'application en maintenance après la copie finale.
2. Remplacer les cinq variables `AWS_*` par celles de Garage et redéployer.
3. Vérifier `/up`, puis une lecture, une écriture et une suppression via Laravel.
4. Afficher un avatar préexistant depuis un compte non administrateur.
5. Sortir de maintenance et surveiller `web`, `worker` et les journaux Garage.
6. Arrêter MinIO sans supprimer son conteneur historique ni son volume.

Pour un retour arrière, remettre les anciennes variables MinIO, redéployer,
vérifier un avatar puis rouvrir le trafic. Si des écritures ont eu lieu après la
bascule, recopier auparavant les nouvelles clés de Garage vers MinIO. Toujours
conserver MinIO et son volume jusqu'à une validation explicite de la migration.

## Sauvegarde et restauration

Sauvegarder quotidiennement les deux volumes Garage hors du serveur pendant une
fenêtre cohérente, ainsi qu'un inventaire du bucket. La copie des seuls blocs de
données sans les métadonnées n'est pas restaurable.

Éprouver mensuellement une restauration isolée : restaurer les deux volumes sur
une ressource Garage sans trafic, démarrer la même version, vérifier `/health`,
comparer l'inventaire et télécharger un échantillon d'objets. Ne raccorder
l'application qu'après ces contrôles.
