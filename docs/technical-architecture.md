# Architecture technique

Ce document décrit l'architecture actuelle. Les fonctions prévues mais non
livrées restent décrites dans le [`PRD.md`](PRD.md).

## Surface légale publique

Les quatre routes légales localisées sont rendues en Blade sans JavaScript,
avec canonical, hreflang et sitemap. `PublicUrls` fournit leurs URL aux pages
publiques et aux layouts Inertia. `terms_acceptances` enregistre de façon
immuable `user_id`, `terms_version` et `accepted_at` dans la transaction de
création du compte et lors des réacceptations ; la suppression du compte supprime aussi cette preuve.

## Stack applicative

- Laravel 13 sur PHP 8.4 ;
- Inertia 3, Vue 3 Composition API et TypeScript ;
- Tailwind CSS et composants accessibles fondés sur Reka UI ;
- Bun 1.3.14 pour les dépendances et commandes frontend ;
- MySQL 8.4 pour les données relationnelles ;
- Redis pour le cache, les sessions et les files ;
- Laravel Reverb et Echo pour le temps réel ;
- le canal privé personnel diffuse les nouveaux univers croisés, les aperçus
  de messages, de réactions et les notifications d’événement dans tout l’espace membre,
  tandis que le canal de conversation
  conserve la synchronisation du fil et des états de lecture ;
- les heartbeats de présence maintiennent une clé Redis expirant après 45
  secondes et limitent la mise à jour de `last_active_at` à une fois par minute ;
  les messages, totaux absolus de réactions et états de lecture sont diffusés uniquement aux interlocuteurs de conversations
  actives, tandis que la saisie utilise un événement client privé sans contenu ;
- Pest, Pest Browser, Playwright, PHPStan/Larastan, Pint, ESLint et Prettier
  pour la qualité.

Les versions exactes sont verrouillées dans `composer.lock` et `bun.lock`. Bun
1.3.14 est épinglé dans `package.json`, GitHub Actions et le build Docker.

## Frontière applicative

Laravel porte l'authentification, les autorisations et les règles métier.
Vue/Inertia affiche les pages servies par Laravel ; aucune API distincte n'est
nécessaire actuellement. Toute action sensible doit être protégée côté serveur,
de préférence avec une Policy Laravel.

## Résolution des thèmes saisonniers

`seasonal_themes` stocke les plages Halloween et Noël ainsi que l’éventuelle
activation manuelle. `ResolveActiveSeasonalTheme` applique à chaque requête la
priorité manuel, planning, standard. Le partage Inertia expose `seasonalTheme`
avec le thème actif et le prochain instant de transition ; le composable
frontend programme alors un minuteur et recharge cette seule donnée à
l’échéance. Aucun scheduler ni cron n’est nécessaire.

Les routes `admin.seasonal-themes.*`, protégées par rôle et Policy, permettent
de modifier une plage, d’activer un thème et de désactiver le mode manuel. Les
dates saisies dans le fuseau de l’application sont persistées comme instants
UTC. Les classes racine saisonnières modifient les tokens CSS, tandis que les
composants de décoration, de match et de conversation sélectionnent leurs
icônes dans Lucide.

Le domaine événements repose sur `Event` et `EventRegistration`. Les Form
Requests valident les entrées HTTP, `EventPolicy` protège la lecture et les
détails privés, et les Actions transactionnelles portent création, inscription,
décision, retrait, modification, annulation et intégration du blocage. Les
routes Inertia `/events` servent la découverte, l’historique et la gestion sans
API parallèle. Les prises de place verrouillent la ligne événement en MySQL.

Les notifications Laravel sont stockées en base puis diffusées sur le canal
privé personnel après validation métier. Un contrat commun catégorise
conversations et événements et conserve seulement une clé de traduction, ses
paramètres et la cible. Le centre Inertia filtre ce flux ; l’ouverture marque
la notification comme lue avant de rediriger vers l’événement ou la
conversation autorisée.

L’arrivée d’un membre autonome est annoncée lors de sa première complétion de
profil vérifiée. La transaction verrouille le membre, les administrateurs
éligibles et leurs réglages, écrit les notifications persistantes et le marqueur
définitif `new_member_announced_at`. Les modifications de préférence verrouillent
la même ligne utilisateur. Broadcast et Web Push ne sont déclenchés qu’après
commit, avec revérification du destinataire ; les workers de diffusion et Push
revérifient aussi son rôle et son réglage avant livraison. Une panne de mise en
file est signalée sans annuler le profil ni sa notification persistante, et
les autres canaux et destinataires continuent. Aucun rejeu n’est déclenché par une
connexion, un changement de préférence ou une nouvelle attribution de rôle.

## Internationalisation

Le français est la langue par défaut et de repli. Le choix de langue suit, dans
l'ordre, la préférence enregistrée du compte, le cookie `locale`, l'en-tête
`Accept-Language`, puis le français. Les langues actuellement prises en charge
sont `fr` et `en`.

Laravel est la source de vérité des traductions. Les catalogues sont organisés
par feature métier dans `lang/{locale}/`, avec un catalogue `common.php` pour
les libellés réellement partagés. Les mêmes clés servent au backend et sont
transmises à Inertia pour le composable `useTranslations`. Aucun texte visible
n'est écrit directement dans Vue, PHP ou Blade.

Les noms d'intérêts restent stockés en français dans `interests.name` et leur
traduction anglaise visible dans `interests.name_en`. En l'absence de traduction
anglaise, l'interface affiche le nom français. Cette structure ne s'étend pas à
des catégories ou à d'autres taxonomies tant qu'elles restent hors périmètre.

`users` contient les données privées de compte. `profiles` contient les données
publiques et l'état d'onboarding. La vérification de l'e-mail précède
l'onboarding, puis les middlewares limitent l'accès selon l'état du compte, du
profil et des rôles.

Le tutoriel produit possède un état séparé dans `product_onboardings` : statut
`not_started`, `in_progress`, `completed` ou `skipped`, et étape courante. Une
Action transactionnelle verrouille la progression et impose l’ordre des étapes.
La page Vue n’appelle jamais les routes sociales : ses cartes, son match, sa
conversation et son message restent des données locales et ne créent
aucun swipe, match, conversation, message ni diffusion temps réel.

La table singleton `product_onboarding_settings` référence les deux avatars de
tutoriel et stocke séparément leurs noms et biographies en français et en
anglais. Le français est obligatoire ; une traduction anglaise absente se
replie explicitement sur le français. Les validations imposent aussi des
avatars actifs et distincts ; les actions d’archivage et de suppression les
protègent également côté serveur.
L’écran admin calcule ses agrégats et sa liste paginée sur le même périmètre de
membres adultes, actifs, vérifiés et disposant d’un profil complet.

## Disponibilité de l’inscription e-mail

`CheckEmailRegistrationAvailability` centralise le contrôle des quotas Resend
journalier et mensuel via le client HTTP Laravel. Fortify expose uniquement un
booléen à la page `auth/Register` (cache de 60 secondes au maximum) ;
`CreateNewUser` exige un contrôle frais après validation des champs et avant la
transaction. Un refus renvoie une erreur de validation localisée, sans créer de
compte ni déclencher l’envoi de vérification. La page masque le formulaire et
conserve Google ainsi que le lien de connexion. Une erreur ou un état périmé
ferme temporairement cette inscription. Voir `operations.md` pour les clés,
les périodes, la réactivation et les limites de concurrence du contrôle.

## Authentification sociale

Laravel Socialite gère Google nativement. La redirection conserve l'état OAuth
et Google retourne sur un callback `GET` ; la validation de l'état OAuth par
Socialite reste active.

Une identité déjà liée à un compte actif ouvre directement une session. Pour
une nouvelle identité, seuls le fournisseur, son identifiant stable et
l'adresse e-mail déclarée vérifiée sont placés temporairement dans la session
serveur. La personne renseigne ensuite sa date de naissance et une action
transactionnelle crée le compte adulte, lui attribue le rôle `user` et crée le
lien social. Une adresse déjà utilisée n'est jamais liée automatiquement. Les
jetons d'accès, jetons de renouvellement et réponses brutes des fournisseurs ne
sont ni persistés ni journalisés.

La gestion des membres est servie par une Policy dédiée et une requête paginée
de 20 comptes qui calcule uniquement des compteurs. La suppression passe par
une Action transactionnelle, révoque les sessions puis met en file la
notification après la suppression. L’ouverture d’un échange admin/membre
verrouille la paire canonique, réutilise son match et sa conversation s’ils
existent, ou les crée sans ajouter de swipe. Les Policies de profil et l’Action
de blocage interdisent toutes deux de cibler un administrateur.

## Cycle de vie partenaire et concurrence

Les routes Inertia `/partner/profile`, `/partner/announcements` et
`/partner/statistics` sont réservées au rôle `partner`, indépendamment du
parcours social `user`. Les contrôleurs admin de fiches, annonces, paramètres
et statistiques utilisent les mêmes Actions métier et des autorisations serveur.
Les rôles cumulables sont modifiés transactionnellement avec audit ; `admin`
reste hors de ce formulaire. L’entrée authentifiée conserve `/app` pour tout
compte `user`, puis dirige un compte sans `user` vers `/dashboard` s’il est
administrateur ou `/partner/profile` s’il est partenaire.

La navigation basse expose un sélecteur d’espace uniquement lorsque les rôles
`user` et `partner` sont tous deux présents. L’espace actif est dérivé de l’URL :
aucune préférence supplémentaire n’est persistée, et les middlewares de rôles
restent la source de vérité des autorisations.

`SavePartnerProfileDraft` et `TransformPartnerImage` enregistrent le brouillon
et une image réencodée dans le stockage privé. Soumettre fige la révision ;
l’approbation change le pointeur de version publique sans exposer les brouillons.
`PublicLandingController` sélectionne au plus six fiches publiées/approuvées
dans l’ordre manuel et sert leurs images via une route publique contrôlée.

Le bouton administrateur des statistiques appelle `StartPartnerAnnouncement` :
verrouillage du réglage singleton, de la fiche et de l’annonce, validation de
l’état et du délai,
création du run et de la métrique, puis job après commit. La préparation capture
l’audience par lots de 500 (compte actif, vérifié, rôle `user`, hors suppression,
préférence absente ou active), insère les livraisons uniques et déclenche leurs jobs après commit.
Chaque livraison revérifie l’éligibilité sous verrou, écrit notification et
métrique atomiquement, puis diffuse via Reverb après commit. La finalisation
attend la fin de préparation et des livraisons non terminales ; les reprises
conservent les lignes déjà livrées ou ignorées et réessaient les broadcasts manqués.

Le centre de notifications présente l’annonce reçue dans le panneau adaptatif
`useResponsiveModal`. Son ouverture appelle la route authentifiée de lecture
sans redirection ; le contenu reste consultable même après sa disparition du
filtre des non-lues. Seul le lien explicite du panneau utilise la redirection
par jeton opaque et enregistre un clic. Le retrait conserve sa confirmation.
Les compteurs d’engagement sont atomiques ; `PartnerAnnouncementStatisticsData`
ne transmet que les agrégats, avec compteurs opérationnels supplémentaires
pour l’administration. Les taux sont rapportés aux livraisons effectives.
Il n’existe ni API séparée, ni ciblage partenaire, ni envoi e-mail/push.

Les livraisons d'annonces conservent un instantané immuable du contenu public
reçu. Leur clé étrangère vers l'annonce est nullable avec `null on delete` : la
rétention peut supprimer une campagne et ses agrégats sans effacer l'historique
appartenant à un autre membre. Aucun identifiant de compte expéditeur ni secret
opérationnel n'est dupliqué dans cet instantané. La suppression du destinataire
reste la seule opération qui supprime sa livraison identifiable.

Les actions concurrentes respectent l'ordre de verrouillage global `user` →
`partner_setting` → `partner_profile` → `partner_announcement` →
`partner_announcement_delivery` → `partner_announcement_metric`. La livraison
et la désactivation verrouillent donc toutes deux la ligne de livraison avant
la métrique, ce qui évite l'inversion lors d'une suppression simultanée de
l'expéditeur. L'approbation d'une fiche verrouille d'abord son propriétaire et
refuse la publication si le compte a disparu, est inactif ou en suppression.

Le job de projection temps réel appelle le diffuseur pendant sa propre tentative
et ne renseigne `broadcasted_at` qu'après le retour réussi du transport. Une
panne épuise donc les tentatives du job sans confirmer l'émission ni recréer la
notification en base ; la relance administrative republie le même UUID et le
même instantané. Le centre affiche le contenu figé depuis la livraison, rendu
comme texte échappé, jamais comme HTML.

La rétention s'exécute quotidiennement à 03:30 dans `Europe/Paris`, sans
chevauchement et sur un seul serveur. Elle traite les échéances inclusives par
lots de 500 et exclut toujours la révision actuellement publiée d'une fiche
active.

## Accueil public et indexation

La racine `/` sélectionne la langue du visiteur à partir de sa préférence puis
de l’en-tête `Accept-Language`, et redirige vers une URL publique stable :
`/fr` ou `/en`. Les pages publiques indexables regroupent ces landing pages,
les documents légaux localisés et l’explication du matching sous
`/{locale}/matching`. Elles fournissent côté serveur un titre, une description,
une URL canonique, des alternatives `hreflang`, les données Open Graph et un
objet JSON-LD, et sont référencées dans `sitemap.xml`. Leur contenu est rendu
par des vues Blade autonomes qui ne chargent que la feuille de style de
production : aucun runtime Vue/Inertia ni JavaScript applicatif n'est
nécessaire pour les afficher ou les parcourir.

Les pages d’authentification et tous les parcours applicatifs renvoient
`X-Robots-Tag: noindex, nofollow`. La redirection publique de la racine n’envoie
pas cet en-tête. La politique `robots.txt` autorise l’exploration afin que les
robots puissent lire ces directives `noindex` ; elle ne remplace pas l’en-tête
de protection attaché aux réponses privées. Un membre connecté qui ouvre la
racine ou une landing page rejoint immédiatement la route d’aiguillage de son
espace membre.

### Standard des pages publiques indexables

Toute nouvelle page publique destinée au référencement suit par défaut le
modèle de la landing : Laravel rend un document HTML complet dans une vue Blade
autonome et ne charge que les ressources nécessaires à son affichage. Le
runtime Vue/Inertia et le JavaScript applicatif ne sont ajoutés que si une
interaction riche le justifie réellement ; cette exception doit conserver un
contenu principal lisible dans la réponse initiale et faire l’objet d’une
vérification de performance.

Chaque page publique indexable doit respecter le contrat suivant :

- une URL stable et distincte par langue, avec un contenu visible, un attribut
  `lang`, un titre et une description cohérents dans cette langue ;
- une URL canonique, des alternatives `hreflang` réciproques, les métadonnées
  sociales utiles et, lorsqu’elles décrivent fidèlement le contenu visible,
  des données structurées adaptées ;
- un lien HTML crawlable depuis une autre page publique et une entrée dans le
  sitemap ; la détection de la langue à la racine ne remplace jamais ces URL
  localisées explicites ;
- du HTML sémantique, accessible au clavier, responsive et utilisable avec
  `prefers-reduced-motion` ;
- aucun accès à des données privées et aucune inclusion accidentelle d’une
  route d’authentification ou applicative dans le sitemap.

Une navigation Inertia qui aboutit sur l’une de ces vues Blade doit demander
un rechargement complet avec une réponse `Inertia::location`. Sans cette
frontière explicite, Inertia reçoit un document non-Inertia qu’il ne peut pas
remplacer correctement, notamment après la déconnexion.

Les tests vérifient au minimum les variantes française et anglaise, le HTML
rendu côté serveur, les métadonnées, les liens localisés, le sitemap et les
protections `noindex`. Un audit Lighthouse est exécuté sur chaque langue avant
livraison ; le changement ne doit pas dégrader sensiblement les performances et
vise un score maximal en SEO et en accessibilité.

## Mesure d’audience et Search Console

Le composant Blade `analytics-consent` expose la même interface bilingue sur les
documents publics et le shell Inertia lorsque `GOOGLE_ANALYTICS_ID` est
configuré. Avant acceptation, aucun script Google n’est créé. Après acceptation,
le module `analyticsConsent.ts` initialise le Consent Mode v2 basic, charge GA4
et conserve le choix six mois. Les pages Blade laissent alors GA4 enregistrer
la page initiale. Le shell Inertia désactive cet envoi automatique et émet une
page vue au chargement puis à chaque navigation cliente, avec la page précédente
comme référent, afin d’éviter les doublons. Il sait aussi démarrer ce suivi si le
consentement est donné après le montage de l’application.

Avant l’envoi, les paramètres de requête, fragments et segments numériques ou
UUID des URL sont retirés ou remplacés par `{id}`. Aucun événement applicatif
ne doit contenir de nom, d’e-mail, d’identifiant de membre, de texte de profil
ou de message. `GOOGLE_SITE_VERIFICATION` ajoute, lorsqu’elle est configurée,
la balise de validation Search Console aux documents HTML.

## Services Docker

| Service | Responsabilité |
| --- | --- |
| `web` | Application HTTP avec PHP-FPM et Nginx |
| `worker` | Exécution des files Laravel |
| `scheduler` | Exécution des tâches planifiées |
| `reverb` | Serveur WebSocket |
| MySQL Coolify indépendant | Base relationnelle persistante sur un réseau privé externe |
| Redis Coolify indépendant | Cache, sessions et files sur un réseau privé externe authentifié |
| Garage Coolify indépendant | Stockage objet privé compatible S3 |
| `mailpit` | Capture locale des e-mails |

Les quatre services applicatifs utilisent la même image Docker. MySQL et Redis
sont des ressources Coolify indépendantes ; le worker et le scheduler ne publient
aucun port sur l'hôte. `compose.yaml`
décrit la stack locale complète, dont Mailpit. `compose.production.yaml`
décrit la stack applicative Coolify, exclut Mailpit, MySQL, Redis et Garage, et
expose seulement les ports internes de `web` et `reverb` au proxy Coolify.

Le conteneur applicatif ne lance aucune migration au démarrage. Les migrations
s'exécutent explicitement avec `php artisan migrate --force` et doivent rester
compatibles avec la version applicative précédente pendant un déploiement.

La préparation et la validation de la bascule réelle sont décrites dans
[`mysql-externalization.md`](mysql-externalization.md). Le Compose ne gère ni le
cycle de vie de MySQL ni ses identifiants administrateur en production.

## Environnements

| Sujet | Développement | CI | Production |
| --- | --- | --- | --- |
| Base de données | MySQL Docker | MySQL de service pour les suites Pest | MySQL privé |
| Cache et files | Redis Docker | Stockages `array` et files synchrones | Redis privé |
| Fichiers | MinIO via le disque `s3` | Disque local éphémère | Garage privé via le disque `s3` |
| E-mails | Mailpit | Transport `array` | Resend via le mailer `resend` |

Les secrets sont fournis par variables d'environnement et ne sont jamais
intégrés à l'image ou au dépôt.

## Déploiement

`main` est l'unique branche de production. Après les contrôles de pull request,
Coolify construit la cible `runtime` définie par `compose.production.yaml`, puis
réutilise cette image pour `web`, `worker`, `scheduler` et `reverb`. MySQL,
Redis et Garage possèdent leurs volumes dans des ressources Coolify indépendantes.
Coolify gère le réseau propre au stack et son raccordement
à la destination prédéfinie ; le Compose ne déclare aucun réseau personnalisé.
Les quatre processus Laravel utilisent `DB_HOST` et `DB_PORT` ; l’opérateur configure leurs
sauvegardes ainsi que les secrets et domaines HTTPS/WSS dans Coolify. Les
variables critiques utilisent l’interpolation Compose obligatoire et bloquent
une configuration incomplète avant le démarrage. GitHub Actions vérifie
l'application et l'image Docker mais ne déclenche pas directement le
déploiement.

La route `/up` sert de contrôle de santé sans exposer de configuration. Les
services longs possèdent également un healthcheck Docker et des limites de
ressources.

## Contrôle des données personnelles

Les exports JSON sont construits à la demande par `BuildUserDataExport` et
renvoyés directement au membre authentifié. Aucun fichier d’export ni état de
préparation n’est conservé côté serveur.

Une suppression confirmée d’un compte actif inscrit `pending_deletion` et
`deletion_requested_at`, puis révoque sessions et liens sociaux avant
la déconnexion. `PurgeDeletedUser` vérifie l’identité, le statut, l’horodatage
immuable et l’échéance de 30 jours avant de supprimer l’utilisateur ; les clés
étrangères en cascade retirent ses données relationnelles. La commande horaire
`accounts:dispatch-due-purges` rend le traitement récupérable après une perte de
job, tandis que ces gardes rendent les doublons sans effet.

## Principes de conception

La séparation en modèles, Policies, Form Requests, Actions et composants sert
un besoin actuel. Une nouvelle couche doit réduire une complexité mesurable ;
elle ne doit pas anticiper une extension hypothétique. Voir les
[`engineering-principles.md`](engineering-principles.md). Le langage visuel et
les règles de composants sont définis dans le
[`design-system.md`](design-system.md).

## Modération des échanges et révocation serveur

Les Policies, Form Requests et Actions transactionnelles protègent signalement,
consultation/clôture et sanction. `ReportConversation` verrouille les utilisateurs
par ID puis la conversation, recontrôle l’appartenance et réutilise `BlockUser`.
La consultation charge les messages originaux par `(created_at,id)` et produit
un audit sans mutation du curseur de lecture. `SetMemberBan` verrouille acteur
et cible, recontrôle leurs droits, applique `banned`/`active`, révoque les accès
et réutilise annulation, retrait et dépublication.

Le broadcaster Reverb signe une identité minimale `user_id` dans `channel_data`
pour chaque canal privé. `IdentityAwareChannelManager` substitue le canal privé
qui valide signature et activité à l’abonnement et recontrôle l’activité fraîche
à chaque diffusion, y compris `broadcastToAll` utilisé en distribution. La
terminaison signée des connexions intervient après commit ; son échec ne
permet pas de livraison à un destinataire inactif. Événements et notifications
différés recontrôlent aussi les participants. Aucun code vendor n’est modifié ;
les stubs PHPStan corrigent uniquement les annotations du vendor Reverb.

`PrepareMemberDataForDeletion` protège sous verrou les données des comptes bannis
avant les cascades ; la purge de comptes vérifie toujours `pending_deletion`.
La purge partenaire verrouille propriétaires et enregistrements avant de
revérifier leur échéance et exclut les propriétaires bannis. Les graphes
détachés sont fermés par les scopes et Policies.

`EnsureCurrentTermsAccepted` garde les routes privées après les contrôles
d’authentification, vérification et activité. La page d’acceptation et le logout
restent hors de cette garde ; la version et l’heure sont déterminées au serveur
et l’écriture est unique/idempotente sous verrou.

## Affichage de la PWA sur iOS

Le viewport conserve `width=device-width, initial-scale=1` et la barre d’état
utilise le style `default` : iOS réserve sa zone native au lieu de superposer
le contenu de l’application. Le viewport ne doit pas être modifié lors des
événements de reprise (`focus`, `pageshow`, `visibilitychange`). Le précédent
contournement qui alternait `viewport-fit=auto` et `cover` est retiré après le
signalement d’un espace vide permanent en bas et de contenu sous la barre
d’état sur iPhone.

Les tests HTTP vérifient les métadonnées des documents public et Inertia.
Le rendu natif et le retour d’un lien externe restent à contrôler dans une PWA
installée sur iPhone ; Chromium ne reproduit pas la gestion native de ces zones.

Un diagnostic temporaire, activable uniquement en bêta depuis le bouton
« Diagnostic écran (bêta) » des espaces membre et administration, compare les
dimensions `innerHeight`, `documentElement`, `visualViewport`, les unités
`svh`/`dvh`/`lvh`/`vh`, le contenant fixe et la position de la navigation. Il
conserve au maximum 40 mesures en mémoire, y compris aux événements de reprise
et de rotation (immédiatement puis après stabilisation). Aucune mesure n’est
envoyée ni persistée ; le rapport copiable ne contient ni URL, ni compte, ni
contenu de page. Fermer le diagnostic retire les sondes et les écouteurs.

Pour analyser le vide intermittent : démarrer le diagnostic sur la PWA iPhone,
mesurer lorsque le vide apparaît, effectuer paysage puis portrait et copier
le rapport avec une capture de l’état défectueux. Une rotation qui rétablit
l’affichage correspond notamment au symptôme décrit dans
[WebKit 244264](https://bugs.webkit.org/show_bug.cgi?id=244264), mais ne suffit
pas à confirmer la cause sur iOS 27.0.1. Les tests Chromium vérifient uniquement
la collecte, son isolation à la bêta et le suivi des dimensions ; ils ne
reproduisent pas le comportement du conteneur natif iOS.

Les relevés iPhone sur iOS 27.0.1 montrent une hauteur de 682 px pour
`innerHeight`, `visualViewport`, le document, `svh`, `dvh` et le contenant fixe,
puis 812 px après paysage/portrait, à largeur constante de 402 px. Même `lvh`
et `vh` passent de 787 à 812 px : changer seulement d’unité ou recopier
`innerHeight` ne constitue donc pas une correction démontrée. Les insets à
zéro sont compatibles avec les métadonnées actuelles sans `viewport-fit=cover`.

Le bouton bêta « Tester le recalcul » teste une seule hypothèse : un débordement
du document peut-il déclencher la mise à jour du viewport natif ? Sur action
explicite, une sonde absolue invisible dans `body` étend la hauteur défilable à
`100lvh + 1px` pendant une seconde. Le rapport enregistre avant, pendant et
après ; fermer le diagnostic retire immédiatement cette sonde. Aucune
métadonnée ni règle de layout permanente n’est modifiée. Il s’agit d’une
expérience à vérifier sur l’iPhone affecté, pas d’une correction validée.
Les tests Chromium vérifient le débordement temporaire, sa suppression et la
stabilité des métadonnées ; ils ne prouvent pas le recalcul natif sur iOS.

Les pages classiques des espaces membre (événements, notifications, réglages,
etc.) et administration utilisent le défilement du document et une hauteur
minimale `100dvh`, sans enfermer le contenu dans un shell `100svh`. La navigation
reste fixe, avec un espace réservé en fin de contenu et les insets natifs. Les
vues de cartes (découverte et profils), l’assistant de profil et les échanges
conservent un cadre `100dvh` et leur défilement interne. Le mode suit le composant
Inertia courant, y compris lors d’une navigation sans rechargement.

Sur l’iPhone affecté, l’expérience de débordement a fait passer la zone visible
à 788 px alors que `svh` et le contenu restaient à 682 px ; la suppression de la
sonde a ramené la zone visible à 682 px. Ce recalcul temporaire n’est donc pas
utilisé comme correction automatique. Le défilement naturel retire la contrainte
de hauteur de l’application, mais son effet sur la réservation native iOS doit
encore être confirmé sur l’appareil : les tests Chromium couvrent le défilement,
la navigation et le redimensionnement, pas le bug natif WebKit.
