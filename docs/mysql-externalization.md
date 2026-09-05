# MySQL indépendant dans Coolify

Cette procédure concerne l’issue 133 et uniquement la production. Le Compose
local et MySQL de test restent autonomes. La configuration versionnée prépare
la séparation ; elle ne prouve pas que la migration de production a été faite.

## État observé dans Coolify le 4 septembre 2026

- Projet `v07iwpqm3wftr6zfnxissd5z`, environnement production
  `qxua5l07049obkwetgfslwbb`, application `kma0dju8bamipey0sd1up9gs`.
- Serveur `y5j0mvuo7rl64wa6z95m178j` : `docker inspect` confirme que la cible
  rejoint la destination `coolify`. L'application Docker Compose doit utiliser
  l'option Coolify **Connect to Predefined Network** pour la rejoindre.
- Ressource créée « DLP Friends - MySQL », UUID `usxpif17bqpgv4qmykcisz8w`,
  image `mysql:8.4.10`, état `Running`, base `dlp_friends`, utilisateur initial
  non-root `mysql`. `SHOW GRANTS` confirme uniquement `USAGE` global et les
  privilèges sur `dlp_friends`, sans `GRANT OPTION`.
- Volume cible `mysql-data-usxpif17bqpgv4qmykcisz8w`, monté sur
  `/var/lib/mysql`, sans port public. Configuration initiale : `utf8mb4`,
  `utf8mb4_unicode_ci`, événements SQL désactivés pendant la préparation.
- Sauvegarde quotidienne à 02:00 UTC créée, UUID
  `ahw5wcmsjwkqgtx71jobz2zy`. Aucune destination S3 n’est configurée dans
  Coolify. L’opérateur a explicitement retenu une sauvegarde locale pour cette
  étape et reporté S3. La rétention locale est configurée à 30 jours.
  Le premier job a réussi sur la cible vide. Un second job a réussi en une
  seconde après import des 26 tables de répétition sur la cible ; il devra être
  revérifié après la copie finale des données.
- Deux ressources Redis distinctes sont visibles : « DLP Friends - Redis »
  (`tvgg71qeadvxtzxez2o2agy5`) et « DLP Friends - Redis deploiement »
  (`vthrho8fsbu1hevbvmopq7ol`). Laravel utilise actuellement `redis`, résolu en
  `10.0.3.3`, adresse du conteneur Redis intégré au Compose : leur présence
  n’indique donc pas que l’issue 134 est déjà livrée.

La source est en MySQL 8.4.10, avec 26 tables dans `dlp_friends`, charset
`utf8mb4` et collation `utf8mb4_unicode_ci`. Son volume exact est
`kma0dju8bamipey0sd1up9gs_mysql-data`.

La répétition a produit un export protégé copié hors du volume source dans
`/data/coolify/backups/manual/issue-133/rehearsal.sql` sur le serveur. Il a été
restauré dans `dlp-issue133-restore`, MySQL 8.4.10 avec réseau `none`, volume
`dlp-issue133-restore-data` et secret distinct. Les 26 tables sont restaurées.
La comparaison complète des exports est identique après retrait de la date
du dump et normalisation du seul `CHARACTER SET utf8mb4` rendu explicite devant
les collations de colonnes par MySQL. Le conteneur de répétition est arrêté.
Cette répétition ne remplace pas l’export final après arrêt des écritures.

Aucune bascule applicative ni copie finale sous maintenance n’a été effectuée.
La cible contient uniquement la copie de répétition et le volume source est
conservé. Un conteneur temporaire sur `coolify` résout bien
`usxpif17bqpgv4qmykcisz8w` : c’est la valeur prévue pour `DB_HOST`. Les quatre
processus applicatifs devront encore confirmer leur connexion après déploiement.
`DB_HOST` est préparé dans les variables Coolify ; les identifiants applicatifs
de la cible restent à renseigner avant la bascule.

La production utilise encore l’image locale
`dlp-friends-app:4b11e638660930836f73c5c84d3012b1dba82cc8`. Le `main` issu de
l’issue 132 exige désormais `APP_IMAGE` publié sur GHCR. La dernière release
observée, `v1.2.2`, ne possède pas encore d’artefact `container-image.json`.
Coordonner la bascule avec la première publication de cette image par le flux
Release Please ; ne pas lancer un déploiement sans référence d’image vérifiée.

## Préparer le service et le réseau

Avant de livrer le nouveau Compose, suspendre les livraisons de releases et
attendre la fin des déploiements en cours. Relever dans Coolify les ressources
applicatives, MySQL et Redis réellement utilisées. Ne pas déduire la séparation
Redis du dépôt : elle relève de l’issue 134.

Créer une ressource MySQL indépendante de l’application, sur le même serveur,
avec l’image MySQL 8.4 (version actuelle du dépôt : `mysql:8.4.10`). Lui attribuer
son propre volume persistant monté sur `/var/lib/mysql`, sans réutiliser le volume
source. Définir `character-set-server=utf8mb4` et
`collation-server=utf8mb4_unicode_ci`, après comparaison avec la source réelle.
Conserver séparément les identifiants administrateur et sauvegarde dans Coolify.
Créer une base applicative et un utilisateur limité à cette base, sans privilège
global ni `GRANT OPTION`. L’application n’utilise jamais le compte root.

Sélectionner la même destination Coolify pour les ressources, puis activer
**Connect to Predefined Network** dans les paramètres avancés de l'application
Docker Compose. Ne publier aucun port MySQL sur l'hôte ou Internet et ne pas
déclarer de réseau personnalisé dans le Compose.

Dans l’application, renseigner :

| Variable | Valeur attendue |
| --- | --- |
| `DB_HOST` | Hôte ou alias unique du service MySQL résolu sur ce réseau |
| `DB_PORT` | Port interne du serveur, `3306` par défaut |
| `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | Base et compte applicatif dédiés |
| `DB_CHARSET`, `DB_COLLATION` | Valeurs compatibles avec la source ; défauts `utf8mb4`, `utf8mb4_unicode_ci` |

Le nom générique `mysql` d’une ancienne stack ne constitue pas une preuve de
résolution entre stacks. Consigner dans le compte rendu de bascule les UUID
Coolify, le nom réel du réseau, l’hôte vérifié, la version MySQL, le volume source
et le volume cible. Ne jamais y copier de secret ni de donnée personnelle.

## Répéter sauvegarde et restauration avant la bascule

1. Relever la version, le mode SQL, le fuseau horaire, le charset et la collation
   de la source, ainsi que les moteurs, collations de tables et colonnes. Vérifier
   que les tables sont InnoDB ; sinon prévoir un verrouillage adapté à une
   sauvegarde cohérente. Relever aussi routines, triggers et événements.
2. Configurer une sauvegarde quotidienne avec 30 jours de rétention et alerte
   d’échec. Pour cette étape, l’opérateur a accepté un stockage local séparé du
   volume source ; vérifier le résultat d’un vrai job et son archive. Le stockage
   chiffré hors serveur est reporté et reste une amélioration opérationnelle à
   traiter ensuite.
3. Restaurer cette sauvegarde dans une troisième base MySQL 8.4 isolée, avec
   réseau, secrets et volume distincts. Aucun worker, scheduler, e-mail réel ou
   diffusion de production ne doit partir de cette répétition.
4. Comparer schéma, contraintes, index, charset, collations, nombre exact de
   lignes par table (`COUNT(*)`, pas les estimations `information_schema`) et
   empreintes de données par clé primaire calculées sans publier les données.
   Vérifier les relations et le parcours applicatif sur cette copie.
5. Consigner date, résultat, durée, emplacement de sauvegarde et contrôles dans
   le compte rendu opérateur. Ne pas basculer tant que la restauration échoue.

Pour un export logique, utiliser un client MySQL 8.4 et un fichier d’options
protégé (`0600`) hors dépôt, contenant les identifiants du compte de sauvegarde.
Ne pas passer de mot de passe en argument ni l’afficher dans les logs. Exemple
à adapter après inventaire des objets et droits :

```sh
umask 077
mysqldump --defaults-extra-file=/secure/source.cnf \
  --single-transaction --quick --routines --triggers --events \
  --hex-blob --no-tablespaces --set-gtid-purged=OFF \
  --default-character-set=utf8mb4 NOM_BASE > /secure/final.sql
mysql --defaults-extra-file=/secure/target.cnf NOM_BASE < /secure/final.sql
```

Le dump contient des données privées : le chiffrer avant transfert hors serveur,
le conserver selon la politique de sauvegarde et protéger aussi la copie isolée.
Vérifier les codes de sortie et le checksum de l’archive après transfert. Ne pas
exporter/restaurer la base système `mysql` ni les comptes administrateur source.
Préserver les définitions de tables et colonnes ; ne pas convertir les collations
pendant cette opération. Examiner les `DEFINER` avant restauration et empêcher
l’exécution d’événements SQL planifiés sur une copie de validation.

## Interrompre les écritures et basculer

1. Conserver le couple image/commit Compose précédent et relever le nom Docker
   exact du volume source. Interdire `down -v`, les nettoyages de volumes et la
   suppression de l’ancienne ressource jusqu’à validation explicite.
2. Mettre toutes les instances HTTP en maintenance (`php artisan down`), puis
   arrêter proprement scheduler, worker et Reverb. Attendre la fin des jobs et
   transactions en cours ; bloquer aussi les écritures opérateur et événements
   SQL éventuels. La maintenance HTTP seule ne suffit pas.
3. Effectuer le dump final après cet arrêt des écritures. Restaurer dans la
   nouvelle base vide et refaire les comparaisons de la répétition sur ce dump
   final. En cas d’échec, conserver la maintenance et suivre le retour arrière.
4. Enregistrer les variables applicatives ci-dessus dans Coolify, retirer
   `MYSQL_ROOT_PASSWORD` de l’application et déployer le nouveau Compose avec
   l’image compatible. Ne plus envoyer aucune écriture vers la source.
5. Dans chacun des conteneurs `web`, `worker`, `scheduler` et `reverb`, vérifier
   la résolution DNS puis une connexion via la configuration Laravel effective
   (y compris son cache), par exemple :

   ```sh
   php artisan tinker --execute='dump(DB::selectOne("SELECT DATABASE() AS db, @@hostname AS server, @@port AS port, @@character_set_connection AS charset, @@collation_connection AS collation"));'
   ```

   Confirmer la même cible dans les quatre conteneurs. `/up` et les healthchecks
   de processus ne prouvent pas l’accès MySQL. En cas de cache obsolète,
   redémarrer les conteneurs avec la nouvelle configuration.
6. Examiner `php artisan migrate:status`. Cette séparation ne demande aucune
   migration de schéma. Si la release en contient, exécuter explicitement
   `php artisan migrate --force` après validation de compatibilité.
7. Réactiver les processus et lever la maintenance (`php artisan up`) après
   validation. Avec des comptes de contrôle autorisés, vérifier connexion,
   profil, découverte, match, message reçu en temps réel, file de jobs et
   scheduler. Consigner les résultats sans contenu de message ni secrets.
8. Vérifier qu’un redéploiement puis un arrêt/redémarrage de l’application en
   fenêtre de maintenance laisse la ressource MySQL saine et les données
   inchangées. Contrôler ensuite un job de sauvegarde de la nouvelle ressource.
   Conserver la source arrêtée et son volume jusqu’à validation opérateur.

## Retour arrière

Avant réouverture des écritures, arrêter les quatre processus applicatifs,
restaurer le Compose précédent avec son image compatible et ses variables,
puis remettre en service l’ancienne base sur son volume exact. Vérifier les
connexions et le parcours avant de lever la maintenance. Ne pas restaurer un dump
sur le volume source conservé.

Après réouverture, la source est obsolète : ne jamais simplement y reconnecter
l’application. Remettre en maintenance, arrêter tous les producteurs et exporter
les nouvelles données de la cible. Les restaurer dans une base de retour isolée,
valider cohérence et compatibilité, puis basculer ; à défaut rester en maintenance
et décider explicitement de la récupération. Aucun `migrate:rollback` automatique.

Un retour à une ancienne release rétablit aussi son ancien Compose : s’il inclut
MySQL ou ignore `DB_HOST`, il reconnectera l’ancienne base. Préparer un Compose
compatible avec la base externe avant un retour applicatif après cette bascule.
