# Exploitation et fiabilité

Ce document porte les exigences et procédures opérateur. Les objectifs produit
et l’état des capacités applicatives sont définis dans le
[`PRD.md`](PRD.md).

## Prérequis de publication légale

- Définir `LEGAL_CONTACT_EMAIL` dans les secrets Coolify avant la production.
- Faire relire professionnellement les CGU et la politique de confidentialité.
- Confirmer la sauvegarde quotidienne chiffrée MySQL et Garage, hors serveur,
  avec une rétention de 30 jours.
- Le VPS est hébergé chez IONOS. Mailpit reste local uniquement ; Resend est le
  transport d’e-mails transactionnels de production.

## MySQL indépendant

Avant de déployer le Compose applicatif, préparer la ressource MySQL et son volume
selon [la procédure de séparation MySQL](mysql-externalization.md).
Pour une installation existante, terminer la répétition de restauration et suivre
la fenêtre de bascule avant toute livraison de ce Compose.

## Redis indépendant

Avant de déployer le Compose applicatif, préparer la ressource Redis selon
[la procédure de séparation Redis](redis-externalization.md). Activer **Connect
to Predefined Network** dans les paramètres avancés de l'application, puis
configurer `REDIS_HOST`, `REDIS_PORT` et `REDIS_PASSWORD` dans Coolify. Le mot de
passe reste un secret d'exécution et aucun port Redis ne doit être publié sur
Internet.

## Garage indépendant

Avant de retirer MinIO du Compose applicatif, préparer Garage et migrer les
objets selon [la procédure Garage](garage-externalization.md). Créer la ressource
avec le service Garage natif de Coolify, puis utiliser son stockage persistant,
un bucket privé et une clé applicative limitée. Après validation de la migration,
supprimer définitivement le conteneur et le volume MinIO de production.

## Premier déploiement sur Coolify

1. Créer une application Docker Compose depuis le dépôt GitHub, suivre la
   branche `main` et sélectionner `compose.production.yaml`. Désactiver **Auto
   Deploy** et supprimer toute commande personnalisée de build. Configurer
   l’accès GHCR privé et les variables GitHub comme indiqué dans
   [`quality-ci-cd.md`](quality-ci-cd.md#configuration-et-droits).
2. Associer le domaine HTTPS public au service `web` sur son port interne `80`,
   puis le domaine WebSocket au service `reverb` sur son port interne `8080`.
3. Renseigner les variables obligatoires détectées par Coolify :
   `APP_IMAGE`, `APP_KEY`, `APP_URL`, `LEGAL_CONTACT_EMAIL`, `DB_DATABASE`, `DB_USERNAME`,
   `DB_PASSWORD`, `DB_HOST`, `REDIS_HOST`, `REDIS_PASSWORD`, `AWS_ENDPOINT`,
   `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_BUCKET`,
   `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`,
   `RESEND_API_KEY`, `MAIL_FROM_ADDRESS`, `GOOGLE_CLIENT_ID`,
   `GOOGLE_CLIENT_SECRET` et `GOOGLE_REDIRECT_URI`. En production,
   `GOOGLE_REDIRECT_URI` doit valoir
   `https://dlp-friends.fr/auth/google/callback`. Ajouter
   `GOOGLE_ANALYTICS_ID` pour activer GA4 et, si la validation HTML Search
   Console est utilisée, `GOOGLE_SITE_VERIFICATION`. `APP_IMAGE` est la référence GHCR
   par digest issue du fichier `container-image.json` joint à la release ; le
   workflow la renseignera ensuite à chaque livraison.
4. Définir les quatre `VITE_REVERB_*` dans les variables **GitHub**, avec le
   domaine public Reverb sans protocole, le port `443`, le schéma `https` et
   une clé publique identique à `REVERB_APP_KEY` dans Coolify.
5. Fusionner volontairement la Release PR une fois les contrôles réussis. Le
   workflow publie l’image, teste son démarrage et demande son déploiement.
   Attendre que `web`, `worker`, `scheduler`, `reverb`,
   et les ressources MySQL, Redis et Garage indépendantes soient sains.
6. Créer le bucket privé nommé par `AWS_BUCKET` dans Garage avant d’activer un
   parcours qui écrit des objets. Limiter la clé applicative à ce bucket.
7. Exécuter explicitement la migration dans le conteneur `web` :

   ```sh
   php artisan migrate --force
   ```

8. Redémarrer proprement les workers, puis contrôler la santé publique :

   ```sh
   php artisan queue:restart
   curl --fail --silent https://<domaine-application>/up
   ```

Les migrations ne font partie ni de l’entrypoint ni de la commande de démarrage
d’un service. Une modification de `VITE_REVERB_*` exige une nouvelle
construction de l’image, car ces valeurs sont intégrées aux assets frontend.

## Google Analytics 4 et Search Console

1. Dans Google Analytics, créer une propriété GA4 et un flux Web pour le domaine
   HTTPS de production. Copier l’identifiant de mesure `G-…` dans
   `GOOGLE_ANALYTICS_ID` dans Coolify, puis redéployer. La variable est lue à
   l’exécution et ne doit pas être ajoutée aux variables `VITE_*`.
2. Avant acceptation, vérifier dans l’onglet Réseau du navigateur qu’aucune
   requête vers `googletagmanager.com` ou `google-analytics.com` n’est émise.
   Le refus doit conserver ce comportement. L’acceptation charge le tag avec
   `analytics_storage=granted`, tandis que les trois consentements publicitaires
   restent `denied`. Le choix est conservé six mois.
3. Dans **Flux de données > Web > Mesure améliorée > Pages vues > Paramètres
   avancés**, désactiver les changements de page fondés sur les événements
   d’historique. L’application désactive aussi la page vue automatique du tag :
   les pages publiques et les navigations Inertia envoient chacune leur propre
   événement explicite, afin d’éviter les doublons et de garantir des titres
   génériques sans nom de membre.
4. Activer le mode debug du tag, puis ouvrir une page Inertia et effectuer une
   navigation cliente. Dans DebugView, vérifier exactement une page vue
   initiale puis une page vue supplémentaire. Contrôler `page_location` et
   `page_referrer` : leurs chemins sont normalisés et ne contiennent ni
   identifiant réel, ni paramètre de requête, ni fragment.
5. Ouvrir ensuite `/fr` et `/en` dans deux chargements complets et vérifier une
   seule page vue pour chaque URL. Le préfixe de langue reste dans le chemin et
   permet de comparer les deux versions au sein du même flux Web.
6. Dans **Flux de données > Web > Masquage des données**, activer le masquage
   des adresses e-mail et déclarer les paramètres d’URL susceptibles de
   contenir des données personnelles. Cette défense complète la normalisation
   applicative sans la remplacer.
7. Dans **Administration > Définitions personnalisées**, créer deux dimensions
   personnalisées de portée Événement : `Mode d’application` associée au
   paramètre `app_mode`, et `Type de page` associée à `page_type`. Les valeurs
   de `app_mode` sont `pwa` et `browser`. Après collecte, créer une exploration
   libre avec `Mode d’application` en lignes et `Vues` en valeurs pour comparer
   la PWA au site. Ces dimensions ne sont pas rétroactives : elles n’enrichissent
   que les événements reçus après leur création.
8. Dans Search Console, créer de préférence une propriété de type Domaine et
   publier l’enregistrement TXT fourni dans le DNS. La variable
   `GOOGLE_SITE_VERIFICATION` reste disponible pour une propriété de type
   Préfixe d’URL validée par balise HTML.
   Une propriété Domaine couvre `/fr` et `/en`; des propriétés de préfixe
   séparées ne sont utiles que si des rapports autonomes par langue sont
   nécessaires.
9. Après validation, soumettre `https://<domaine>/sitemap.xml`. Contrôler que
   `/fr`, `/en`, les pages de matching, les quatre guides et les documents
   légaux sont détectés, tandis que les routes d’authentification et membres
   restent exclues.
10. Avec l’inspection d’URL, lancer un test en direct de `/` et vérifier que
   Google peut suivre la redirection vers la landing localisée. Tester aussi
   une URL canonique française et anglaise et contrôler leurs annotations
   `hreflang` réciproques.
11. Demander l’indexation des pages principales avec l’inspection d’URL. Une
   soumission ne garantit ni l’indexation immédiate ni une position dans les
   résultats ; surveiller les rapports Pages et Sitemaps après exploration.

L’absence de `GOOGLE_ANALYTICS_ID` désactive entièrement le script GA4.
L’absence de `GOOGLE_SITE_VERIFICATION` retire uniquement la balise de
validation et n’affecte ni le sitemap ni l’indexation.

### Suivi du référencement public

Après chaque déploiement qui touche le contenu public, contrôler ces six pages
éditoriales en priorité : `/fr`, `/en`,
`/fr/rencontres-amicales-disneyland-paris`,
`/en/disneyland-paris-friendships`, `/fr/aller-seul-disneyland-paris` et
`/en/visiting-disneyland-paris-solo`.

1. Dans l’inspection d’URL de Search Console, lancer un test en direct pour
   chaque URL. Vérifier le code `200`, la canonical choisie, la langue et
   l’absence de directive `noindex`.
2. Valider le HTML avec le test des résultats enrichis de Google ou le
   validateur Schema.org. Les blocs `WebApplication`, `WebPage` et
   `BreadcrumbList` doivent être du JSON valide ; un résultat enrichi n’est
   toutefois ni requis ni garanti pour ces types.
3. Soumettre à nouveau `/sitemap.xml`, puis demander l’indexation des pages
   principales qui ont matériellement changé. Éviter les demandes répétées :
   elles n’accélèrent pas nécessairement le traitement.
4. Distinguer les problèmes : une URL non explorée relève de la découverte ou
   de l’accès, une URL explorée mais non indexée relève surtout de la qualité,
   de la duplication ou de la canonical, et une URL indexée mais peu visible
   relève du classement et de l’adéquation aux requêtes.
5. Chaque semaine durant le premier mois, relever par page et par requête les
   impressions, clics, CTR et position moyenne, puis ventiler par pays et
   appareil. Passer ensuite à une revue mensuelle, en comparant des périodes de
   durée équivalente et en annotant les mises en production.
6. Utiliser les requêtes réellement observées pour améliorer une page utile,
   ses titres et son maillage. Ne pas créer de variantes minces pour chaque
   mot-clé et ne pas interpréter une variation courte de position comme un
   résultat durable.

## Déploiements suivants

Un merge ordinaire dans `main` ne déploie rien. Après le merge volontaire de la
Release PR, le workflow publie l’image du commit de release, fixe son digest
pour les quatre services et fixe le commit utilisé pour le Compose. Après la
réussite du déploiement dans Coolify :

1. lire les notes de migration de la version ;
2. activer une fenêtre de maintenance si la migration l’exige ;
3. exécuter `php artisan migrate --force` dans `web` ;
4. exécuter `php artisan queue:restart` ;
5. vérifier `/up`, la connexion WebSocket, les files et les journaux des six
   services applicatifs et du service MySQL indépendant.

## Bascule d’une installation existante

Avant le merge de l’issue 132, désactiver Auto Deploy dans Coolify pour éviter
qu’un Compose exigeant `APP_IMAGE` ne soit lancé avant la première publication.
Préparer les variables GitHub, vérifier les droits write/deploy de
`COOLIFY_TOKEN` et authentifier le VPS au registre GHCR privé. Le dépôt Git,
les domaines et les volumes existants sont conservés.

Après merge de la Release PR, le workflow fournit `APP_IMAGE` et
`git_commit_sha` avant son premier appel de déploiement. Vérifier dans les logs
Coolify le téléchargement de l’image et l’absence de compilation applicative.
Le premier retour à une version antérieure à cette bascule nécessite le
Compose et l’image locale de l’ancien déploiement ; il ne bénéficie pas encore
d’un artefact GHCR. Conserver cette image locale jusqu’à validation de la
première release GHCR et ne pas lancer de nettoyage Docker entre-temps.

## Retour à une release GHCR précédente

1. Suspendre toute nouvelle livraison et vérifier qu’aucun déploiement Coolify
   n’est en attente ou en cours. Auto Deploy doit rester désactivé.
2. Vérifier la compatibilité de la version précédente avec le schéma actuel,
   les données, les variables serveur et les paramètres publics Reverb intégrés
   à cette ancienne image. Ne pas exécuter de `migrate:rollback` automatique.
3. Télécharger `container-image.json` depuis la GitHub Release choisie. Le
   champ `image` contient le digest immuable et `commit` le SHA du Compose.
   Vérifier que le VPS peut toujours télécharger ce digest ; conserver dans
   GHCR les versions nécessaires aux retours arrière.
4. Dans les variables de l’application Coolify, définir `APP_IMAGE` sur ce
   digest (valeur littérale, disponible au build et à l’exécution dans Coolify). Dans **Git Source**,
   définir le commit sur le champ `commit` du même fichier.
5. Déployer depuis Coolify, sans reconstruction. Les quatre services récupèrent
   la même image. Vérifier leur santé, `/up`, WebSocket et les files. Toute
   migration supplémentaire reste une décision opérateur explicite.
6. Consigner la version restaurée. La prochaine Release PR livrée remplacera
   ces deux valeurs par sa propre référence.

Ne pas choisir simplement un ancien commit de `main` : le couple image/Compose
issu du manifeste de release est nécessaire. Aucun nouveau tag ni nouvelle
release ne doit être créé manuellement pour revenir en arrière.

## Sauvegardes

- Sauvegarde chiffrée quotidienne de MySQL, avec conservation de 30 jours et test mensuel de restauration.
- Sauvegarde quotidienne cohérente des volumes de métadonnées et de données
  Garage, avec la même politique de conservation.
- Les sauvegardes sont stockées hors du serveur de production. Une copie sur le même volume Docker ne constitue pas une sauvegarde.
- La restauration s’effectue sur une stack isolée : restaurer MySQL, restaurer
  les deux volumes Garage, déployer l'image applicative de la version compatible,
  exécuter les migrations nécessaires, puis vérifier `/up`, Reverb, les files
  et un objet privé avant de rediriger le trafic.

## Santé et alertes

- La route `/up` vérifie que l'application répond; elle ne divulgue ni version sensible ni configuration.
- Docker vérifie les services longs (`web`, `worker`, `scheduler`, `reverb`)
  avec un healthcheck adapté en production. Coolify supervise séparément les
  ressources MySQL, Redis et Garage indépendantes. Mailpit
  possède son propre healthcheck uniquement dans la stack locale.
- Configurer Coolify pour notifier les échecs de déploiement, conteneurs arrêtés, sauvegardes en erreur et manque d'espace disque.
- Les journaux applicatifs sont structurés, sans mot de passe, jeton OAuth, contenu de message privé ou données personnelles inutiles.

## Délivrabilité des e-mails

- Mailpit est le seul service SMTP fourni par la stack locale et ne doit jamais recevoir de trafic public.
- Resend est le transport de production via le mailer Laravel `resend`. La clé
  `RESEND_API_KEY` reste uniquement dans Coolify.
- Vérifier le domaine d’envoi dans Resend, définir `MAIL_FROM_ADDRESS` sur ce
  domaine et configurer SPF, DKIM et DMARC avant tout envoi réel.
- Après le premier déploiement, envoyer un e-mail transactionnel de contrôle et
  vérifier sa remise sans afficher la clé API ni le contenu du message dans les
  journaux.

## Récapitulatifs hebdomadaires par e-mail

Appliquer explicitement les migrations avant d’activer le worker et le scheduler
sur cette version (`php artisan migrate --force` dans le conteneur web).
Les migrations activent les deux réglages pour les comptes existants ; les
membres peuvent les modifier dans Réglages → Notifications.

Le scheduler exécute `notifications:dispatch-weekly-recaps` chaque dimanche à
15 h dans `Europe/Paris`, avec verrou de chevauchement et verrou multi-serveurs.
Garder le scheduler actif et utiliser le cache partagé déjà configuré.
Le worker consomme la file habituelle ; les jobs ont quatre tentatives avec
reprises après 60, 300 et 900 secondes. Le transport Laravel existant est utilisé.
Mailpit reste local ; cette fonctionnalité ne modifie pas le fournisseur de
production configuré.

Une relance manuelle de `php artisan notifications:dispatch-weekly-recaps`
réutilise l’échéance du dernier dimanche à 15 h et ne réserve pas de doublon.
Seuls les membres éligibles sont mis en file. Le job recalcule les compteurs et
contrôle les préférences, l’adresse vérifiée et la disponibilité avant envoi.
Une livraison réussie ou ignorée ne se rejoue pas ; une livraison encore en
attente expire au dimanche suivant à 15 h. Inspecter les jobs échoués avant
`php artisan queue:retry <id>` ; une reprise après expiration sera ignorée.
L’unicité applicative ne résout pas l’ambiguïté d’un transport ayant accepté
l’e-mail puis coupé la connexion avant son accusé de réception.

Pour vérifier en local, utiliser uniquement des comptes de test et Mailpit :
créer un échange reçu non lu de plus de trois jours, lancer la commande puis le
worker. Inspecter le rendu FR/EN, les seuls compteurs et les liens vers `/app` et
`/settings/notifications`. Lire le message ou désactiver le rappel avant la
consommation du job doit supprimer l’envoi. Contrôler les traces minimales dans
`weekly_email_recap_deliveries` ; elles sont exportées et supprimées à la purge.
Ne pas journaliser les adresses ou le contenu privé pour ce contrôle.

## Fournisseurs de connexion sociale

Configurer les secrets uniquement dans l'environnement d'exécution ou dans
Coolify, jamais dans le dépôt :

- Google : `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` et `GOOGLE_REDIRECT_URI`.

La console Google doit déclarer exactement le callback de production :
`GET /auth/google/callback`.

## Tâches récurrentes

- Le scheduler exécute chaque heure `accounts:dispatch-due-purges` pour remettre
  en file toute suppression échue. La commande utilise un verrou anti-chevauchement.
- Le worker est supervisé : un job en échec est journalisé et rejoué selon une politique explicite; après le dernier essai, il rejoint la table des jobs échoués.
- Après un déploiement, redémarrer proprement les workers pour qu'ils consomment le nouveau code.

Les sauvegardes quotidiennes chiffrées MySQL et fichiers ne sont jamais
réécrites pour une suppression individuelle. Leur rotation automatique doit
garantir une rétention maximale de 30 jours ; une restauration exceptionnelle
ne constitue pas un mécanisme de récupération de compte et doit préserver la
liste des suppressions arrivées à échéance avant toute remise en service.

## Reprise des broadcasts d'annonces partenaires

La notification Laravel en base est la source durable. Le broadcast temps réel
est délivré **at-least-once** : une panne après son acceptation par le transport
mais avant l'écriture de `broadcasted_at` laisse volontairement la livraison à
reprendre. Cette fenêtre peut produire une seconde émission, toujours avec le
même UUID et le même payload ; le client ou le transport la déduplique par
UUID.

Après un incident worker, Redis ou Reverb :

1. rétablir les services et vérifier que les workers consomment de nouveau les
   files ;
2. identifier dans l'administration les annonces concernées à l'état `sending`
   ou `sent` ;
3. déclencher leur action de relance administrateur ; elle remet les échecs de
   livraison à `pending`, ré-enfile les livraisons en attente et ré-enfile les
   broadcasts des livraisons `delivered` dont `broadcasted_at` est encore
   `null` ;
4. vérifier la résorption des jobs en échec et des broadcasts non confirmés.

Ne pas réinitialiser une livraison `delivered`, supprimer sa notification en
base ou corriger manuellement sa métrique. La relance est idempotente pour ces
effets durables ; seule la projection temps réel peut être répétée avec le même
UUID dans la fenêtre de crash décrite ci-dessus.

## PWA et Web Push

La PWA sert `/service-worker.js`, `/manifest.webmanifest` et `/offline.html`
sans cache HTTP durable. Seuls les fichiers versionnés sous `/build/assets/`
sont immuables. Après chaque déploiement, vérifier que le nouveau worker est
téléchargé puis activé depuis un navigateur installé. Un retour arrière consiste
à redéployer l’image précédente : son worker reprend la main au prochain cycle
de mise à jour sans supprimer les données locales du membre.

Le Web Push exige `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY` et `VAPID_SUBJECT`
(URL HTTPS ou adresse `mailto:` de contact opérationnel). Générer une paire hors
du dépôt avec
`php -r "require 'vendor/autoload.php'; print_r(Minishlink\\WebPush\\VAPID::createVapidKeys());"`,
puis conserver la clé privée exclusivement dans le gestionnaire de secrets.
Les workers doivent pouvoir joindre en HTTPS les services push des navigateurs,
notamment `*.push.apple.com` pour iOS. Après modification des variables,
redémarrer les workers Laravel.

Une rotation VAPID invalide les abonnements existants : déployer la nouvelle
paire, révoquer les appareils enregistrés avec l’ancienne clé et demander aux
membres de réactiver les notifications. Les réponses HTTP `404` et `410`
révoquent automatiquement un abonnement ; `429` et les erreurs serveur sont
rejouées par la file. Surveiller les jobs échoués et la table
`web_push_deliveries`. Les journaux ne doivent contenir ni endpoint, ni clés
d’abonnement, ni payload personnel : uniquement les UUID techniques, catégorie,
identifiant de notification et code de résultat.

## Déploiement de la migration des conversations

La migration qui crée `conversations` reprend tous les matches existants. Pour
éviter qu'une ancienne instance crée un match entre cette reprise et le
déploiement du nouveau code, ce déploiement exige une courte fenêtre de
maintenance :

1. placer toutes les instances HTTP en maintenance et arrêter les workers ;
2. exécuter `php artisan migrate --force` ;
3. déployer la nouvelle image applicative et redémarrer les workers ;
4. exécuter la requête suivante et vérifier qu'elle retourne `0`, puis rouvrir
   le service :

```sql
SELECT COUNT(*)
FROM matches
LEFT JOIN conversations ON conversations.match_id = matches.id
WHERE conversations.id IS NULL;
```

La migration reste additive et compatible avec l'image précédente pour
permettre un retour arrière avant la réouverture. Aucun trafic social ne doit
cependant être réactivé tant que la nouvelle image n'est pas en service.

## Procédure d'incident minimale

1. Vérifier la route de santé, les logs Coolify et les logs Laravel.
2. Stopper le déploiement si une migration ou la santé échoue.
3. Revenir à l'image applicative précédente uniquement après vérification de compatibilité de schéma.
4. Restaurer une sauvegarde seulement si le problème est une perte/corruption de données; journaliser l'opération et prévenir les utilisateurs concernés si nécessaire.

## Déploiement de la modération des échanges (issue 256)

Appliquer explicitement les deux migrations de modération et conservation
avec `docker compose exec web php artisan migrate --force`, après sauvegarde
et déploiement du code. Ne pas ajouter de migration automatique aux entrypoints.
Redémarrer tous les workers et tous les processus Reverb, sur chaque nœud,
pour charger le manager et les canaux privés exigeant une identité signée.
Les clients doivent se réauthentifier sur leurs canaux après reconnexion.
Ne pas laisser coexister un nœud Reverb ancien acceptant des abonnements non signés.

La version CGU `2026-10-04` déclenche une réacceptation de tous les comptes actifs
dont la preuve actuelle manque. Vérifier le contact `LEGAL_CONTACT_EMAIL` en
production. Le warning générique de révocation temps réel indisponible indique
une terminaison HTTP échouée ; le filtrage des canaux reste fermé aux comptes
inactifs et ne journalise aucune donnée privée.

Les comptes bannis n’ont aucune purge automatique ; les purges partenaires les
excluent sous verrou. La levée ne republie ni ne réinscrit automatiquement.
La suppression administrative explicite demeure irréversible et supprime les
données propres à la cible. Éviter un rollback des migrations de conservation
après création de graphes détachés : leur `down` retire ces graphes avant de
restaurer les contraintes non nulles et entraîne donc une perte de ces données.
Préférer une migration corrective conservant les données.
