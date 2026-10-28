# Déploiements web avec recouvrement

État du chantier au 28 octobre 2026 : contrôles locaux et CI réussis, répétition Coolify en bêta en cours ; **aucune bascule de production effectuée**. Une erreur HTTP a été observée pendant un remplacement bêta : zéro interruption n'est pas encore validé.

## Base de la transition

Partir du tag publié `v1.16.1`, commit `e418a4379fd758820d58d795f88a40517c5ff1f3`. L’image en production porte exactement cette révision : `ghcr.io/zdossantos/dlp-friends@sha256:dd44c29d39410e687d1a6d8b1457ae058f1351a26e088d8e4e7aedc4bed659e8`.

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

Les variables `VITE_REVERB_*` sont publiques. Ne pas y ajouter de secret. Aucun jeton Coolify de production n’est nécessaire pour publier une image. Le pipeline Release Please et la procédure avec maintenance restent disponibles pour les autres livraisons.

## Répétition serveur obligatoire

La ressource beta configurée `hhz9n4q2qzxppne4q0rf8phx` est **Exited**, sans conteneur applicatif observé lors de l’inventaire. Le Mailpit beta tourne. Ne pas confondre les autres applications du serveur avec cette beta ; ses dépendances, données et transports doivent être vérifiés avant de la démarrer.

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

`compose.production-support.yaml` et `compose.beta-support.yaml` contiennent seulement worker, scheduler et Reverb. Ils sont autonomes car le parseur Coolify ne résout pas l'héritage Compose avant son traitement. Les tests d'infrastructure comparent leur configuration à celle des stacks complètes. Ils ajoutent un arrêt gracieux de 120 secondes et un alias Reverb propre à chaque environnement sur le réseau externe `coolify`.

Conserver le digest actuel des processus de support lors de la séparation. Vérifier la résolution de `dlp-friends-production-reverb` depuis le nouveau web avant de lui attribuer le domaine. Ne jamais démarrer une seconde stack de support tant que l'ancien scheduler tourne.

La procédure `scripts/operations/release.py` et l'automatisation bêta attendent encore une stack complète à quatre services. **Ne pas les utiliser sur une topologie séparée** : `prepare` peut activer la maintenance avant de rencontrer une incompatibilité. Leur adaptation ou le maintien de la bêta classique après la répétition est nécessaire avant clôture. Ces fichiers seuls n'activent aucun déploiement.

Pour une livraison web compatible après séparation : construire et vérifier un digest précis, contrôler migrations et compatibilité avec les processus de support, relever l'ancien digest et la santé, lancer un seul redéploiement natif, mesurer les réponses et les sessions pendant le recouvrement, puis vérifier les parcours et les journaux. En cas d'échec de santé, conserver l'ancienne instance ; en cas de régression après bascule, réaffecter le digest précédent et redéployer, sans migration inverse automatique. Une livraison qui nécessite de nouveaux jobs, schémas ou processus ne relève pas de cette procédure web seule.

### Mesures acquises pendant la répétition

- Première image de transition : commit `6f8f2b52a61f7bd0b6e416d72b93b1b5eb6094a9`, issu directement de la release `v1.16.1`. Une correction du retrait au proxy est en cours de validation ; ces premiers digests ne sont pas approuvés pour la production.
- Variante bêta : `sha256:75bb6b89016e239273c81b611752e99fc70d1303811cc6a077151c0439af3e4c` ; variante production : `sha256:fd97886494626a25f36ea533ef638e79879f018d52401789e4c855f0c4be8e3d`. Les deux builds GitHub ont réussi leurs contrôles runtime avant publication.
- Restauration du dump MySQL du 28 octobre dans un conteneur isolé sans réseau ni port : import en 44,234 secondes, 51 tables, 45 migrations, contrôle rapide de `users` et `migrations` réussi ; conteneur supprimé ensuite. Cela ne prouve pas la restauration complète des objets S3 ni les parcours métier.
- Ressource native bêta `c4xzgs0ydhcx2lzpr1sz8ef1`, arrêt effectif de 120 secondes, volume partagé limité aux assets, domaine HTTPS bêta. Un remplacement sain prend environ 27 secondes, avec nouvelle instance saine avant retrait de l'ancienne selon Coolify.
- Première mesure HTTP : 326 réponses 200 et une erreur HTTP dont le code n'avait pas été enregistré sur 150 secondes ; aucun changement de jeton CSRF. Cette mesure ne prouve ni une session authentifiée conservée ni zéro erreur.
- Une URL inexistante répondant par une redirection est acceptée par la sonde HTTP générée (`curl -f`). Employer exactement `/ready` et vérifier séparément son statut 200 sans redirection. Le test de refus utilise un port fermé pour éviter ce faux positif.
- Candidat sur port 81 fermé : refusé par Coolify, ancienne instance conservée ; 394 réponses 200, aucune erreur ni changement CSRF sur 180 secondes. Le journal du proxy attribue en revanche les incidents des remplacements sains au retrait de l'ancien backend : 502 à 12:09:46 UTC, puis requête abandonnée après cinq secondes à 12:12:18 UTC. La correction doit être répétée sur serveur avant bascule.

Précharger l’image, vérifier sa parité et ses accès sans trafic public. Peupler le volume avec les assets de l’image actuellement servie ; vérifier que l’ancien HTML continue à trouver ses chunks après changement de route. Conserver l’ancien digest et une procédure inverse déjà répétée.

Le transfert du domaine entre la ressource Compose et la nouvelle ressource doit être répété sur cette version de Coolify/Traefik : ne pas attribuer le même domaine aux deux ressources en supposant une sélection automatique. Ne pas utiliser le DNS comme rollback rapide. Mesurer le retrait et l’installation des routes et le comportement pendant cette phase.

Après transfert, l’ancien web doit rester identifiable pour le retour arrière sans pouvoir reprendre accidentellement le domaine lors d’un redéploiement Compose. La séparation de la définition de support et l’adaptation de la procédure d’exploitation sont une condition de clôture du chantier ; ne pas présenter la seule création du nouveau web comme une livraison complète.

Effectuer ensuite un redéploiement contrôlé du même digest sain sous trafic pour prouver le rolling natif. Conserver le résultat réel et la durée observée avant d’annoncer zéro interruption.

## Risques, durée et limites

La première séparation est une opération d’infrastructure : réserver **au moins 30 minutes**, avec une estimation initiale de **5 à 15 minutes de maintenance** si image/configuration/routage sont prêts. Si sauvegarde et restauration doivent se dérouler pendant la fermeture, prévoir **10 à 30 minutes ou davantage**, selon mesure. Ces durées sont des estimations d’ingénierie, pas des mesures ni un engagement Coolify. Les remplacer après répétition.

Les futures releases compatibles visent **0 seconde d’interruption HTTP**, sous réserve de la preuve serveur. Les migrations destructrices, changements de payload de jobs ou formats de cache incompatibles suivent une livraison avec maintenance ou un découpage expansion/contraction. Aucun `migrate` automatique au démarrage. Un rollback d’image ne restaure pas les données.

Risques principaux : faux positif de santé, requête excédant le délai d’arrêt, session/clé différente, chunk absent, double scheduler, incompatibilité des versions et manque de ressources. Un serveur, un proxy et une base uniques restent des points de panne. La sonde ne prouve ni S3, ni Resend, ni chaque parcours ; ces vérifications restent séparées. Tester aussi les dépendances lentes : le délai du curl ne borne pas à lui seul la durée du travail PHP.

Mesure ponctuelle du serveur : environ 2,9 Go de RAM disponibles et 122 Go disque libres ; aucune swap. Elle ne constitue pas un essai de charge.

Pour le quota Codex, aucun taux fiable « tokens → pourcentage des cinq heures » n’est publié. Mesurer avant/après les phases, sans autres tâches simultanées, et garder une marge avant toute bascule.

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
