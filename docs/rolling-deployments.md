# Déploiements web avec recouvrement

État au 28 octobre 2026 : **production basculée sur le rolling web natif Coolify**, support séparé et bêta classique rétablie. La transition initiale a produit des erreurs sur une fenêtre d'environ 100 secondes. Le remplacement natif suivant a réussi sans 5xx dans le proxy ; une requête de la sonde a néanmoins expiré lors d'un pic de latence ultérieur. Ces résultats ne garantissent pas zéro interruption sous toute charge.

## Base de la transition

Partir du tag publié `v1.16.1`, commit `e418a4379fd758820d58d795f88a40517c5ff1f3`. L’image des processus de support conserve cette révision : `ghcr.io/zdossantos/dlp-friends@sha256:dd44c29d39410e687d1a6d8b1457ae058f1351a26e088d8e4e7aedc4bed659e8`.

Les changements non publiés de `main` ne doivent pas entrer dans l’image de transition. Une fusion de la PR d’infrastructure vers `main` ne change pas cette règle : construire le **SHA de la branche issue de la release**, conservé sur GitHub, et vérifier son étiquette OCI. Ne créer ni tag Git ni release manuellement.

## Format retenu

- Une ressource Coolify **Docker Image** pour `web`, avec digest GHCR immuable, port interne 80, aucun port publié sur l’hôte, aucun nom/IP fixe et pas de preview PR. Compose ne bénéficie pas du rolling natif.
- Le web conserve exactement les clés, sessions Redis, préfixes, base MySQL, stockage S3 et paramètres de diffusion de production. Pas de migration ni déplacement de données pour cette transition.
- Worker, scheduler et Reverb restent initialement sur leur image actuelle. Un seul scheduler. Le nom privé de Reverb doit être vérifié depuis la nouvelle ressource ; ne pas compter sur un alias générique partagé entre applications.
- Volume persistant partagé **uniquement** sur `/var/www/html/public/build/assets`. Chaque image garde son manifeste et son service worker. L’entrée web copie ses assets hashés depuis `/opt/dlp-build-assets`, conserve les anciens fichiers et termine cette copie avant de démarrer HTTP.
- Pas de déploiements concurrents sur ce volume. Aucun nettoyage automatique des anciens fichiers dans cette première version : surveiller sa taille ; supprimer seulement après expiration des onglets, PWA et images de rollback concernés.

Le `HEALTHCHECK` de l’image interroge `/ready` (HTTP 200/503, JSON générique, sans cookie ni cache HTTP). Il réutilise le contrôle de base/migrations/maintenance, lit le cache et vérifie le Redis de session. `/up` reste une preuve de vie distincte. Les checks Compose historiques restent sur `/up` pour préserver la procédure de maintenance existante.

Le processus principal retire d'abord sa readiness avec un marqueur local au conteneur, garde HTTP ouvert pendant 15 secondes pour la propagation au proxy, puis transmet l’arrêt gracieux à Nginx, attend ses requêtes et termine PHP-FPM avec ses traitements après réponse. Configurer la sonde Coolify sur `/ready`, port 80, intervalle 5 secondes, timeout 5 secondes et **une seule tentative échouée** avant état unhealthy. Un nombre supérieur retarderait le retrait au-delà du délai prévu. Fixer le **Stop Grace Period** Coolify selon les essais réels, au moins 120 secondes pour commencer la répétition. Une requête dépassant cette limite peut être tuée ; le rolling ne rend pas une écriture rejouable. La fenêtre de 15 secondes reste à valider sous charge et ne garantit pas la propagation d'un proxy bloqué.

## Construire une image précise

Le workflow manuel **Candidate image** construit un SHA complet, pour `beta` ou `production`, et ne déploie rien. Il doit d’abord être disponible sur la branche par défaut pour être déclenché. Il vérifie le SHA, sélectionne les variables publiques Reverb de la cible, construit pour `linux/amd64`, exécute les tests Docker, publie puis affiche le digest.

```sh
gh workflow run candidate-image.yml -f commit=<SHA_COMPLET_DE_LA_BRANCHE_RELEASE> -f target=beta
```

Construire ensuite la variante `production` du **même SHA**, avec les variables publiques de production. Les deux digests diffèrent car la configuration du frontend est intégrée au build. Vérifier révision, arbre source et empreinte des migrations. Tester le digest téléchargé sur le serveur avant toute affectation de domaine.

Les variables `VITE_REVERB_*` sont publiques. Ne pas y ajouter de secret. Aucun jeton Coolify de production n’est nécessaire pour publier une image. Release Please publie les images ; il ne déploie pas automatiquement cette ressource de production. La procédure historique avec maintenance reste réservée aux stacks complètes compatibles avec ses scripts.

## Répétition serveur obligatoire

La répétition a utilisé une ressource native isolée. Après les essais, `hhz9n4q2qzxppne4q0rf8phx` a retrouvé `/compose.beta.yaml`, sa source `ebc53ac955f8f07c9517e22ad6c420405a33582a` et ses quatre services. La ressource native `c4xzgs0ydhcx2lzpr1sz8ef1` est arrêtée, sans domaine, avec ses images et son volume conservés. L'automatisation bêta existante reste ainsi compatible. Aucun import de production vers la bêta.

Créer une ressource web Docker Image beta avec les dépendances beta isolées, sans worker ni scheduler supplémentaires. Préparer et mesurer :

1. Ancienne et nouvelle image sous trafic continu : page publique sans cache, parcours membre de test, session conservée et écriture vérifiée une seule fois.
2. Nouvelle image non prête ou démarrage trop lent : ancienne instance conservée et trafic toujours réussi.
3. Arrêt pendant requête longue, upload et traitement après réponse ; vérifier les effets, pas seulement le statut HTTP.
4. Ancien onglet/PWA et nouveaux assets ; retour au digest précédent après écritures compatibles.
5. Journal Coolify montrant recouvrement, nouvelle instance saine puis retrait de l’ancienne ; erreurs HTTP/réseau et latences conservées.
6. Connectivité Reverb et rattrapage client ; aucun redémarrage de Reverb, job ou scheduler involontaire.
7. Marge mémoire sous charge, récupération de l’ancien digest et sauvegarde/restauration vérifiée.

Aucun message ou mail de test vers des membres réels. Les essais locaux utilisent seulement des ressources synthétiques.

## Première bascule de production

### Séparer les processus de support

`compose.production-support.yaml` et `compose.beta-support.yaml` contiennent seulement worker, scheduler et Reverb. Ils sont autonomes car le parseur Coolify ne résout pas l'héritage Compose avant son traitement. Les tests d'infrastructure comparent leur configuration à celle des stacks complètes. Ils ajoutent un arrêt gracieux de 120 secondes. Le service Reverb porte le nom `dlp-friends-production-reverb` ou `dlp-friends-beta-reverb` : Coolify remplace les aliases du réseau partagé par le nom de service, donc un alias personnalisé seul ne suffit pas. Réaffecter le domaine WebSocket au service renommé avant son déploiement.

Conserver le digest actuel des processus de support lors de la séparation. Vérifier la résolution de `dlp-friends-production-reverb` depuis le nouveau web avant de lui attribuer le domaine. Ne jamais démarrer une seconde stack de support tant que l'ancien scheduler tourne.

La procédure `scripts/operations/release.py` et l'automatisation bêta attendent encore une stack complète à quatre services. **Ne pas les utiliser sur une topologie séparée** : `prepare` peut activer la maintenance avant de rencontrer une incompatibilité. Leur adaptation ou le maintien de la bêta classique après la répétition est nécessaire avant clôture. Ces fichiers seuls n'activent aucun déploiement.

Pour une livraison web compatible après séparation : construire et vérifier un digest précis, contrôler migrations et compatibilité avec les processus de support, relever l'ancien digest et la santé, lancer un seul redéploiement natif, mesurer les réponses et les sessions pendant le recouvrement, puis vérifier les parcours et les journaux. En cas d'échec de santé, conserver l'ancienne instance ; en cas de régression après bascule, réaffecter le digest précédent et redéployer, sans migration inverse automatique. Une livraison qui nécessite de nouveaux jobs, schémas ou processus ne relève pas de cette procédure web seule.

### État opérationnel retenu

Le web est construit depuis `504bcb38e2da65ae97ee80b3b897f5af99b9c0e7`, branche conservée `feature/rolling-release-1-16-1`, issue de `v1.16.1`. Les modifications applicatives non publiées de `main` sont exclues. Les premiers digests du commit `6f8f2b5` ont été rejetés après les essais du retrait au proxy ; ne pas les employer.

| Cible                                                            | Digest `ghcr.io/zdossantos/dlp-friends`                                   |
| ---------------------------------------------------------------- | ------------------------------------------------------------------------- |
| Production web, révision OCI `504bcb3` vérifiée sur serveur      | `sha256:6d812ede8f1be54ed6ae823276bf9edfafc2b3030c6bd9eea8cb2344db54785e` |
| Bêta de répétition, même source ; ressource arrêtée après essais | `sha256:b8188989f0e7fae25b90921f30ace927b093c438ecc26a4b0a850391f82dfadb` |
| Production support inchangé, release `v1.16.1`                   | `sha256:dd44c29d39410e687d1a6d8b1457ae058f1351a26e088d8e4e7aedc4bed659e8` |

Builds candidats : GitHub `40687480178` (bêta) et `40687485517` (production).

- Web natif `dntfcsbhnoxkwelfon1bzn5i` : domaines `dlp-friends.fr` et `www.dlp-friends.fr`, port 80, `/ready`, santé 5/5/1/20, arrêt 120 secondes, mémoire 512 Mio, CPU 1. Aucun hook de migration : l'exemple affiché est un placeholder, la valeur réelle est vide.
- Configuration serveur `/data/coolify/applications/dntfcsbhnoxkwelfon1bzn5i/runtime/.env`, montée sur `/var/www/html/.env`, parent réservé à root. Ne jamais publier son contenu. Une modification de configuration doit atteindre ce fichier avant redéploiement ; modifier seulement les variables de l'ancienne stack ne met pas le web à jour.
- Volume `dntfcsbhnoxkwelfon1bzn5i-dlp-friends-production-rolling-assets` limité aux assets. Les 193 fichiers de l'ancien web ont été préchargés et comparés octet pour octet. Fichiers de migration identiques entre ancien et nouveau web.
- Support `kma0dju8bamipey0sd1up9gs` : branche `main`, commit fixé `2f36fdf569c9f18a7eb50874813f73909990e183`, chemin `/compose.production-support.yaml`. Ce commit d'infrastructure ne sert pas de source à l'image web. `realtime.dlp-friends.fr` pointe vers `dlp-friends-production-reverb:8080`. Un seul worker, scheduler et Reverb ; ancien web Compose retiré.
- Parité sensible comparée sur serveur sans exporter les valeurs : clés applicatives, MySQL, Redis/sessions/cache, S3, mail et clés Reverb. Le nom privé Reverb change volontairement. MySQL, Redis et Garage sont conservés. Aucune migration, restauration ou écriture sur un compte membre de production pendant la transition.

### Mesures du 28 octobre 2026

Heures UTC (Paris : UTC+1).

| Essai                                      | Résultat mesuré                                                                                           | Limite                                                                                                      |
| ------------------------------------------ | --------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------- |
| Bêta, candidat sur port fermé              | Refusé, ancien web conservé ; 394 HTTP 200, zéro erreur sur 180 s                                         | Une route inexistante qui redirige peut tromper curl ; vérifier `/ready` sans redirection                   |
| Bêta, corrigé vers corrigé                 | 392 HTTP 200, zéro erreur, CSRF stable sur 180,22 s                                                       | Charge synthétique limitée                                                                                  |
| Bêta, session authentifiée pendant rolling | 312 réponses authentifiées 200 sur 150,03 s, zéro erreur, une écriture synthétique réussie une seule fois | Compte masqué ; admin temporaire autorisé puis révoqué, identifiants temporaires supprimés                  |
| Bêta, proxy 12:36–12:51                    | 800 HTTP 200, 10 redirections, 3 réponses Inertia 409, aucun 5xx                                          | Les 409 correspondent à la négociation de version des assets                                                |
| Production, séparation du support          | Déploiement 13:12:06–13:12:55 ; support interrompu environ 35 s                                           | Reconnexion des clients non vérifiée de bout en bout                                                        |
| Production, transfert initial des domaines | Sonde 126,22 s : 126 HTTP 200 et 123 erreurs ; première erreur 13:12:19, dernière 13:13:59                | Fenêtre d'incidents d'environ 100 s, pas une preuve de 100 s continuellement indisponibles                  |
| Production, répétition native              | Déploiement 13:14:23–13:15:03 ; nouveau sain 13:14:47, ancien retiré ensuite, environ 16 s de drainage    | Sonde 180,16 s : 377 HTTP 200, CSRF stable, **un timeout** à 13:17:00                                       |
| Production, proxy 13:14:20–13:17:30        | 617 HTTP 200, 5 redirections, 1 HTTP 403, 2 HTTP 204, 1 HTTP 499, **aucun 5xx**                           | 52 réponses au-delà de 4 s, jusqu'à 5,71 s, initiées vers 13:16:55 après le déploiement ; cause non établie |

Journaux Coolify : [rolling web](https://coolify.zdossantos.fr/project/v07iwpqm3wftr6zfnxissd5z/environment/qxua5l07049obkwetgfslwbb/application/dntfcsbhnoxkwelfon1bzn5i/deployment/xytrrdlchjqxfrgszzw9bxrs), [séparation support](https://coolify.zdossantos.fr/project/v07iwpqm3wftr6zfnxissd5z/environment/qxua5l07049obkwetgfslwbb/application/kma0dju8bamipey0sd1up9gs/deployment/ehktq6luzkdn46cfbeaueibb).

Après bascule : HTTP 200 sur les deux domaines web, S3 en lecture seule, résolution privée Reverb et véritables négociations WebSocket HTTP 101 privées et publiques avec TLS vérifié. Aucun message, événement ou mail envoyé à un membre pour ces tests. La bêta conserve ses MySQL/Redis/bucket et Mailpit propres ; sa stack classique rétablie répond HTTP 200.

Sauvegarde MySQL cohérente avant bascule : `/root/dlp-rolling-backups/production-before-rolling-20261028T130210Z.sql`, 673 866 845 octets, permissions 0600, dossier 0700. Lecture `--single-transaction --quick --skip-lock-tables`, sans verrouillage des tables. Une sauvegarde du même jour a été restaurée auparavant dans un conteneur isolé sans réseau ni port : 44,234 s, 51 tables, 45 migrations, contrôle rapide `users`/`migrations` réussi ; ressources temporaires retirées. Le nouveau dump n'a pas été restauré et la restauration S3 n'a pas été répétée.

Validation : readiness 11 tests / 39 assertions ; contrôles runtime, arrêt gracieux, traitements après réponse, assets anciens/nouveaux et réparation atomique réussis ; `composer ci:check` : 1 484 tests réussis, 8 ignorés, 35 675 assertions. CI finale `40689779904` verte, dont PHP, frontend, métier/navigateur/realtime et Docker. Les contrôles de code complètent les mesures serveur.

## Prochain déploiement web compatible

1. Relever le digest actif, vérifier sauvegardes, santé et marge RAM/disque. Aucune livraison simultanée. Confirmer compatibilité schéma, jobs, cache et support restant sur `v1.16.1`.
2. Construire le SHA explicitement choisi avec **Candidate image**, cible production. Vérifier digest, révision OCI, frontend, migrations et tests. Ne jamais employer implicitement `main` pour prolonger cette transition.
3. Dans la **ressource web native**, renseigner le digest immuable. Si le formulaire sépare image/tag, employer `ghcr.io/zdossantos/dlp-friends` et `sha256-<empreinte>`, puis vérifier la résolution `@sha256:<empreinte>` dans les logs.
4. Garder `/ready:80`, santé 5/5/1/20, arrêt 120 s, configuration et volume des assets. Aucun hook `migrate`, aucun redémarrage du support pour une livraison web seule.
5. Un seul Redeploy sous mesure trafic/session. Un candidat non prêt doit laisser l'ancien servi. En cas d'erreurs nouvelles, analyser avant tout nouveau déploiement.
6. Confirmer santé avant retrait, parcours, dépendances, erreurs/latences et absence de second scheduler. Conserver digest précédent et anciens assets.

Procédure **manuelle, réservée aux changements compatibles**. `scripts/operations/release.py` suppose quatre services et peut enclencher la maintenance : **ne pas l'utiliser sur la production séparée**. Une release avec migration ou nouveau worker/Reverb demande une procédure coordonnée distincte (expansion/contraction ou maintenance explicite). Le rolling web ne couvre pas ces opérations.

## Retour arrière

Pour une future livraison web compatible, remettre le précédent digest compatible `/ready` et drainage, puis redéployer nativement sous observation. Ne pas effacer les assets ni restaurer la base automatiquement. Le digest initial `dd44…` ne fournit pas ce contrat ; ne pas le sélectionner directement avec les mêmes paramètres comme s'il était équivalent.

Retour à la topologie antérieure : procédure de secours **non répétée en production**.

1. Prévoir une interruption ; arrêter le web natif et lui retirer les deux domaines, sans suppression DNS. Ne pas attribuer simultanément ces domaines aux deux ressources.
2. Sur `kma0dju8bamipey0sd1up9gs`, remettre `release/1.16`, commit `e418a4379fd758820d58d795f88a40517c5ff1f3`, `/compose.production.yaml`, et garder `APP_IMAGE` au digest initial `dd44c29d39410e687d1a6d8b1457ae058f1351a26e088d8e4e7aedc4bed659e8`.
3. Recharger Compose ; affecter les deux domaines web au service `web:80` et `realtime.dlp-friends.fr` au service `reverb:8080`. Retirer seulement les variantes générées en trop, sans suppression DNS.
4. Redéployer cette même ressource pour remplacer son support et démarrer le web. Ne pas lancer une seconde stack avec scheduler. Vérifier HTTP, sessions, S3, WebSocket et unicité des processus.
5. Garder le web natif arrêté, sa configuration, ses images et son volume pour diagnostic. Aucune migration inverse ni restauration nécessaire pour cette seule transition : schémas identiques.

## Risques et limites

Le recouvrement natif et le refus d'un candidat non sain sont vérifiés. L'objectif de zéro interruption HTTP pour les prochaines livraisons compatibles reste dépendant de la santé, de la charge et du proxy. Le timeout après le rolling interdit de présenter la fenêtre complète comme parfaite.

Non vérifiés en production : session membre authentifiée, écriture métier, upload long, reconnexion complète d'un canal privé, saturation et requêtes dépassant les 120 s d'arrêt. Session authentifiée et écriture unique sont vérifiées seulement en bêta. Le pic de latence reste à caractériser avant d'affirmer une capacité sous charge.

Serveur, proxy et base uniques restent des points de panne. Une requête trop longue peut être interrompue ; une écriture n'est pas automatiquement rejouable. Les migrations destructrices/payloads incompatibles demandent une stratégie séparée. Les assets grandissent sans nettoyage contrôlé. Mesure ponctuelle : environ 2,7 Go RAM disponibles, 122 Go disque libre, sans swap ; ce n'est pas un test de charge.

Aucun taux fiable « tokens → quota Codex cinq heures » n'est publié. Ce quota est partagé entre tâches et modèles : lire l'état réel et garder une marge ; ne pas convertir une estimation de tokens en engagement de pourcentage.

## Vérification locale reproductible

Après la garde MySQL isolée et le build runtime :

```sh
php tests/Support/verify-test-database.php
php artisan test tests/Feature/DeploymentReadinessTest.php
DLP_RUNTIME_TEST_IMAGE=dlp-friends:rolling php artisan test tests/Feature/Infrastructure/RollingRuntimeTest.php
bash .github/scripts/smoke-image.sh dlp-friends:rolling
python3 tests/Support/operations/runtime_integration.py dlp-friends:rolling
```

La suite runtime vérifie une réponse acceptée et son traitement PHP après réponse, ainsi que deux web servant les anciens et nouveaux chunks. Elle injecte un chunk tronqué et compare les octets servis à ceux de l’image : la publication se fait par renommage atomique sur le volume, et répare les fichiers incomplets. Elle ne pilote pas Traefik/Coolify, un navigateur utilisateur, ni les dépendances de production.

## Sources officielles

[Rolling Coolify](https://coolify.io/docs/applications/deployments/rolling-updates), [Docker Image et digests](https://coolify.io/docs/applications/deployments/docker-image), [santé Coolify](https://coolify.io/docs/applications/configuration/health-checks), [Laravel deployment](https://laravel.com/framework/docs/13.x/deployment), [Laravel queues](https://laravel.com/framework/docs/13.x/queues), [Reverb](https://laravel.com/framework/docs/13.x/reverb), [Inertia asset versioning](https://inertiajs.com/docs/v3/advanced/asset-versioning), [GHCR](https://docs.github.com/en/packages/working-with-a-github-packages-registry/working-with-the-container-registry), [GitHub workflow dispatch](https://docs.github.com/en/actions/how-tos/manage-workflow-runs/manually-run-a-workflow), [Docker stop](https://docs.docker.com/reference/cli/docker/container/stop/), [Nginx control](https://nginx.org/en/docs/control.html).
