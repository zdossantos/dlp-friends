# Pages légales et acceptation des CGU — Conception

## Objectif

Livrer les issues #43 et #42 avec deux documents publics distincts, localisés
et conformes au fonctionnement réel de DLP Friends : une politique de
confidentialité et des conditions générales d’utilisation (CGU). L’inscription
exige une acceptation explicite et non précochée des CGU, dont la preuve est
conservée par version.

Les textes produits constituent un projet opérationnel fondé sur le dépôt et
les choix d’exploitation documentés. Ils doivent être relus et validés par une
personne juridiquement compétente avant leur mise en production. L’application
ne présente jamais cette validation comme acquise.

## Identité de l’éditeur et contact

DLP Friends est édité par Zacharie Dos Santos, entrepreneur individuel :

- raison sociale : DOS SANTOS ZACHARIE ;
- SIREN : 104 531 819 ;
- siège : 28 rue Ernest Petit, 21000 Dijon, France ;
- activité : programmation informatique, code APE 6201Z.

Zacharie Dos Santos est également le responsable du traitement. L’adresse
électronique utilisée pour les contacts juridiques et l’exercice des droits
provient de `LEGAL_CONTACT_EMAIL`. `.env.example` documente cette variable
sans publier d’adresse réelle. La configuration de production doit fournir une
adresse valide avant la mise en ligne.

## Documents publics

Les pages suivent le standard des pages publiques indexables décrit dans
`docs/technical-architecture.md`. Laravel rend du HTML Blade autonome, sans
runtime Vue/Inertia, à des URL localisées et stables :

- `/fr/conditions-generales-utilisation` et `/en/terms-of-use` ;
- `/fr/politique-confidentialite` et `/en/privacy-policy`.

Chaque document fournit un attribut `lang`, un titre et une description
localisés, une URL canonique, des alternatives `hreflang`, des métadonnées
sociales et une date visible de dernière mise à jour. Les pages se lient entre
elles et permettent de rejoindre l’accueil localisé. Elles utilisent du HTML
sémantique, des titres hiérarchisés, un sommaire navigable au clavier, des
styles responsive et une feuille d’impression qui retire la navigation et les
décorations.

Les quatre URL figurent dans `sitemap.xml`, `robots.txt` les autorise et les
landing pages française et anglaise comportent des liens HTML permanents vers
les deux documents. Les zones applicatives disposant déjà d’un emplacement de
navigation secondaire réutilisable exposent les mêmes liens. L’inscription
affiche directement les liens dans le libellé d’acceptation.

Les textes visibles sont stockés dans les catalogues Laravel `lang/fr/legal.php`
et `lang/en/legal.php`. Blade et Vue n’introduisent aucun texte visible en dur.

## Politique de confidentialité

La politique décrit uniquement les traitements livrés ou les choix
d’exploitation explicitement documentés :

- données de compte et de sécurité : adresse électronique, mot de passe haché,
  date de naissance, préférence de langue, vérification d’adresse, sessions,
  adresse IP et agent utilisateur de session, passkeys et second facteur
  lorsqu’ils sont activés ;
- données de profil : nom d’affichage, biographie, fréquence de visite,
  visibilité, avatar et intérêts ;
- données sociales : swipes, matchs, blocages, conversations, messages et état
  de lecture ;
- données techniques nécessaires à l’exploitation, à la sécurité, au cache,
  aux files et au temps réel.

Les finalités et bases retenues dans le projet de texte sont :

- exécution des CGU pour créer le compte, fournir le profil, la découverte, le
  matching et la messagerie ;
- exécution des CGU et intérêt légitime pour vérifier que le service reste
  réservé aux personnes majeures ; obligation légale pour répondre aux droits
  et demandes des autorités compétentes ;
- intérêt légitime pour sécuriser le service, prévenir les abus, maintenir les
  sessions, diagnostiquer les incidents et assurer la continuité ;
- consentement uniquement lorsqu’un futur traitement le requiert ; aucun
  traitement optionnel de cette nature n’est actuellement livré.

Les données applicatives sont conservées pendant la vie du compte. Le code
actuel supprime immédiatement le compte et les données liées après confirmation
du membre ; il ne prétend pas livrer la purge différée planifiée. Les
sauvegardes quotidiennes documentées de MySQL et du stockage objet privé sont
conservées au maximum 30 jours et ne sont réutilisées qu’en cas de reprise
après incident. Les jetons de réinitialisation et sessions suivent leur durée
technique. Les journaux applicatifs quotidiens suivent la durée configurée par
`LOG_DAILY_DAYS`, à 14 jours par défaut ; aucun mot de passe, jeton, message
privé ou donnée personnelle inutile ne doit y être écrit.

La politique identifie IONOS comme hébergeur du VPS. MySQL, Redis, Reverb et le
stockage compatible S3 sont auto-hébergés dans l’infrastructure prévue et ne
sont pas présentés comme des destinataires indépendants. Mailpit est strictement
local. Le fournisseur SMTP de production n’étant pas choisi, le texte indique
qu’il sera ajouté à la politique avant son activation. Aucun analytics, réseau
publicitaire, paiement, Google/Apple OAuth ou outil de modération tiers n’est
déclaré comme actif.

La politique explique les droits d’accès, rectification, effacement,
opposition, limitation et portabilité lorsqu’ils s’appliquent, le droit de
réclamation auprès de la CNIL, et l’exercice de ces droits via
`LEGAL_CONTACT_EMAIL`. Elle distingue clairement les fonctions déjà livrées
des capacités d’export et de purge différée encore planifiées.

## Conditions générales d’utilisation

Les CGU couvrent l’objet strictement amical et indépendant de DLP Friends, la
gratuité actuelle du service, l’accès réservé aux personnes majeures, la
création et la sécurité du compte, la vérification de l’adresse électronique,
les obligations des membres, les contenus et comportements interdits, les
règles de blocage, la suppression du compte, la disponibilité du service et la
répartition des responsabilités.

Elles n’inventent ni console de signalement ni équipe de modération. Elles
expliquent que le blocage est le mécanisme disponible et que l’éditeur peut
prendre les mesures nécessaires sur un compte ou un contenu porté à sa
connaissance, dans les limites des outils réellement disponibles. Une
suspension prévue par les CGU reste une faculté juridique de l’éditeur et
n’implique pas l’ajout d’une console d’administration dans cette livraison.

La version initiale des CGU est `2026-09-01` et sa date d’entrée en vigueur est
le 1er septembre 2026. Ces valeurs vivent dans une configuration PHP dédiée,
pas dans la base de données. Une nouvelle version ne modifie jamais les preuves
existantes.

## Acceptation à l’inscription

La page d’inscription ajoute une case Reka UI nommée `terms_accepted`, vide à
chaque affichage. Son libellé localisé contient des liens ouvrables vers les
CGU et la politique de confidentialité dans la langue active. Le bouton reste
utilisable afin que la validation serveur demeure l’autorité ; une soumission
sans acceptation affiche une erreur localisée et ne crée aucun compte.

`CreateNewUser` valide `terms_accepted` avec la règle Laravel `accepted`. Dans
la transaction qui crée l’utilisateur et lui attribue son rôle, l’action crée
aussi une ligne immuable dans `terms_acceptances` contenant :

- `user_id`, avec suppression en cascade pour respecter la suppression du
  compte et la minimisation des données ;
- `terms_version`, chaîne égale à la version configurée au moment de
  l’inscription ;
- `accepted_at`, horodatage explicite de l’acceptation.

L’unicité de `(user_id, terms_version)` empêche les doublons. La table n’a pas
de colonne de mise à jour, car une preuve ne doit jamais être modifiée. Le
modèle `TermsAcceptance` appartient à `User`, qui expose une relation
`termsAcceptances()`.

La livraison ne bloque pas les comptes existants et ne demande aucune
réacceptation après l’inscription. Le stockage par version préserve
l’historique nécessaire à une évolution future, mais aucun middleware, écran
ou mécanisme de réacceptation n’est ajouté sans nouvelle décision produit.

## Erreurs et confidentialité

Une configuration absente de `LEGAL_CONTACT_EMAIL` n’empêche pas les tests ou
le développement local, mais les pages rendent une erreur serveur explicite en
production afin d’éviter de publier un document sans moyen d’exercer ses
droits. La configuration fournit les métadonnées légales et les versions ; les
secrets restent dans l’environnement.

La validation de l’inscription renvoie uniquement les erreurs de formulaire
usuelles. Aucun détail de preuve d’acceptation n’est envoyé au navigateur après
création. L’adresse IP n’est pas ajoutée à la preuve, car l’issue ne l’exige pas
et la minimisation prévaut.

## Tests et vérification

Le développement suit le cycle rouge, vert, refactorisation.

Les tests Pest couvrent :

- les quatre pages publiques, leur locale, contenu principal, dates, liens,
  métadonnées, accessibilité structurelle et comportement d’impression ;
- les URLs légales dans le sitemap, `robots.txt` et les landing pages ;
- l’échec d’une inscription sans acceptation ou avec une valeur invalide ;
- la création atomique d’un utilisateur et de sa preuve avec la version et
  l’horodatage attendus ;
- la conservation indépendante des preuves de versions différentes et
  l’impossibilité de modifier rétroactivement une preuve ;
- la suppression en cascade avec le compte ;
- la parité des catalogues français et anglais.

Le test navigateur vérifie la case initialement vide, les deux liens, le retour
clavier, l’erreur visible sans acceptation, l’inscription réussie après
acceptation, le rendu mobile et les liens légaux permanents. Le projet ne
possède plus de suite Vitest Vue ; ce comportement observable reste donc couvert
par Pest Browser conformément à l’architecture actuelle.

Avant livraison, les contrôles ciblés sont suivis de `composer test`, des
contrôles frontend pertinents, du build et d’une inspection responsive et
imprimable. Les critères exigeant une validation juridique et la confirmation
des opérateurs de sauvegarde, SMTP et hébergement restent explicitement à
valider avant production.

## Hors périmètre

- rédaction assimilée à un conseil juridique ou validation juridique
  automatisée ;
- interface d’administration des documents légaux ;
- réacceptation forcée pour les comptes existants ;
- middleware bloquant après une nouvelle version ;
- bannière de consentement aux cookies, analytics ou publicité non utilisés ;
- ajout d’un workflow de signalement ou d’une console de modération ;
- choix ou intégration du fournisseur SMTP de production ;
- mise en œuvre de l’export des données ou de la purge différée sous 30 jours.
