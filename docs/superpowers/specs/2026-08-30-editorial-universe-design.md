# Univers éditorial de DLP Friends — Spécification

## Contexte

DLP Friends possède déjà tous les parcours principaux du MVP et prend en
charge le français et l’anglais, mais sa voix reste fragmentée. Des textes
visibles sont encore écrits directement dans les composants Vue, plusieurs
catalogues emploient des phrases françaises comme clés et les mêmes actions
changent de nom selon les écrans.

Cette évolution donne au produit une voix reconnaissable, chaleureuse et
légèrement évocatrice de l’aventure, sans vocabulaire romantique ni emprunt à
une propriété intellectuelle Disney. Elle harmonise les parcours existants ;
elle ne crée pas de nouveau parcours et ne constitue pas une campagne de
contenu marketing.

## Voix et niveau de langage

L’application tutoie systématiquement la personne qui l’utilise. Cette règle
s’applique aux interfaces membres et administratives, aux parcours de compte,
aux validations, aux confirmations, aux états vides et aux e-mails
transactionnels.

La voix est :

- chaleureuse sans familiarité forcée ;
- adulte, directe et rassurante ;
- légèrement évocatrice de l’aventure et des univers partagés ;
- concise pour rester naturelle à partir de 320 px ;
- explicite quand une action est sensible, irréversible ou indisponible.

Les phrases privilégient la voix active, les verbes concrets et une seule idée
principale. Les points d’exclamation sont réservés aux moments positifs rares.
Les erreurs expliquent d’abord ce qui s’est passé, puis indiquent une action
réaliste pour continuer. Si aucune action immédiate n’est possible, elles le
disent sans promettre une résolution.

La nature strictement amicale du service est explicitée aux moments où elle
oriente la compréhension : accueil, inscription, tutoriel et découverte. Le
mot « amical » n’est pas ajouté mécaniquement à chaque rencontre ou échange.

## Vocabulaire canonique

| Concept produit | Français retenu | Usage |
| --- | --- | --- |
| Espace de découverte | **Explorer** | Navigation, titre et états associés |
| Décision négative | **Passer** | Bouton, aide et tutoriel |
| Décision positive | **Découvrir** | Bouton, aide et tutoriel |
| Match réciproque | **Univers croisés** | Nom fonctionnel dans le tutoriel et les aides |
| Annonce du match | **Vos univers se croisent** | Titre du dialogue de réciprocité |
| Conversation privée | **Échange** | Navigation, listes, états vides et actions |
| Profil d’un membre | **Profil** | Partout |
| Centres d’intérêt | **Univers favoris** | Sélection et présentation membre |

La confirmation d’un match suit ce modèle : « Vos univers se croisent », puis
« :name souhaite aussi te découvrir. Tu peux maintenant commencer
l’échange. » Les libellés accessibles emploient le même verbe que le contrôle
visible et ajoutent seulement le contexte dynamique nécessaire, par exemple
« Découvrir le profil de :name ».

Le code peut conserver les termes métier internes `like`, `pass` et `match`
lorsque les renommer n’apporte aucun bénéfice au comportement. Ils ne doivent
plus apparaître dans les textes visibles.

## Lexique d’ambiance

Les mots suivants peuvent soutenir la voix, avec modération et sans les
accumuler dans une même phrase :

- aventure ;
- découvrir et découverte ;
- explorer et exploration ;
- parcours ;
- univers ;
- se croiser ;
- partager ;
- curiosité ;
- étincelle ;
- magie.

« Étincelle » et « magie » désignent uniquement une ambiance ou un moment
positif du produit. Ils ne qualifient jamais une relation entre deux membres.

## Formulations interdites

Les textes du produit n’emploient aucun vocabulaire qui présente DLP Friends
comme un service romantique. Sont notamment interdits, avec leurs variantes de
genre, nombre et casse :

- âme sœur ;
- coup de cœur ;
- alchimie ;
- séduire et séduction ;
- craquer pour ;
- partenaire idéal ;
- relation amoureuse ;
- rendez-vous amoureux ;
- compatibilité amoureuse ;
- flirt et flirter ;
- dating.

Les formulations pouvant suggérer une romance, comme « complicité »,
« connexion spéciale » ou « le courant passe », sont évitées dans les
parcours de découverte et de mise en relation même si elles ne sont pas
interdites dans tout contexte.

Aucun slogan, personnage, attraction, citation ou formulation distinctive de
Disney ou Disneyland Paris n’est repris. Sont notamment proscrites comme
accroches de marque les formulations « il était une fois », « royaume
enchanté » et « rêve devenu réalité ». Les noms Disneyland Paris et Disney ne
sont employés que lorsque le contexte factuel du produit l’exige, notamment
pour expliquer la cible du service et son absence d’affiliation.

## Architecture des traductions

Les traductions sont organisées par feature métier, sans séparation entre
frontend et backend :

```text
lang/fr/
├── common.php
├── account.php
├── profile.php
├── onboarding.php
├── discovery.php
├── conversations.php
├── blocking.php
└── administration.php
```

`lang/en/` expose exactement les mêmes fichiers et les mêmes clés. Une
traduction appartient au domaine qui porte son sens, qu’elle soit utilisée par
Vue, un contrôleur, une validation ou un e-mail. `common.php` est réservé aux
actions, états et libellés réellement partagés par plusieurs features.

`App\Support\FrontendTranslations` transmet à Inertia les catalogues métier
nécessaires au client. Le type TypeScript des clés accepte les chemins
imbriqués, par exemple `discovery.actions.discover` et
`profile.interests.title`. Laravel utilise ces mêmes clés côté serveur.

L’ancien catalogue `frontend.php`, sa section `copy` et la traduction du DOM à
partir de phrases françaises sont supprimés une fois la migration terminée.
Les catalogues Laravel fournis par le framework ne sont conservés que pour les
contrats techniques qui les exigent ; leurs textes visibles suivent la même
voix.

## Centralisation exhaustive

Aucune chaîne visible par une personne n’est codée en dur dans le code
applicatif. Cette règle inclut :

- le nom de marque et les titres de document ;
- les boutons, liens, menus, onglets et aides ;
- les placeholders et textes alternatifs ;
- les noms accessibles, textes réservés aux lecteurs d’écran et infobulles ;
- les états vides, chargements et indisponibilités ;
- les confirmations, toasts, validations et erreurs ;
- les sujets et contenus des e-mails ;
- les textes génériques des primitives d’interface.

Les données fournies par les membres ou l’administration, les symboles sans
signification linguistique, les identifiants techniques et les formats ne sont
pas des chaînes éditoriales. Ils restent dynamiques ou techniques, mais ne
constituent jamais une exception permettant d’introduire un libellé visible.

## Migration des parcours MVP

La réécriture couvre l’accueil, l’inscription, l’authentification, la
vérification, la récupération de compte, la création et la modification du
profil, le tutoriel, l’exploration, les univers croisés, les échanges, le
blocage, les réglages, la sécurité du compte et l’administration.

Chaque parcours conserve son comportement. Les changements de tests
n’actualisent que les textes attendus, sauf lorsqu’un contrôle automatisé est
ajouté pour rendre la politique éditoriale observable.

L’anglais reste fonctionnel et naturel. L’objectif n’est pas une nouvelle
traduction intégrale vers d’autres langues, mais le maintien de la parité de la
langue déjà prise en charge pendant la réorganisation.

## Contrôles automatisés

Des tests Pest garantissent :

1. la présence des mêmes fichiers et des mêmes clés en français et en anglais ;
2. l’absence de traduction vide ;
3. l’absence du catalogue historique `copy` ;
4. l’absence de chaîne visible codée en dur dans les fichiers applicatifs Vue,
   TypeScript, PHP et Blade concernés ;
5. l’absence des termes interdits dans les valeurs des catalogues, avec une
   exception ciblée pour la mention factuelle et légitime de Disney ou
   Disneyland Paris ;
6. l’usage cohérent des noms canoniques dans les parcours de découverte et le
   tutoriel.

Le contrôle des chaînes codées en dur ne possède pas de liste blanche de
libellés visibles. Son analyse distingue les valeurs techniques et les
contenus dynamiques des textes rendus à l’écran.

## Vérification manuelle

La relecture finale parcourt le MVP à une largeur mobile de référence de
390 px, en français, puis vérifie les parcours de localisation anglais déjà
couverts. Elle contrôle au minimum :

- la compréhension immédiate des actions « Passer » et « Découvrir » ;
- le dialogue « Vos univers se croisent » ;
- l’absence de texte tronqué ou de retour à la ligne gênant ;
- les états vides, confirmations et erreurs atteignables ;
- les libellés accessibles des actions représentées par une icône ;
- la cohérence du tutoiement et du vocabulaire canonique.

Les suites de lint, analyse statique, types, build et tests concernés sont
exécutées avant de déclarer le chantier terminé.

## Hors périmètre

- créer un nouveau nom ou une nouvelle identité de marque ;
- modifier les règles métier de découverte ou de messagerie ;
- ajouter une langue ;
- écrire des contenus marketing longs ;
- renommer systématiquement les classes, colonnes ou événements internes
  `like`, `pass` et `match`.
