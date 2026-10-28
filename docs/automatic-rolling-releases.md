# Releases automatiques avec Coolify

## Fonctionnement

Une fois l'activation vérifiée, fusionner la Release PR Release Please suffit :

1. GitHub construit et teste l'image de la release, puis la publie sur GHCR.
2. Le script API Coolify existant configure le support avec ce digest et le tag
   Git de la release, pour que le Compose corresponde aussi à cette version.
3. La commande de démarrage Compose lance explicitement un conteneur temporaire
   de la **nouvelle image** : `run --rm --no-deps worker php artisan migrate --force --no-interaction`.
4. Si la migration réussit, `up -d --wait --wait-timeout 180` remplace les workers,
   le scheduler et Reverb. GitHub attend le résultat final du déploiement Coolify.
5. GitHub configure ensuite le web avec le même digest et demande son déploiement
   **rolling natif Coolify**, puis attend son résultat final.

La case « Pre-deployment » exécute la commande dans le conteneur déjà lancé :
`php artisan migrate` y lirait les anciennes migrations. Ici, la commande Compose
utilise la nouvelle image. Aucune migration n'est ajoutée à l'entrypoint Docker.

Une erreur arrête le job. Aucun déploiement web après une migration échouée,
aucun rollback SQL automatique, aucun `artisan down` et aucun import de données.
Un timeout exige de vérifier l'état Coolify avant toute relance : la requête
peut avoir été acceptée. Un DDL MySQL peut être partiellement appliqué après erreur.
Les sauvegardes et procédures de restauration habituelles restent nécessaires ;
ce parcours natif n'ajoute pas de dump automatique à chaque déploiement.

## Configuration et activation

Réutiliser `COOLIFY_API_URL`, `COOLIFY_APPLICATION_UUID` (support) et le secret
GitHub existant `COOLIFY_TOKEN`. Ajouter `COOLIFY_WEB_APPLICATION_UUID` pour le web.
Aucun compte SSH ni relais de déploiement supplémentaire n'est nécessaire.
Le support doit utiliser `/compose.production-support.yaml` ; le web doit être
une application Docker Image du même environnement avec son healthcheck `/ready` actif.
Les ports hôte fixes empêchent le chevauchement des conteneurs web.

Le job est désactivé tant que `PRODUCTION_RELEASE_BRANCH` est absent. Répéter en
bêta avec ses propres base, Redis, courrier et images avant de l'activer.
Contrôler la migration avec la nouvelle image, l'arrêt sur erreur, le rolling,
les sessions, les assets déjà chargés, les jobs et les reconnexions WebSocket.
Les tests avec une API locale ne prouvent pas ces comportements sur le serveur.

La maintenance part de v1.16.1 sur `release/1.16` et doit produire **1.16.2** via
Release Please. Reporter le correctif sur `main` sans publier 1.17 maintenant.
Configurer `PRODUCTION_RELEASE_BRANCH=release/1.16` après validation ; avant la
future release 1.17, sélectionner `main`. Ne jamais créer de tag manuellement.
Ne pas laisser une ancienne branche autorisée après le passage à 1.17.

## Disponibilité

Pour des migrations compatibles et courtes, l'objectif est zéro interruption
HTTP grâce au rolling natif et au drainage du web. Le support Compose n'est pas
en rolling : jobs retardés et reconnexion WebSocket pendant son remplacement.
Compter environ 5–15 minutes de CI et 1–4 minutes serveur hors migration longue.

Une migration incompatible peut casser l'ancien web entre la migration et la
bascule. Le rolling ne supprime pas cette interruption. Prévoir une intervention
et une sauvegarde restaurable pour ces releases ; aucune durée n'est garantie.
Une migration bloquée ou échouée nécessite un diagnostic avant de reprendre.
