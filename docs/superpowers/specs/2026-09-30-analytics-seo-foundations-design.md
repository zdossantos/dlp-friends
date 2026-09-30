# Mesure GA4 et socle SEO public — Design

## Contexte et résultat attendu

DLP Friends doit retrouver une mesure GA4 exploitable après la régression
observée en production, distinguer les usages depuis la PWA installée de ceux
du navigateur et empêcher toute donnée personnelle d'apparaître dans les
rapports. Les titres d'onglet visibles dans l'application restent inchangés :
une conversation peut continuer à afficher le nom de l'interlocuteur, mais
GA4 reçoit uniquement une désignation générique.

Le site public doit aussi devenir découvrable sur des recherches génériques
liées aux rencontres amicales entre fans adultes de Disneyland Paris. Le
travail ne promet pas une position donnée dans Google : il rend les pages
techniquement indexables, corrige les signaux invalides, publie du contenu
original répondant à des intentions de recherche réelles et fournit les
mesures nécessaires pour améliorer ensuite le référencement à partir des
données Search Console.

Le nettoyage demandé des worktrees secondaires est une opération locale déjà
terminée. Il ne fait pas partie des modifications applicatives de ce design.

## Principes

- Ne jamais envoyer à GA4 un nom d'affichage, une adresse e-mail, un UUID, un
  identifiant numérique, un message, une bio ou une valeur issue d'un contenu
  membre.
- Séparer strictement le titre visible du document et le titre analytique.
- Construire la taxonomie analytique depuis des routes et types de pages
  connus, jamais depuis `document.title`.
- Conserver le Consent Mode basic : aucun script ni événement Google avant
  consentement explicite.
- Rendre les contenus SEO côté serveur, avec des liens HTML crawlables et sans
  dépendance au runtime Vue/Inertia.
- Écrire pour les personnes avant les moteurs : pas de bourrage de mots-clés,
  de pages quasi dupliquées, de texte généré en masse ou de promesse produit
  absente de l'application.
- Conserver le positionnement strictement amical, réservé aux adultes, et la
  mention d'indépendance vis-à-vis de Disney et Disneyland Paris.

## Diagnostic observé

En production, l'identifiant GA4 est présent et `gtag.js` se charge après
consentement. Le parcours contrôlé n'a toutefois pas exposé de requête de
collecte de page vue. L'implémentation publique dépend actuellement de la page
vue automatique de `gtag`, alors que le shell Inertia envoie ses propres
événements. Cette divergence complique le diagnostic et la cohérence des
paramètres.

Les chemins Inertia sont déjà normalisés, mais les événements n'envoient pas
de `page_title`. GA4 complète alors l'événement avec le titre réel du document,
qui contient le nom d'affichage sur les conversations et profils. La capture
du rapport montre ainsi plusieurs lignes par membre.

Le HTML de production expose des canoniques, des alternatives `hreflang`, un
sitemap et une validation Search Console. En revanche, la clé JSON-LD
`@context` est interprétée comme une directive Blade et produit du code PHP
dans le script structuré. Une recherche exploratoire ne fait pas ressortir le
domaine sur les requêtes génériques visées. Le site ne propose actuellement
que la landing, l'explication du matching et les documents légaux comme
contenus publics indexables.

## Taxonomie analytique sans donnée personnelle

Un module pur centralise la résolution d'une page analytique à partir du chemin
normalisé et de la locale. Il renvoie au minimum :

- `page_type`, identifiant stable et non traduit utilisé dans les explorations ;
- `page_title`, libellé générique localisé destiné au rapport Pages et écrans ;
- `page_path`, chemin sans requête, fragment ni identifiant réel ;
- `page_location`, origine suivie du chemin normalisé ;
- `app_mode`, égal à `pwa` ou `browser`.

La taxonomie couvre explicitement les routes dynamiques sensibles :

| Surface | `page_type` | Titre analytique français |
| --- | --- | --- |
| Liste des échanges | `conversation_list` | Mes échanges — DLP Friends |
| Conversation | `conversation_detail` | Conversation — DLP Friends |
| Profil d'un membre | `member_profile` | Profil membre — DLP Friends |
| Profil personnel | `own_profile` | Mon profil — DLP Friends |
| Participant d'un événement | `event_participant` | Participant — DLP Friends |
| Événement | `event_detail` | Événement — DLP Friends |

Les autres routes stables reçoivent un `page_type` explicite ou dérivé d'une
liste blanche. Une route inconnue retombe sur `application_page` avec le titre
« DLP Friends », jamais sur le titre du document. Les équivalents anglais sont
traduits dans les catalogues existants, tandis que `page_type` reste identique
dans les deux langues.

Cette taxonomie n'altère aucun composant `<Head>`, aucun `document.title` et
aucun libellé visible. Elle ne sert qu'au payload envoyé à Google.

## Distinction PWA et navigateur

`app_mode` est déterminé au moment de chaque page vue par les capacités déjà
utilisées par la PWA : media query `(display-mode: standalone)` ou
`navigator.standalone === true` pour iOS. Tout autre contexte vaut `browser`.
Le code ne déduit pas le mode depuis le user-agent.

Le paramètre accompagne chaque `page_view`, y compris la première vue, les
navigations Inertia et les documents publics. La documentation d'exploitation
demande ensuite de déclarer dans GA4 les dimensions personnalisées de portée
événement `app_mode` et `page_type`. Les valeurs sont collectées dès le
déploiement du code ; GA4 ne rend les dimensions disponibles dans les rapports
qu'après leur déclaration et ne retraitera pas l'historique antérieur.

## Cycle d'envoi GA4

Toutes les surfaces utilisent une page vue explicite. La configuration GA4
désactive `send_page_view` aussi bien sur les documents Blade que dans le shell
Inertia, puis un émetteur commun construit le payload assaini :

1. aucun chargement ni envoi avant consentement ;
2. après acceptation ou au chargement avec un consentement conservé, initialiser
   le tag et envoyer exactement une vue initiale ;
3. sur Inertia, envoyer exactement une vue supplémentaire par navigation
   réussie, avec la page précédente normalisée comme référent ;
4. sur un document Blade, ne pas installer d'écouteur Inertia ;
5. lors d'un retrait, supprimer les cookies accessibles et recharger comme
   aujourd'hui afin d'arrêter les envois suivants.

Les vues Blade exposent uniquement un identifiant de page analytique et la
locale via des attributs `data-*`; elles n'exposent aucun contenu utilisateur.
Le shell Inertia résout la taxonomie depuis l'URL de la page. Le titre
analytique est passé explicitement dans l'événement et ne peut donc plus être
complété depuis le titre personnalisé de l'onglet.

## Pages publiques et intentions de recherche

La landing française cible clairement la proposition centrale : trouver et
rencontrer amicalement d'autres fans adultes de Disneyland Paris. Sa hiérarchie
de titres et son texte d'introduction utilisent naturellement les formulations
que le public emploie, tout en conservant la marque DLP Friends et le ton du
produit.

Deux familles de pages éditoriales bilingues sont ajoutées. Elles répondent à
des intentions différentes et ne répètent pas la landing :

1. **Rencontres amicales entre fans de Disneyland Paris**
   (`/fr/rencontres-amicales-disneyland-paris` et
   `/en/disneyland-paris-friendships`) explique à qui s'adresse le service,
   comment les passions communes, la réciprocité, la confidentialité et les
   événements amicaux fonctionnent, puis oriente vers l'inscription.
2. **Aller seul à Disneyland Paris et rencontrer d'autres fans**
   (`/fr/aller-seul-disneyland-paris` et
   `/en/visiting-disneyland-paris-solo`) répond à la situation d'une personne
   adulte qui ne connaît pas encore d'accompagnant, propose des conseils
   prudents pour préparer une rencontre amicale et explique comment DLP Friends
   aide sans garantir une rencontre ni se substituer aux règles officielles du
   parc.

Chaque page contient un contenu original, substantiel et maintenable, un seul
`h1`, des sections descriptives, un appel à l'action, la mention 18+ et la
mention d'indépendance. Aucun personnage, logo ou illustration Disney n'est
ajouté. Les pages françaises et anglaises sont des adaptations éditoriales,
pas des duplications automatiques mot à mot.

Le périmètre initial se limite à ces deux familles. Les futures pages seront
décidées à partir des requêtes et impressions Search Console afin d'éviter un
catalogue de contenus minces.

## Architecture publique et maillage

Les nouvelles pages suivent le standard SSR existant : contrôleur Laravel,
contenu localisé dans les catalogues, vue Blade publique partagée et aucune
donnée privée. `PublicUrls` fournit les URL absolues et chemins localisés.

La landing lie les deux guides avec des ancres descriptives. Chaque guide lie
la landing, l'autre guide lorsque le contexte le justifie, l'explication du
matching et les pages légales pertinentes. Le pied de page public rend ces
liens accessibles sans JavaScript. Les deux variantes de chaque famille sont
ajoutées au sitemap avec leurs alternatives `hreflang` réciproques et
`x-default` français.

Les titres, descriptions et canoniques sont uniques. La racine continue à
rediriger vers `/fr` ou `/en`; les URL localisées restent les seules URL
indexables. Les routes privées et d'authentification conservent
`X-Robots-Tag: noindex, nofollow`.

## Données structurées

La génération JSON-LD est déplacée vers une sérialisation qui ne laisse jamais
Blade interpréter les clés commençant par `@`. Le document produit doit être du
JSON valide après extraction du HTML.

La landing décrit fidèlement l'application avec `WebApplication`. Les guides
utilisent `WebPage` et un `BreadcrumbList` limité aux liens réellement visibles.
Le balisage ne revendique ni avis, ni note, ni affiliation, ni fonctionnalité
absente. Les tests valident le JSON décodé et ses URL plutôt que de rechercher
seulement la présence d'une balise `<script>`.

## Performance et accessibilité

Les pages restent rendues côté serveur et ne chargent que la feuille de style,
les polices nécessaires et le petit module de consentement analytique lorsque
GA4 est configuré. Aucun framework frontend n'est ajouté aux guides. Les images
éventuelles sont des actifs DLP Friends locaux avec dimensions explicites,
texte alternatif utile et formats optimisés.

La structure sémantique, la navigation au clavier, le contraste, le zoom à
200 %, la réduction des mouvements et les écrans mobiles à partir de 320 px
suivent le design system existant. Les audits Lighthouse français et anglais
ne doivent pas introduire de régression significative en SEO, accessibilité,
bonnes pratiques ou performance.

## Search Console et exploitation

La documentation d'exploitation décrit une vérification post-déploiement :

- tester les URL avec l'inspection en direct et contrôler le HTML rendu ;
- valider les données structurées ;
- resoumettre le sitemap après publication ;
- demander l'indexation de la landing et des deux nouveaux contenus français
  et anglais ;
- suivre chaque semaine, puis mensuellement, l'indexation, les impressions, les
  clics, le CTR, la position et les requêtes par page ;
- distinguer un problème d'exploration, d'indexation et de classement avant de
  modifier le contenu.

La configuration GA4 documente aussi la création des dimensions `app_mode` et
`page_type`, puis la construction d'une exploration comparant PWA et navigateur.
Aucune automatisation ne modifie les propriétés Google externes depuis le code.

## Tests et critères d'acceptation

Les tests frontend couvrent :

- la taxonomie de toutes les routes dynamiques sensibles ;
- l'absence de nom, identifiant, requête et fragment dans les payloads ;
- des titres d'onglet personnalisés inchangés face à un `page_title` GA4
  générique ;
- la détection `pwa` sur Chromium et iOS, et `browser` autrement ;
- une seule vue initiale puis une seule vue par navigation Inertia ;
- l'absence complète de chargement ou d'événement sans consentement.

Les tests Laravel couvrent :

- le HTML SSR, le contenu et les métadonnées uniques des six URL éditoriales ;
- les canoniques et `hreflang` réciproques ;
- le maillage public et les entrées du sitemap ;
- le maintien du `noindex` sur les routes privées ;
- le décodage réussi de chaque bloc JSON-LD et l'absence de code PHP rendu ;
- les attributs analytiques publics sans donnée personnelle.

La vérification manuelle de production confirme enfin, dans GA4 DebugView ou
le flux réseau autorisé : une page vue par chargement/navigation, des titres
génériques pour conversations et profils, des chemins normalisés, puis les deux
valeurs `pwa` et `browser`. Search Console doit accepter le sitemap et les tests
en direct des nouvelles URL, sans que cela soit présenté comme une garantie de
classement.

## Références de conception

- Google Search Central, *SEO Starter Guide* :
  <https://developers.google.com/search/docs/fundamentals/seo-starter-guide>
- Google Search Central, *Creating helpful, reliable, people-first content* :
  <https://developers.google.com/search/docs/fundamentals/creating-helpful-content>
- Google Search Central, *Build and submit a sitemap* :
  <https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap>
- Google Analytics, *Event parameters* :
  <https://support.google.com/analytics/answer/13675006>
