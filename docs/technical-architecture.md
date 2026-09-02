# Architecture technique

Ce document décrit l'architecture actuelle. Les fonctions prévues mais non
livrées restent décrites dans le [`PRD.md`](PRD.md).

## Surface légale publique

Les quatre routes légales localisées sont rendues en Blade sans JavaScript,
avec canonical, hreflang et sitemap. `PublicUrls` fournit leurs URL aux pages
publiques et aux layouts Inertia. `terms_acceptances` enregistre de façon
immuable `user_id`, `terms_version` et `accepted_at` dans la transaction de
création du compte ; la suppression du compte supprime aussi cette preuve.

## Stack applicative

- Laravel 13 sur PHP 8.4 ;
- Inertia 3, Vue 3 Composition API et TypeScript ;
- Tailwind CSS et composants accessibles fondés sur Reka UI ;
- Bun 1.3.14 pour les dépendances et commandes frontend ;
- MySQL 8.4 pour les données relationnelles ;
- Redis pour le cache, les sessions et les files ;
- Laravel Reverb et Echo pour le temps réel ;
- Pest, Pest Browser, Playwright, PHPStan/Larastan, Pint, ESLint et Prettier
  pour la qualité.

Les versions exactes sont verrouillées dans `composer.lock` et `bun.lock`. Bun
1.3.14 est épinglé dans `package.json`, GitHub Actions et le build Docker.

## Frontière applicative

Laravel porte l'authentification, les autorisations et les règles métier.
Vue/Inertia affiche les pages servies par Laravel ; aucune API distincte n'est
nécessaire actuellement. Toute action sensible doit être protégée côté serveur,
de préférence avec une Policy Laravel.

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
tutoriel. Les validations imposent des avatars actifs et distincts ; les
actions d’archivage et de suppression les protègent également côté serveur.
L’écran admin calcule ses agrégats et sa liste paginée sur le même périmètre de
membres adultes, actifs, vérifiés et disposant d’un profil complet.

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
`X-Robots-Tag: noindex, nofollow`. La politique `robots.txt` autorise les deux
landing pages et indique le sitemap ; elle ne remplace pas l’en-tête de
protection attaché aux réponses privées. Un membre connecté qui ouvre la
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

## Services Docker

| Service | Responsabilité |
| --- | --- |
| `web` | Application HTTP avec PHP-FPM et Nginx |
| `worker` | Exécution des files Laravel |
| `scheduler` | Exécution des tâches planifiées |
| `reverb` | Serveur WebSocket |
| `mysql` | Base relationnelle persistante |
| `redis` | Cache, sessions et files sur le réseau privé |
| `minio` | Stockage objet compatible S3 |
| `mailpit` | Capture locale des e-mails |

Les quatre services applicatifs utilisent la même image Docker. MySQL, Redis,
le worker et le scheduler ne publient aucun port sur l'hôte. `compose.yaml`
décrit la stack locale complète, dont Mailpit. `compose.production.yaml`
décrit la stack Coolify, exclut Mailpit, garde MySQL, Redis et MinIO privés et
expose seulement les ports internes de `web` et `reverb` au proxy Coolify.

Le conteneur applicatif ne lance aucune migration au démarrage. Les migrations
s'exécutent explicitement avec `php artisan migrate --force` et doivent rester
compatibles avec la version applicative précédente pendant un déploiement.

## Environnements

| Sujet | Développement | CI | Production |
| --- | --- | --- | --- |
| Base de données | MySQL Docker | MySQL de service pour les suites Pest | MySQL privé |
| Cache et files | Redis Docker | Stockages `array` et files synchrones | Redis privé |
| Fichiers | Disque local par défaut | Disque local éphémère | MinIO privé via le disque `s3` |
| E-mails | Mailpit | Transport `array` | Resend via le mailer `resend` |

Les secrets sont fournis par variables d'environnement et ne sont jamais
intégrés à l'image ou au dépôt.

## Déploiement

`main` est l'unique branche de production. Après les contrôles de pull request,
Coolify construit la cible `runtime` définie par `compose.production.yaml`, puis
réutilise cette image pour `web`, `worker`, `scheduler` et `reverb`. Compose
déclare les volumes persistants MySQL et MinIO ; l’opérateur configure leurs
sauvegardes ainsi que les secrets et domaines HTTPS/WSS dans Coolify. Les
variables critiques utilisent l’interpolation Compose obligatoire et bloquent
une configuration incomplète avant le démarrage. GitHub Actions vérifie
l'application et l'image Docker mais ne déclenche pas directement le
déploiement.

La route `/up` sert de contrôle de santé sans exposer de configuration. Les
services longs possèdent également un healthcheck Docker et des limites de
ressources.

## Principes de conception

La séparation en modèles, Policies, Form Requests, Actions et composants sert
un besoin actuel. Une nouvelle couche doit réduire une complexité mesurable ;
elle ne doit pas anticiper une extension hypothétique. Voir les
[`engineering-principles.md`](engineering-principles.md). Le langage visuel et
les règles de composants sont définis dans le
[`design-system.md`](design-system.md).
