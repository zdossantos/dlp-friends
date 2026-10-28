# Releases automatiques avec web en rolling update

## Contrat

Après l'activation serveur et GitHub décrite plus bas, fusionner la Release PR
créée par Release Please suffit pour une livraison **compatible avec la version
en cours** : publication et test de l'image AMD64, référence immuable jointe à la
release, contrôles serveur, sauvegarde si le schéma change, migration explicite,
web natif Coolify, puis worker, scheduler et Reverb.

Aucun `artisan down`, arrêt du web, import de données ou retour arrière SQL
n'appartient à ce parcours. MySQL, Redis et Garage restent en place. Une erreur
interrompt la livraison et rend le job GitHub rouge ; une requête acceptée par
Coolify n'est pas considérée comme un déploiement réussi.

**Préparation ne vaut pas activation.** Les fichiers de ce dépôt ne créent ni
compte SSH ni jeton ni configuration sur le serveur. Le job reste inactif tant
que `PRODUCTION_RELEASE_BRANCH` n'est pas défini. Ne pas annoncer la livraison
automatique opérationnelle avant l'essai complet et l'activation ci-dessous.

## 1.16.2 et suite 1.17

La PR de maintenance part de `release/1.16` (v1.16.1) et reprend uniquement les
changements nécessaires au runtime rolling déjà utilisé en production. Son
merge par squash avec un titre `fix:` permet à Release Please de proposer la
**1.16.2**. Ne pas créer de tag manuellement ni fusionner la Release PR 1.17
pour cette livraison.

Reporter les changements d'automatisation sur `main` avec une PR normale : les
changements applicatifs 1.17 restent sur cette branche. Ne pas réécrire son
historique partagé. Lors du passage volontaire à 1.17, changer à la fois la
ligne autorisée dans le relais et `PRODUCTION_RELEASE_BRANCH=main` avant le
merge de la Release PR. Le même état serveur interdit ensuite qu'une 1.16
ancienne remplace la 1.17.

## Compatibilité des migrations et des traitements

Les migrations existantes sont immuables : aucun fichier supprimé ou modifié
n'est admis. Une nouvelle migration doit être relue pour garantir qu'elle reste
compatible avec **l'ancien web et les anciens traitements** pendant le
chevauchement, puis déclarée dans `deployment/rolling-migrations.json` :

```json
{
    "2026_10_28_120000_add_optional_example": "SHA256_DU_FICHIER_RELUE"
}
```

Calcul : `sha256sum database/migrations/2026_10_28_120000_add_optional_example.php`.
La clé omet `.php`. Aucune déclaration n'est nécessaire sans nouvelle migration.
Ce fichier est une attestation de revue, pas une preuve automatique de sûreté.

Ajouter une colonne nullable avec une opération courte est le cas habituel.
Renommer/supprimer une colonne encore utilisée, modifier une contrainte qui
rejette les écritures de l'ancienne version, changer les formats de session ou
les classes/payloads de jobs sans compatibilité nécessite un autre plan.
Les gros index, backfills et conversions nécessitent une évaluation sur une
copie représentative ; un numéro de version mineure ne garantit rien.

Le relais limite l'attente des verrous MySQL à cinq secondes et l'exécution de
migration à trois minutes. Une migration échouée peut avoir appliqué une partie
du DDL : il n'existe **aucun rollback SQL automatique**. Examiner le schéma et le
registre avant toute reprise. Ne jamais supprimer l'état bloquant pour seulement
faire disparaître une erreur.

Le nouveau web devient actif avant le remplacement des traitements. Les jobs et
événements produits par le nouveau code doivent donc être compris par les anciens
workers/Reverb. Le scheduler reste unique. Les workers disposent de 120 secondes
d'arrêt gracieux pour des jobs dont le timeout habituel est 90 secondes.

## Installation opérateur, sans déploiement

Préparer un répertoire de confiance **root, non modifiable par l'utilisateur
SSH**, figé sur le commit relu de cette PR, avec :

- `scripts/operations/rolling_release.py` ;
- `scripts/operations/rolling-probe.php` ;
- `scripts/operations/operations.py` et `release.py` (helpers existants).

Créer `/srv/dlp-operations/rolling-private`, root, mode `0700`. Copier
`rolling-config.example.json` dans `config.json`, mode `0600`, et renseigner les
identifiants observés sur le serveur. `environment_id` est l'identifiant numérique
Coolify, pas l'UUID affiché dans son URL. `mysql_server_uuid` vient d'une lecture
de `SELECT @@server_uuid`; ne jamais le déduire du nom du conteneur. Le fichier
`.env` indiqué est celui déjà monté dans le web natif, dans un dossier root privé.
Ne pas importer de nouvelles variables ou secrets depuis GitHub.

Créer un jeton Coolify dédié aux opérations de lecture, écriture de configuration
et déploiement nécessaires, sans accès « sensitive » aux secrets si le serveur
le permet. Le conserver uniquement dans `coolify.curl`, root `0600` :

```text
header = "Authorization: Bearer JETON_SAISI_HORS_DES_LOGS"
```

Ne pas fournir le jeton Coolify à GitHub. Le relais ne permet d'appeler que les
deux applications configurées ; il ne reçoit ni UUID ni commande de l'appelant.
Le serveur conserve son authentification GHCR de lecture existante.

Créer une identité SSH dédiée `dlp-release-deploy`, mot de passe verrouillé,
sans accès Docker ou shell privilégié. Suivre le modèle root-owned de
[`beta-environment.md`](beta-environment.md) : `authorized_keys` et son dossier
appartiennent à root, avec `restrict,command="sudo -n /usr/local/sbin/dlp-rolling-release"`
pour la seule clé publique générée à cet usage. Le wrapper est :

```sh
#!/bin/sh
[ "$#" -eq 0 ] || exit 64
PATH=/usr/sbin:/usr/bin:/sbin:/bin
export PATH
umask 077
exec /usr/bin/python3 -Es /CHEMIN_COMMIT_RELUE/rolling_release.py \
    --config /srv/dlp-operations/rolling-private/config.json
```

Sudoers : autoriser seulement ce wrapper **sans argument** (`""` dans la règle),
sans `SETENV`. Valider avec `visudo -cf`, contrôler les propriétaires de chaque
parent, puis prouver qu'une commande, une requête hors contrat et un transfert
SSH sont refusés. Ne pas ouvrir temporairement un shell root pour GitHub.

Le verrou local couvre toutes les branches et tous les clients SSH. L'état
`rolling-state.json` est écrit avant la première mutation : après un échec ou
un timeout, toute livraison est bloquée jusqu'à rapprochement manuel de l'état
réel. Après succès, la même référence est idempotente ; changer son image ou
son commit est interdit. Une version plus ancienne est également refusée.

## Activation GitHub et répétition

1. Répéter sur une topologie bêta séparée (web natif + support), avec base, Redis,
   stockage et courrier bêta. Utiliser une image compilée pour la bêta ; ne pas
   faire pointer le navigateur bêta vers Reverb de production. Le relais bêta lit
   exclusivement `rehearsal-image.json`, root `0600` dans son propre `state_dir`,
   issu du build CI vérifié (mêmes champs que `container-image.json`). Cette
   référence privée ne peut pas être choisie par le client SSH. Ne pas importer
   la production pour cet essai. Les fixtures locales ne remplacent pas cet essai.
2. Prouver le succès normal, le maintien des sessions/CSRF, les assets d'une
   page déjà ouverte, les jobs et les messages, ainsi qu'un candidat non prêt.
   Sonder le HTTP en continu, suivre les images réellement actives et confirmer
   l'unicité du scheduler. Tester aussi l'arrêt avant bascule sur migration refusée.
3. Dans l'environnement GitHub **production**, ajouter `PRODUCTION_SSH_PRIVATE_KEY`.
   Au niveau du dépôt, définir `PRODUCTION_SSH_HOST` et
   `PRODUCTION_SSH_KNOWN_HOSTS` (empreinte vérifiée hors bande).
4. Exécuter une demande `action: preflight` depuis le relais installé pour
   vérifier la production sans migration ni déploiement. Elle peut tirer l'image
   et créer un conteneur temporaire limité à 384 Mio / 0,5 CPU.
5. Pour la première 1.16.2, publier avec le garde de branche encore désactivé,
   puis vérifier son `preflight` et livrer cette référence exacte via le relais
   (`action: deploy`) après validation. Conserver son résultat privé et vérifier
   le service. Activer `PRODUCTION_RELEASE_BRANCH=release/1.16` seulement après ces preuves.
   Cette variable est un garde de sélection de branche ; le secret et le relais
   sont indispensables. Un job rouge exige une intervention, jamais un fallback
   vers une livraison non contrôlée.
6. Fusionner volontairement la Release PR 1.16.2. Le workflow garde le SHA et le
   digest de cette release ; ni `latest` ni la branche mouvante ne sont déployés.

La première livraison peut ainsi être contrôlée avant l'activation de la
livraison automatique des releases suivantes. Aucune recréation de release,
modification de tag ou reprise de job ignoré n'est nécessaire. Ne pas relancer
tous les jobs d'une release existante : Release Please ne réémet pas sa sortie
`release_created`. Après un échec, rapprocher l'état serveur avant de relancer
uniquement la livraison de la référence d'origine.

## Disponibilité et limites

Objectif de la bascule web : **zéro interruption HTTP** ; l'ancien conteneur
continue jusqu'à disponibilité du nouveau, puis termine ses requêtes. Une image
non prête ne doit pas le remplacer. Cela exige une mémoire suffisante, `/ready`,
aucun port hôte fixe, aucun nom de conteneur forcé et le drainage du runtime.
Le relais exige au moins 1 Gio de mémoire disponible et 4 Gio libres sur disque.
Ces seuils protègent le chevauchement ; ils ne prouvent pas la capacité en charge.

Les traitements sont en Compose et ne sont pas en rolling natif. Leurs tâches
peuvent attendre pendant le remplacement ; Reverb impose une reconnexion
WebSocket. Compter habituellement quelques dizaines de secondes de retard ou
reconnexion, jusqu'à deux minutes de drainage d'un job en cours. Cela ne ferme
pas l'accès HTTP, mais **ce n'est pas zéro interruption de tous les services**.

Ordre de grandeur d'une release habituelle : 5–15 minutes de build/CI et
1–4 minutes d'exécution serveur, hors migration longue et file d'attente des
runners. Ce sont des estimations, pas des durées garanties. La préparation et
l'installation de l'automatisation ne nécessitent aucun redémarrage de production.

La sauvegarde automatique est un dump transactionnel avant changement du schéma,
conservé privé sur le serveur. Son existence n'est pas une preuve de restauration
ni une sauvegarde hors serveur. Garder les sauvegardes habituelles et leurs tests
de restauration. Aucun effacement automatique des sauvegardes ou anciens assets
n'est ajouté dans cette PR.

Sources : [rolling Coolify](https://coolify.io/docs/applications/deployments/rolling-updates),
[images Docker Coolify](https://coolify.io/docs/applications/deployments/docker-image),
[migrations Laravel](https://laravel.com/docs/13.x/migrations).
