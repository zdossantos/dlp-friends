# PWA installable et notifications Web Push — Design

## Contexte et résultat attendu

DLP Friends doit pouvoir être installé depuis un navigateur mobile puis lancé
comme une application autonome sur Android et iOS/iPadOS. Cette évolution doit
conserver l'expérience web actuelle sur les navigateurs non compatibles et ne
doit jamais rendre accessibles hors connexion des données privées ou périmées.

L'installation devient la dernière étape du tutoriel produit, après la
démonstration de conversation. Cette étape explique le parcours réellement
disponible sur la plateforme, propose le mécanisme natif du navigateur lorsqu'il
existe, puis permet d'activer explicitement les notifications. Une personne peut
passer l'étape après avoir confirmé qu'elle comprend qu'elle ne recevra pas les
notifications Web Push tant qu'elle n'aura pas installé l'application et accordé
la permission requise.

Le périmètre étend l'issue #203 avec le Web Push immédiat prévu par l'issue #182,
appliqué à toutes les catégories de notifications internes existantes : messages
privés, nouveaux univers croisés, événements, annonces partenaires et
administration. Les récapitulatifs par e-mail restent distincts.

## Principes de conception

- Utiliser exclusivement les standards Web App Manifest, Service Worker, Push,
  Notifications et Badging, sans Firebase, OneSignal ni application native.
- Appliquer l'amélioration progressive : le site reste utilisable sans service
  worker, installation, permission de notification ou API de badge.
- Ne mettre en cache aucune réponse Laravel, page Inertia, donnée authentifiée,
  image privée, notification, message ou autre contenu personnel.
- Ne demander une permission système qu'à la suite d'une action explicite.
- Afficher des notifications discrètes et génériques qui ne révèlent aucune
  identité ni aucun contenu privé sur un écran verrouillé.
- Détecter les capacités plutôt que déduire le comportement du seul user-agent,
  sauf pour choisir les instructions manuelles propres à iOS/iPadOS.
- Respecter le français, l'anglais, le clavier, les zones sûres mobiles et
  `prefers-reduced-motion`.

## Manifeste, identité et lancement

Un manifeste `/app.webmanifest` de même origine est lié depuis tous les
documents qui peuvent conduire à l'installation. Il définit au minimum :

- un `id` stable `/`, `scope: "/"` et une `start_url` pointant vers `/app` ;
- `name: "DLP Friends"`, un nom court, une description localisable lorsque la
  plateforme l'autorise, `display: "standalone"` et une orientation non forcée ;
- les couleurs de thème et de fond issues du design system ;
- des icônes DLP Friends 192 px et 512 px avec variantes `any` et `maskable`,
  plus l'icône Apple 180 px déjà servie depuis le document ;
- des captures mobiles françaises et anglaises sans marque ou personnage Disney,
  destinées aux interfaces d'installation enrichies qui les prennent en charge ;
- une catégorie sociale et aucun lien vers une application de store.

La route `/app` reste l'aiguillage serveur canonique. Elle redirige une personne
vers la destination permise par son authentification, ses rôles et l'état de son
profil. Le démarrage depuis l'icône ne contourne donc aucun middleware.

Les documents Inertia ajoutent les métadonnées mobiles cohérentes avec le
manifeste, notamment la couleur de thème et le comportement autonome Apple. Le
layout conserve les `safe-area-inset-*` existants et les étend aux surfaces qui
occupent la hauteur de l'écran installé.

## Service worker et stratégie hors connexion

Le service worker est construit par Vite avec une URL publique stable. Son
contenu et le manifeste de précache injecté changent à chaque build ; l'URL du
script elle-même n'est pas versionnée. L'enregistrement se fait après le rendu
initial, uniquement en environnement de production ou dans un mode de test
explicite, avec une portée `/` et une gestion d'échec non bloquante.

Le précache contient uniquement :

- les bundles JS/CSS et polices produits par Vite avec nom de fichier haché ;
- les icônes et éléments statiques de marque nécessaires au shell ;
- une page hors connexion autonome, localisée à partir de la dernière locale
  publique connue sans inclure de donnée membre.

Les requêtes de navigation, Inertia, XHR/fetch, formulaires, images contrôlées,
routes `/storage`, WebSocket et toute réponse authentifiée restent `network
only`. Le service worker ne stocke pas leurs réponses. Si une navigation échoue
faute de réseau, il sert la page hors connexion plutôt qu'un écran applicatif
ancien ou incomplet. Les méthodes autres que `GET` ne sont jamais interceptées
pour mise en cache ou rejeu.

À l'activation, le worker supprime les caches DLP Friends d'anciennes versions
sans toucher aux caches étrangers. Une mise à jour installée attend que les
clients actuels soient fermés ou que la personne accepte l'action « Mettre à
jour ». Il n'y a ni `skipWaiting` automatique ni rechargement forcé pendant un
formulaire. L'application détecte un worker en attente, affiche une action
localisée, lui envoie un message d'activation, puis recharge une seule fois
après `controllerchange`.

## Installation et navigation mobile

Un module frontend centralise l'état `browser`, `standalone`, `installable`,
`installed`, `unsupported` et les capacités de push. Il combine la media query
`(display-mode: standalone)`, `navigator.standalone` pour les anciennes versions
iOS et les événements d'installation disponibles.

Sur Chromium, l'application conserve l'événement `beforeinstallprompt` et ne
déclenche `prompt()` qu'après l'action « Installer ». Elle utilise ensuite le
résultat natif et l'événement `appinstalled`; aucune fausse boîte de dialogue ne
copie l'interface du système.

Sur iOS/iPadOS en mode navigateur, le tutoriel affiche une illustration locale
et réaliste de la séquence « Partager » puis « Sur l'écran d'accueil ». Il ne
prétend pas pouvoir déclencher ce parcours. Dans une app déjà autonome, l'étape
reconnaît l'installation et passe directement au choix des notifications. Les
navigateurs sans mécanisme détectable reçoivent une explication sobre et peuvent
continuer.

Les liens internes de même origine utilisent la navigation Inertia ou une
navigation de fenêtre dans le périmètre du manifeste. Les liens externes HTTPS
restent identifiables comme externes et s'ouvrent dans le navigateur avec les
protections `noopener` et `noreferrer`. Aucun `target="_blank"` n'est introduit
pour une route interne.

## Dernière étape du tutoriel

`ProductOnboardingStep` reçoit une étape `InstallApp` après
`ConversationDemo`. Envoyer le message de démonstration fait avancer vers cette
étape au lieu de terminer le tutoriel. Une migration normalise uniquement les
progressions historiques qui seraient devenues incohérentes ; les tutoriels déjà
terminés restent terminés.

La nouvelle étape présente dans cet ordre :

1. le bénéfice et l'état d'installation adapté à la plateforme ;
2. l'action native d'installation ou les instructions iOS ;
3. après installation détectée, l'action explicite d'activation des
   notifications ;
4. l'état résultant de la permission et de l'abonnement ;
5. l'action de fin du tutoriel.

Une installation ne peut pas être vérifiée immédiatement après avoir quitté
Safari pour ajouter l'icône. La progression est persistée sur `InstallApp` et se
réévalue au prochain lancement autonome. Sur une plateforme incompatible, ou si
la personne ne souhaite pas installer/autoriser, « Passer cette étape » ouvre une
confirmation localisée qui décrit l'impact sur les notifications hors
application. La confirmation termine le tutoriel sans simuler une installation
et sans modifier la permission système.

Les réglages Notifications permettent ensuite d'installer l'abonnement, de
réessayer après un état réparable, de consulter les appareils abonnés et de les
révoquer. Une permission système refusée n'est jamais redemandée en boucle ; la
page explique comment la modifier dans les réglages du système ou du navigateur.

## Modèle de données Web Push

Une table `web_push_subscriptions` associe plusieurs appareils à un membre :

- UUID public, `user_id`, endpoint unique et clés de chiffrement `p256dh` et
  `auth` ;
- type de contenu supporté, nom de plateforme/navigateur limité et non unique,
  date de dernière confirmation, date de dernier succès et `revoked_at` ;
- timestamps et suppression en cascade avec le compte.

L'endpoint et les clés sont des secrets de livraison : ils sont chiffrés au
repos avec le chiffrement Laravel, masqués des logs et jamais partagés à
Inertia. La réponse des routes d'abonnement expose seulement l'UUID, un libellé
d'appareil, les dates utiles et l'état actif. L'enregistrement utilise l'endpoint
comme identité idempotente et refuse d'affecter silencieusement à un autre
compte un endpoint encore actif.

Une table ou un modèle unique de préférences membre stocke les booléens
`messages`, `matches`, `events`, `partner_announcements` et `administration`,
ainsi que les préférences de récapitulatif de #182. Les préférences immédiates
sont activées par défaut au moment où la personne crée volontairement son
premier abonnement ; elles restent modifiables indépendamment. « Tout
désactiver » désactive les préférences immédiates et révoque tous les appareils.
La préférence partenaire existante est migrée sans perdre le choix du membre.

L'export personnel mentionne les préférences et les métadonnées non secrètes des
appareils, mais exclut endpoints et clés. La suppression du compte retire les
abonnements dès la désactivation immédiate du compte, sans attendre la purge à
30 jours.

## Livraison des notifications

Le projet utilise une bibliothèque PHP maintenue implémentant Web Push et VAPID.
Les clés VAPID et l'adresse de contact sont fournies par variables
d'environnement, validées au démarrage de la livraison et documentées dans les
opérations. Elles ne sont jamais générées automatiquement au déploiement.

Les notifications Laravel persistantes restent la source de vérité. Un canal
Web Push commun transforme les catégories existantes en un petit contrat :

- identifiant stable de notification pour la déduplication ;
- catégorie ;
- titre et corps génériques traduits selon la locale du destinataire ;
- URL relative interne issue d'une liste de routes permises ;
- nombre de notifications internes non lues pour le badge.

Les messages privés n'exposent ni contenu, ni auteur, ni conversation sur
l'écran verrouillé. Les autres catégories n'exposent pas davantage le nom d'un
membre, d'un événement ou d'un partenaire. Le clic authentifié révèle ensuite
le détail déjà protégé par les Policies et middlewares.

Avant chaque mise en file puis juste avant l'envoi, le serveur vérifie : compte
actif, préférence de catégorie, abonnement actif, accessibilité de la cible et,
pour les interactions sociales, absence de blocage. Une notification devenue
inaccessible est abandonnée. Chaque couple notification/abonnement possède une
clé d'idempotence pour éviter les doublons lors des reprises.

Les envois sont groupés sans partager les payloads entre membres. Un succès met
à jour la dernière livraison. Les réponses permanentes d'abonnement expiré ou
introuvable révoquent l'appareil ; les erreurs temporaires utilisent la politique
de reprise de la file et n'empêchent pas les autres abonnements d'être traités.
Les journaux ne contiennent que l'UUID interne d'abonnement, la catégorie, le
code de résultat et une corrélation technique.

## Réception, clic et badge

Le service worker traite `push` avec `event.waitUntil` et affiche toujours une
notification visible lorsque le payload est valide. Un payload invalide produit
une notification générique ouvrant le centre des notifications, jamais une
exception silencieuse. Le tag dérivé de l'identifiant stable évite les doublons
et le nombre non lu met à jour le badge lorsque l'API existe.

Sur `notificationclick`, le worker ferme la notification, valide que la cible
est une URL relative de même origine, recherche une fenêtre autonome de même
origine, la focalise et lui transmet la cible par `postMessage`. L'application
effectue alors une navigation Inertia. Sans client approprié, `clients.openWindow`
ouvre la cible. Une cible invalide ou refusée retombe sur `/notifications`.

À l'ouverture de l'application et après une lecture, le frontend synchronise le
badge depuis le compteur serveur partagé. Un compteur nul appelle
`clearAppBadge` lorsque disponible. L'absence de Badging API ne change aucun
autre comportement.

Le frontend réconcilie au démarrage l'abonnement du navigateur avec le serveur,
car les navigateurs ne garantissent pas tous un événement fiable lors d'une
rotation d'abonnement. Une nouvelle clé remplace idempotemment l'enregistrement
de cet appareil ; un abonnement local absent révoque son enregistrement serveur
si l'application peut l'identifier.

## Accessibilité, langues et illustrations

Tous les textes visibles appartiennent aux catalogues Laravel/frontend français
et anglais. Les états d'installation, permission, abonnement, erreur et mise à
jour sont annoncés sans dépendre uniquement de la couleur. Les actions sont
atteignables au clavier, disposent d'un focus visible et conservent une cible
tactile d'au moins 44 px.

Les illustrations d'installation sont des compositions locales inspirées des
contrôles génériques du système, sans capture dépendante d'une version précise,
logo tiers protégé ni asset distant. Les animations décoratives sont désactivées
avec `prefers-reduced-motion`. L'étape fonctionne sans animation et en zoom à
200 %.

## Configuration et exploitation

Les variables d'environnement documentées couvrent l'activation du Web Push,
la clé publique VAPID, la clé privée VAPID et le contact `mailto:`. Une
configuration absente laisse la PWA installable mais expose un état de push
indisponible ; elle ne casse ni le boot ni le tutoriel.

Le déploiement sert le service worker avec un type JavaScript correct et une
politique de revalidation, le manifeste avec
`application/manifest+json`, et les assets hachés avec cache immutable. Les
réponses privées conservent leurs protections existantes. L'infrastructure doit
autoriser les endpoints Web Push, notamment `*.push.apple.com` pour les appareils
Apple.

Les opérations documentent la génération hors ligne des clés VAPID, leur
rotation coordonnée — qui révoque les abonnements incompatibles —, le diagnostic
des files, les métriques agrégées succès/révocation/erreur et le retour arrière
d'un service worker défectueux.

## Tests et critères de livraison

Les tests automatisés couvrent :

- contenu, type MIME, icônes, couleurs, portée et URL de départ du manifeste ;
- enregistrement tardif et non bloquant du service worker ;
- liste exacte du précache, refus du cache pour navigations et données privées,
  repli hors connexion, nettoyage versionné et activation contrôlée ;
- détection autonome, parcours Chromium, instructions iOS, replis incompatibles,
  navigation interne/externe et réduction des animations ;
- transition serveur du tutoriel vers `InstallApp`, reprise, confirmation de
  passage et non-régression des tutoriels déjà terminés ;
- consentement explicite, création idempotente, multi-appareils, préférences par
  catégorie, révocation et suppression du compte ;
- contenu confidentiel, vérification des autorisations et blocages, idempotence,
  reprise temporaire et révocation des endpoints expirés ;
- réception, clic, validation de cible, focus/ouverture, déduplication et badge ;
- français, anglais, clavier, affichage mobile et zones sûres.

Les vérifications manuelles de livraison utilisent au minimum Chrome Android et
Safari iOS en installation réelle, permission acceptée/refusée, application au
premier plan/fermée, lien de notification et mise à jour du service worker. Un
audit Lighthouse mobile vérifie l'installabilité, les bonnes pratiques,
l'accessibilité et l'absence de régression significative des performances.

## Hors périmètre

- Application App Store/Google Play, wrapper natif ou API de notification native.
- Consultation ou saisie métier hors connexion, synchronisation différée et
  cache de données membre.
- Contenu de message ou identité personnelle dans une notification système.
- Push silencieux, géolocalisation, segmentation marketing et fournisseur push
  tiers.
- Refonte générale du centre de notifications ou des règles métier propres aux
  notifications déjà persistées.

## Références de plateforme

- [Web Push for Web Apps on iOS and iPadOS](https://webkit.org/blog/13878/web-push-for-web-apps-on-ios-and-ipados/)
- [What makes a good Progressive Web App?](https://web.dev/articles/pwa-checklist)
- [Installation prompt](https://web.dev/learn/pwa/installation-prompt)
- [Service worker lifecycle](https://web.dev/articles/service-worker-lifecycle)
- [Precaching dos and don'ts](https://developer.chrome.com/docs/workbox/precaching-dos-and-donts)
- [Push API](https://developer.mozilla.org/en-US/docs/Web/API/Push_API)

