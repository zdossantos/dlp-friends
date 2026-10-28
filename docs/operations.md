> Production séparée : utiliser [le parcours rolling](automatic-rolling-releases.md).
> Ne pas exécuter `release.py prepare/deploy/resume` sur la topologie web natif +
> support : cette ancienne procédure est réservée au stack Compose fermé.

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

Un merge ordinaire dans `main` ne déploie rien. Le merge volontaire de la
Release PR publie l’image et sa référence ; il ne livre plus la production.
Suivre exclusivement la procédure
[prepare → deploy → verify → resume](#livraisons-contrôlées-et-répétition-bêta),
après sa transition initiale. Un déploiement « Success » ou un `/up` à 200 ne
prouve pas que le schéma attendu est installé. Les migrations et la reprise
restent des actions opérateur explicites après répétition et sauvegarde vérifiées.

### Incident de profil après la 1.15.0

Une erreur `Attempt to read property "value" on null` dans
`MemberProfileController` peut signaler que la migration des liens sociaux
n’a pas été appliquée. Vérifier le statut de toutes les migrations, notamment
`2026_10_03_100000_add_social_links_to_profiles_table`, sans afficher les données
des membres. La valeur de repli utilisée en lecture est `matches` ; elle ne
remplace pas les migrations et ne permet pas d’enregistrer les nouveaux champs
sur un ancien schéma. Appliquer les six migrations de la 1.15.0 avant toute
remise en production de cette version, selon la procédure ci-dessus.

## Bascule d’une installation existante

La bascule historique vers les images GHCR (issue 132) est remplacée pour les
livraisons futures par la procédure contrôlée ci-dessous. Conserver les anciens
digest/commit et désactiver les déclencheurs Coolify qui contournent cette
procédure. Aucun jeton de livraison production n'est fourni au workflow de publication.

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

### Disponibilité de l’inscription par e-mail (issue 272)

Avec le transport `resend`, l’application consulte `GET https://api.resend.com/usage`
([API Usage officielle](https://www.resend.com/changelog/account-usage-api)).
Configurer `RESEND_USAGE_API_KEY` avec une clé Resend **Full access** du même
compte que la clé d’envoi, conservée uniquement côté serveur dans Coolify. La clé
d’envoi `RESEND_API_KEY` peut ainsi rester limitée à l’envoi. Sans clé dédiée, `RESEND_API_KEY` est utilisée : elle
doit alors avoir accès à Usage. Une clé limitée à l’envoi produit un refus de
consultation et suspend l’inscription e-mail. Ne jamais afficher ces clés.

Les périodes `emails.daily` et `emails.monthly` sont vérifiées ensemble ;
`used >= limit` pour une seule période suspend l’inscription, et une limite
`null` signifie illimitée. Les dates de réinitialisation proviennent de Resend,
sans supposer une heure locale ou un jour calendaire de facturation.
La page d’inscription utilise un état mis en cache au plus 60 secondes, limité
également par la prochaine réinitialisation. Il n’y a pas de tâche planifiée :
la première consultation après expiration actualise cet état.

Chaque soumission valide du formulaire reconsulte Resend avant toute création
de compte et tout envoi de vérification, même si la page a été ouverte avant
l’épuisement. Les connexions existantes, Google, la récupération du mot de passe
et les autres envois restent indépendants de ce contrôle. Mailpit et le transport
de test `array` ne consultent jamais Resend.

Une clé absente, une erreur HTTP (y compris 403/429), un délai dépassé (connexion
2 secondes, total 5 secondes), une réponse incomplète ou une période déjà périmée
suspendent aussi l’inscription e-mail. Aucun ancien état disponible n’est utilisé
en repli. Le formulaire disparaît au prochain chargement et un message FR/EN
invite à continuer avec Google. Le lien de connexion reste présent.
L’inscription se réactive automatiquement dès qu’un contrôle confirme que les
deux quotas sont disponibles ; pour la page, le délai de cache est au maximum
60 secondes. Aucun corps de réponse fournisseur ni secret n’est journalisé.

Ce contrôle lit l’état fournisseur sans réserver de crédit : un autre envoi
sur le même compte Resend peut consommer le dernier crédit entre le contrôle
et l’envoi de vérification. Ce mécanisme ne garantit pas la remise des messages.

## Récapitulatifs hebdomadaires par e-mail

Appliquer explicitement les migrations avant d’activer le worker et le scheduler
sur cette version (`php artisan migrate --force` dans le conteneur web).
La migration `2026_10_17_100000` désactive les deux réglages pour tous les comptes
existants et les nouvelles inscriptions. Les membres peuvent les réactiver
volontairement dans Réglages → Notifications. Les rappels déjà en file sont
ignorés après désactivation, grâce au contrôle des préférences avant envoi.
Un rollback rétablit les valeurs par défaut du schéma sans réabonner les comptes.
Les e-mails de vérification, de récupération du mot de passe et de suppression
de compte restent actifs.

Le scheduler exécute `notifications:dispatch-weekly-recaps` chaque dimanche à
15 h dans `Europe/Paris`, avec verrou de chevauchement et verrou multi-serveurs.
Garder le scheduler actif et utiliser le cache partagé déjà configuré.
Le worker consomme la file habituelle ; les jobs ont quatre tentatives avec
reprises après 60, 300 et 900 secondes. Le transport Laravel existant est utilisé.
Mailpit reste local ; cette fonctionnalité ne modifie pas le fournisseur de
production configuré.

Une relance manuelle de `php artisan notifications:dispatch-weekly-recaps`
réutilise l’échéance du dernier dimanche à 15 h et ne réserve pas de doublon.
Elle remet en file les réservations encore en attente, notamment après une panne
entre leur création et la publication du job. Plusieurs jobs peuvent ainsi viser
la même réservation ; le verrou et les états terminaux du job limitent l’envoi
applicatif à un seul e-mail. Les livraisons envoyées ou abandonnées ne sont pas
remises en file.
Seuls les membres éligibles sont mis en file. Le job recalcule les compteurs et
contrôle les préférences, l’adresse vérifiée et la disponibilité avant envoi.
Une livraison réussie ou ignorée ne se rejoue pas ; une livraison encore en
attente expire au dimanche suivant à 15 h. Inspecter les jobs échoués avant
`php artisan queue:retry <id>` ; une reprise après expiration sera ignorée.
L’unicité applicative ne résout pas l’ambiguïté d’un transport ayant accepté
l’e-mail puis coupé la connexion avant son accusé de réception.

Pour vérifier en local, utiliser uniquement des comptes de test et Mailpit :
activer le rappel, créer un échange reçu non lu de plus de trois jours, lancer
la commande puis le worker. Inspecter le rendu FR/EN, les seuls compteurs et les liens vers `/app` et
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

## Livraisons contrôlées et répétition bêta

**État : préparé dans le dépôt, activation serveur non validée.** Cette
procédure remplace la livraison automatique après publication dès intégration
du workflow. Une GitHub Release publie son image et `container-image.json` ;
elle ne déploie plus la production. Désactiver également Auto Deploy et les
anciens webhooks Coolify avant activation. Ne pas utiliser l'ancien script
`deploy-release.sh`, qui refuse désormais la livraison.

Prérequis et copie isolée : [environnement bêta](beta-environment.md). Les
commandes suivantes s'exécutent sur le serveur avec la configuration privée,
jamais dans GitHub Actions. `--image` exige un digest GHCR ; `--commit` le SHA
complet de cette image. Les labels `io.dlp-friends.source-tree` et
`io.dlp-friends.migrations-sha256` sont contrôlés, puis les fichiers de
migration réellement embarqués sont hachés. Le tree complet peut correspondre
malgré des SHA différents après squash. Un changement de tree ou de migration
exige une nouvelle répétition.

### Transition initiale obligatoire

Avant toute procédure, préparer une fenêtre de maintenance et conserver le
digest/commit de l'image précédente. L'ancienne **et** la nouvelle image doivent
partager `APP_MAINTENANCE_DRIVER=cache`, `APP_MAINTENANCE_STORE=redis`,
`CACHE_PREFIX=dlp_friends_production_cache_`,
`REDIS_PREFIX=dlp_friends_production_`, `REDIS_CACHE_DB=1`, `REDIS_DB=0`
et le même Redis de production. Le script vérifie aussi son identité réelle,
la base sélectionnée et le préfixe client effectif du stockage de maintenance.
Le passage initial depuis une maintenance fichier/base exige une fermeture
HTTP au niveau du proxy pendant la transition : un drapeau de l'ancien
stockage n'est pas lu par le nouveau. La modification initiale des préfixes
Redis/cache peut déconnecter les sessions existantes et invalider le cache.
Vérifier cette transition avant d'ôter la fermeture proxy. Ne pas annoncer
ces protections actives avant cette vérification réelle.
L'image précédente doit aussi embarquer `docker/php/drain.conf` : le délai
PHP-FPM de 86400 s empêche une escalade en TERM/KILL pendant les 150 s de
drainage contrôlé. Le script refuse une autre configuration avant le signal.

### Répétition sur la copie privée

```sh
bash scripts/operations/production-release.sh --config /srv/dlp-operations/private/config.json rehearse --image "$BETA_IMAGE" --commit "$BETA_COMMIT"
```

Le premier passage applique explicitement les migrations, en mesure la durée,
les relance et exige zéro nouvelle migration. Il écrit un rapport
`awaiting_journeys`, puis échoue tant que l'attestation manque. Conserver la
copie, ouvrir **seulement la bêta réservée aux administrateurs actifs**, démarrer ses processus et
tester connexion, profil existant, découverte, message temps réel, fichier,
e-mail capturé et file. Refermer et arrêter proprement les processus après
les essais. Remplir `beta-journeys.json` en `0600` : `snapshot_id`,
`candidate_image`, `candidate_commit`, `completed_at` (timestamp Unix) et
`checks` avec `login`, `existing_profile`, `discovery`, `realtime_message`,
`file`, `captured_mail`, `queue`, chacun à `true` **après vérification réelle**.
Relancer exactement la même commande : elle finalise le rapport sans réappliquer
les migrations si l'image, le snapshot et le schéma bêta correspondent.

La clôture refuse un snapshot âgé de 24 h ou plus, une date future, un rapport
incomplet et une évolution du schéma production depuis la copie. Après une
nouvelle copie ou un autre candidat, refaire les parcours et l'attestation.
Un nouveau candidat ou une migration interrompue exige de repartir d'une copie
fraîche : avant toute nouvelle exécution, schéma et registre de migrations bêta
doivent encore correspondre au snapshot initial. Seule la reprise des parcours
du même candidat en état `awaiting_journeys` réutilise la copie migrée.
Une migration manquante dans le code ne se déduit pas d'une liste vide de
migrations en attente : chaque futur changement de schéma doit avoir un test
d'upgrade avec données anciennes dans `tests/Feature/Database/`, en complément
des parcours et de la revue. Préférer ajout compatible, reprise rejouable puis
retrait différé des anciens champs.

### Préparer, livrer, contrôler, rouvrir

1. Exécuter `production-release.sh ... prepare --image "$RELEASE_IMAGE" --commit "$RELEASE_COMMIT"`.
   Le script ferme en maintenance persistante et arrête/drain les trois
   processus (150 s maximum chacun), puis envoie SIGQUIT au maître PHP-FPM et
   attend la disparition de tous ses processus (150 s maximum), donc la fin des
   requêtes et callbacks en cours. Nginx et Artisan privé restent disponibles ;
   les nouvelles requêtes applicatives échouent jusqu'au remplacement du web.
   Il recontrôle ce drain avant livraison et conserve l'ancien digest/commit,
   exporte MySQL et télécharge les objets privés avec contrôle de contenu.
   Il attend ensuite une preuve de restauration ; la maintenance reste active.
   Si le délai de drainage expire, aucune sauvegarde ni livraison ne démarre.
   Garder fermé, identifier la requête bloquée et attendre sa terminaison avant
   de reprendre. Ne pas relancer PHP-FPM ni lever la maintenance pendant ce drain.
   Si la procédure doit être abandonnée après drainage, remplacer volontairement
   le web par le digest précédent, toujours en maintenance, puis contrôler le
   schéma et les processus avant toute réouverture opérateur.
2. Restaurer cette sauvegarde dans une **troisième base jetable isolée**, jamais
   dans la production ni par-dessus la répétition conservée. Vérifier schéma,
   comptages et téléchargement des objets. Créer `backup-restore.json` privé
   avec les valeurs `backup_id`, `sql_sha256`, `objects_sha256` de `backup.json`,
   `completed_at` et les booléens `restored_to_isolated_database`,
   `schema_and_counts_verified`, `objects_download_verified`. Aucun booléen
   n'est prévalidé. Relancer `prepare` avec les mêmes arguments pour produire
   `authorization.json`. Sans preuve, aucune livraison n'est autorisée.
3. Exécuter `... deploy --image ... --commit ...`. Le script recontrôle la
   répétition, le schéma source, la fermeture, le drain et la sauvegarde ;
   il épingle image/commit puis demande Coolify une seule fois. Un timeout
   laisse `deployment_unknown` : inspecter Coolify, ne pas relancer à l'aveugle.
   Actualiser dans la configuration privée les références des conteneurs
   remplacés. Le jeton curl Coolify reste dans un fichier `0600` serveur.
4. **Action séparée et volontaire :** dans le conteneur web de l'image précise,
   appliquer `php artisan migrate --force --no-interaction`. Ni `deploy`, ni
   l'entrypoint ne le font. En cas d'échec, conserver la maintenance et les
   processus arrêtés. Après le remplacement Coolify, arrêter proprement ses
   processus en attente avant de poursuivre ; ils ne doivent traiter aucun job.
5. Contrôler les parcours de lecture via un bypass Laravel privé de maintenance
   (`php artisan down --with-secret`, sortie conservée hors logs publics).
   Le bypass autorise aussi des écritures : le réserver à l'opérateur et aux
   comptes de test. Remplir `production-journeys.json` : `image`, `commit`,
   `deployment_uuid`, `completed_at`, `checks` (`login`, `existing_profile`,
   `discovery`, `file`). Les essais d'envoi, de file et de temps réel ont été
   effectués en bêta pour éviter des envois aux membres pendant cette phase.
   `... verify --image ... --commit ...` exige déploiement terminé, commit,
   digest, santé web et `deployment:check --allow-maintenance` réussis.
6. Dans l'heure suivant la vérification, `... resume --image ... --commit ... --confirm-resume`
   redémarre les conteneurs, puis lève la maintenance. Leurs entrypoints
   attendent cette levée et revérifient le schéma avant de lancer les processus
   (délai habituel jusqu'à deux secondes). Contrôler ensuite les trois santés
   et la reconnexion temps réel ; en cas d'échec refermer immédiatement et
   investiguer. Révoquer le bypass et vérifier les nouvelles connexions.

Aucune erreur ne lance `up`, `migrate:rollback` ou une restauration automatique.
Un rollback d'image ne restaure pas les données ; garder fermé puis choisir une
correction en avant ou une restauration contrôlée selon les écritures depuis
la sauvegarde. Ne pas exécuter une livraison production pour tester cette
procédure : sa première utilisation reste une opération volontaire.
