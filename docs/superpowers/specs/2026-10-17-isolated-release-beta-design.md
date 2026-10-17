# Bêta isolée et validation des migrations avant livraison

## Statut et décision

Conception fonctionnelle et spécification écrite approuvées dans la
conversation le 17 octobre 2026.
Elle décrit une cible ; aucune ressource bêta ni protection nouvelle n'est
encore livrée. La production reste inchangée pendant cette préparation.

## Objectif

Tester la prochaine release avec une copie réaliste de la production, sans
qu'une écriture, une migration, un fichier supprimé ou une notification de
test affecte les membres en production. Répéter la mise à niveau depuis le
schéma effectivement exploité avant d'autoriser la livraison.

Une copie isolée est retenue plutôt qu'une base partagée. Une base partagée en
lecture seule empêcherait les tests métier ; une base partagée en écriture
ne protégerait ni les données ni les membres des essais. Aucune solution ne
garantit l'absence de tout bug après livraison.

## État vérifié

- Dépôt Laravel/Inertia/Vue ; production pilotée par Release Please et GHCR.
- Coolify accessible en version 4.3.23 ; application production
  `kma0dju8bamipey0sd1up9gs`, avec MySQL, Redis et Garage indépendants.
- Release PR vers `main` observée : #268, 1.15.1. Ce numéro n'est pas une
  configuration permanente ; la bêta doit suivre la Release PR courante.
- `.github/workflows/release-please.yml` publie une image de release et appelle
  `.github/scripts/deploy-release.sh`, qui épingle image et commit Compose.
- `compose.production.yaml` impose actuellement `APP_ENV=production`.
- L'entrypoint ne lance aucune migration. Les migrations sont manuelles,
  conformément à `AGENTS.md` et `docs/operations.md`.
- Le logo Vue, les en-têtes Blade et le manifeste PWA partagent les assets de
  marque. Les variantes claire, sombre et Halloween existent déjà.
- `/up` est une sonde de processus, pas une attestation de schéma à jour.
- Les abonnements Web Push possèdent des attributs chiffrés : copier la base
  ne rend pas leur réutilisation acceptable dans la bêta.

## Déploiement bêta

Créer un environnement Coolify `beta` dans le projet DLP Friends, avec une
application distincte sur le serveur existant. Aucun nouveau VPS payant n'est
nécessaire. Limiter ses ressources pour préserver la capacité de production.
Vérifier cette capacité avant démarrage ; si elle est insuffisante, ne pas
augmenter les ressources ou acheter un serveur sans nouvelle décision.

Utiliser `https://beta.dlp-friends.fr` et un hôte WebSocket distinct
`https://ws-beta.dlp-friends.fr`. Vérifier DNS et TLS avant ouverture. Les noms
DNS utilisent `beta` sans accent ; l'interface française affiche « Bêta ».

La source est uniquement la Release PR ouverte par Release Please vers `main`,
identifiée par dépôt, auteur attendu, branche et labels. Les PR de maintenance
`release/1.14` et les autres PR ne sont pas des candidats bêta. Ne jamais
exécuter du code de PR non fiable avec des secrets de déploiement.

À chaque création ou mise à jour du candidat, attendre les checks requis sur
son SHA exact, construire l'image AMD64 et tester son démarrage par digest.
Déployer ce digest et son commit Compose dans la seule application bêta.
Réutiliser les conventions de publication existantes ; aucune release ni tag
SemVer n'est créé pour la bêta. Les variables publiques Vite/Reverb sont celles
de bêta. Un échec de build ou de validation conserve le dernier candidat sain.

Sérialiser les déploiements bêta et revérifier le SHA courant avant toute
mutation Coolify : un ancien build ne doit pas écraser un candidat plus récent.
Sans Release PR ouverte, conserver la dernière bêta et indiquer son commit ;
ne pas déployer implicitement `main` ou une PR de fonctionnalité.

## Isolation des données et des effets de bord

- Base MySQL et volume distincts ; compte limité à la seule base bêta.
  Aucun identifiant de production n'est présent dans les processus bêta.
- Instance Redis dédiée : sessions, cache, verrous, présence et files isolés.
  Ne restaurer aucun contenu Redis de production.
- Bucket Garage privé distinct et clé limitée à ce bucket ; copier les objets
  nécessaires avant les tests. Aucun montage du stockage de production.
- Clé Laravel, cookies limités à l'hôte, identifiants Reverb et clés VAPID
  propres à la bêta. `APP_DEBUG=false` et protections des commandes destructives
  équivalentes à la production malgré `APP_ENV=beta`.
- Capturer les e-mails dans un Mailpit réservé à la bêta, dont l'interface est
  privée. Aucune clé Resend de production et aucun envoi réel depuis la bêta.
- Neutraliser les abonnements Push restaurés avant tout démarrage applicatif ;
  seuls les appareils inscrits explicitement depuis la bêta peuvent recevoir
  les notifications de test avec les clés VAPID bêta.
- Ne copier ni sessions, ni jetons de réinitialisation, ni clés/API externes,
  ni tâches de file de production. Les paramètres externes copiés sont examinés
  et neutralisés avant l'ouverture, notamment les URL et actions sortantes.
- Décision opérateur mise à jour : aucune restriction IP. En `APP_ENV=beta`,
  un contrôle Laravel réserve pages, médias et autorisations de canaux privés
  aux comptes actifs ayant le rôle `admin`. Connexion et double authentification
  restent accessibles ; les inscriptions sont refusées. Le retrait du rôle
  révoque les requêtes suivantes et la livraison sur les WebSocket déjà ouverts.
  Bucket privé et Mailpit interne uniquement, sans exposition publique directe.
  Vérifier ce contrôle avant l'import de données privées.
- Désactiver GA4, la vérification Search Console et l'indexation de toutes les
  pages bêta. `noindex` complète le contrôle d'accès ; il ne le remplace pas.

Le compte d'export de production est utilisé uniquement par l'opération de
copie contrôlée. Les sauvegardes et objets restent sur l'infrastructure
autorisée ; aucun dump, secret, message privé ou fichier personnel n'est
transmis à GitHub Actions, au dépôt ou aux journaux de validation.

## Copie réaliste et répétition des migrations

Le rafraîchissement est une opération explicite demandée par l'opérateur,
distincte du déploiement automatique du code. Il remplace les essais précédents
en bêta ; annoncer cet effet avant chaque rafraîchissement. Aucune donnée bêta
n'est réinjectée en production.

1. Fermer la bêta, arrêter worker, scheduler et Reverb, puis attendre l'arrêt
   des travaux. Vérifier les identités source et cible et refuser une cible
   correspondant à la production, avant toute écriture ou suppression.
2. Produire un export cohérent MySQL 8.4 selon la procédure existante ; vérifier
   les moteurs et les limites de cohérence des fichiers copiés pendant des
   écritures de production. Conserver un export protégé et un inventaire des
   objets pour la répétition, sans bloquer durablement la production.
3. Restaurer dans une base bêta vide et un stockage bêta dédié. Comparer schéma,
   tables et comptages avant transformation ; ne pas lancer `migrate:fresh` ou
   remplacer la répétition par des factories.
4. Neutraliser sessions, jetons, abonnements Push et intégrations sortantes.
   Traiter explicitement les éventuelles valeurs chiffrées incompatibles avec
   la clé Laravel bêta, sans copier la clé de production dans la bêta.
5. Relever les migrations déjà exécutées, puis appliquer explicitement les
   migrations du candidat dans la copie. Mesurer durée, erreurs, index et
   contraintes ; confirmer qu'une seconde exécution n'applique rien.
6. Tester connexion, lecture/modification d'un profil existant, découverte,
   message temps réel entre comptes de test, fichier, notifications et file.
   Les seules écritures se font dans la copie.
7. Vérifier qu'aucune migration du candidat ne reste en attente avant de
   réactiver les processus et de rouvrir la bêta aux testeurs autorisés.

Une nouvelle image bêta n'applique jamais de migration dans l'entrypoint.
Si elle requiert des migrations non validées, la bêta reste fermée avec une
indication opérateur de la marche à suivre. Les mises à jour sans migration
restent automatiques après les checks ; une évolution du schéma impose une
nouvelle répétition explicite.

## Protection de la mise en production

La publication GHCR reste automatique après merge volontaire de la Release PR.
La livraison applicative devient une opération contrôlée : l'image publiée
seule et l'acceptation de l'API Coolify ne valent pas autorisation de réouverture.
Ne pas exposer le nouveau code avant le contrôle du schéma attendu.

Avant chaque livraison, répéter la mise à niveau sur une copie de production
créée depuis moins de 24 heures. Enregistrer un rapport sans données privées :
date, commit/image candidate, empreinte des fichiers de migration, état initial
du schéma, migrations appliquées et résultats des parcours. Une modification
du candidat ou du schéma source invalide le rapport. Le contenu applicatif
livré doit correspondre au candidat validé ; vérifier cette correspondance
après le squash de la Release PR, sans assimiler leurs SHA distincts.

La procédure de livraison impose ensuite :

1. Rapport de répétition réussi et correspondant au contenu à livrer.
2. Maintenance HTTP persistante entre anciennes et nouvelles instances,
   arrêt effectif des workers, scheduler et WebSocket, puis sauvegarde finale
   vérifiée. Conserver image et commit précédents ainsi que les ressources.
3. Déploiement de l'image de release exacte en maintenant le service fermé.
4. Exécution opérateur explicite des migrations dans cette image ; toute
   erreur arrête la procédure et conserve la maintenance.
5. Commande de vérification du schéma : échec si une migration est en attente,
   si la base est indisponible ou si la table des migrations est absente.
6. Contrôles des parcours et des services, reprise contrôlée des processus,
   puis levée explicite de la maintenance seulement après réussite.

Le chemin GitHub/Coolify habituel ne doit plus contourner cette procédure.
Le contrôle de schéma doit être automatisé et bloquant ; la sauvegarde,
l'application des migrations et la réouverture restent des actions opérateur
explicites. `/up` reste utilisable pour tester une image sans base, avec un
contrôle séparé pour l'aptitude à servir les parcours réels.

Pour les migrations qui transforment des données existantes, privilégier des
étapes additives compatibles avec la version précédente, un remplissage
rejouable et la suppression différée des anciens champs. Tester les cas
existants et les contraintes sur la copie. Un contrôle de migrations en attente
ne détecte pas à lui seul une migration oubliée dans le code : les tests métier
de mise à niveau et la revue des migrations restent nécessaires.

Un rollback d'image ne restaure pas les données. Ne jamais lancer
automatiquement `migrate:rollback` ni restaurer un dump par-dessus des écritures
récentes. En cas d'échec, garder le service fermé et choisir une correction en
avant ou une restauration contrôlée selon la procédure d'exploitation.

## Identification visuelle

Quand `APP_ENV=beta`, afficher un petit badge « Bêta » en français et « Beta »
en anglais, en bas à droite du logo, sur fond blanc avec contour et texte
sombre. Préserver les variantes claire, sombre et saisonnière du logo.

Décliner le même repère sur favicon SVG/ICO, Apple Touch Icon et icônes PWA
normales et maskable en conservant leurs zones de sécurité. Les textes visibles
et noms accessibles viennent des catalogues existants. L'icône raster peut
porter la marque commune « BETA », dessinée à partir des SVG du projet.

La sélection dépend de la configuration serveur à l'exécution : utiliser la
même image en `production` ne doit afficher aucun badge. Séparer les URL des
assets bêta pour éviter les collisions de cache. La PWA bêta doit pouvoir être
installée séparément de la PWA de production et porter un nom identifiable.

## Validation et documentation

Tests Pest pour isolation déclarée dans le Compose, sélection des assets selon
l'environnement, contrôles de migrations et refus des configurations à risque.
Tests des scripts avec une API Coolify locale simulée : échecs, SHA dépassé,
candidat non autorisé et absence de déploiement production implicite.
Tests navigateur pour badge, thèmes et PWA ; aucune donnée de production dans
les tests automatisés ou leurs captures.

Vérifier séparément dans Coolify : accès refusé sans autorisation, DNS/TLS,
image et commit attendus, état des services, migrations, stockage et effets
sortants isolés. Documenter les vérifications avec métadonnées uniquement.

Mettre à jour `docs/operations.md`, `docs/quality-ci-cd.md`, les règles de marque
et l'état d'implémentation seulement au moment de la livraison effective.
Conserver les changements locaux préexistants hors des commits de ce travail.

## Références

- `AGENTS.md`, `avatar.md`, `docs/PRD.md`, `docs/engineering-principles.md`.
- `docs/operations.md`, `docs/mysql-externalization.md`,
  `docs/redis-externalization.md`, `docs/garage-externalization.md`.
- `docs/quality-ci-cd.md`, `docs/design-system.md`,
  `.github/workflows/release-please.yml`, `compose.production.yaml`.
