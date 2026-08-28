# Onboarding produit obligatoire intégré à l’inscription

## Objectif

Transformer le tutoriel produit actuel en dernière phase obligatoire de
l’inscription. Le membre configure d’abord son profil, puis apprend à passer un
profil, manifester son intérêt, comprendre un match et démarrer une discussion
avant d’accéder à l’espace membre.

Le parcours doit employer les composants et l’apparence de l’application
réelle sans créer de swipe, match, conversation ou message social. Il remplace
les écrans de démonstration spécifiques qui divergent aujourd’hui des écrans
réels.

## Parcours utilisateur

L’inscription complète est présentée comme un parcours linéaire de huit étapes :

1. Avatar ;
2. Identité ;
3. Affinités ;
4. Aperçu ;
5. Passer ;
6. J’aime ;
7. Match ;
8. Discussion.

Les quatre premières étapes restent dans le formulaire de profil existant. À la
première sauvegarde du profil complet, le membre est redirigé vers l’étape 5 sur
la page d’onboarding produit. Cette page affiche le même composant de progression
que le formulaire de profil, avec les huit libellés et l’étape courante. Les
étapes déjà terminées sont visuellement accomplies, mais la page produit ne
permet pas de revenir modifier le profil.

Le parcours produit ne contient plus les actions « Continuer plus tard »,
« Recommencer » ou « Ignorer le tutoriel ». Il ne contient plus le badge
« Démonstration », le titre générique « Découvrez le fonctionnement », ni le
paragraphe expliquant que le contenu est fictif. Les termes « fictif »,
« message fictif », « conversation de démonstration » et « match de
démonstration » disparaissent de l’interface.

Chaque étape conserve une instruction courte liée à l’action immédiate. Une
fois la discussion terminée, le statut devient `completed` et le membre rejoint
la découverte réelle.

## Caractère obligatoire et navigation

Un membre actif, vérifié et doté d’un profil complet dont l’onboarding produit
n’est pas `completed` doit toujours être redirigé vers `onboarding.show` quand
il tente d’ouvrir une route membre. Cette protection est assurée par un
middleware serveur et ne dépend pas de contrôles Vue.

Les routes nécessaires au chargement de l’onboarding, à sa progression, à sa
complétion et aux images d’avatars restent accessibles. Les routes publiques,
d’authentification et de complétion du profil conservent leur comportement. Un
administrateur déjà éligible au tutoriel reste soumis au parcours avant l’espace
membre, sans perdre l’accès nécessaire au parcours lui-même.

Les endpoints `skip` et `restart`, ainsi que la page de réglages permettant une
relance volontaire, sont supprimés. Les anciens enregistrements `skipped` sont
migrés vers `in_progress` à l’étape `pass_demo`, afin qu’aucun compte historique
ne contourne le nouveau parcours obligatoire. Le cas `Skipped` disparaît de
l’enum applicatif et des statistiques administratives. L’administration affiche
désormais `not_started`, `in_progress`, `completed` et le taux de complétion.

Une interruption de navigateur ne perd pas la progression : le membre reprend
l’étape persistée. Il n’existe pas de commande de retour au début.

## Composants partagés

### Stepper

Le stepper du profil devient un composant configurable recevant ses libellés,
son étape courante et son nombre total. Le formulaire de profil l’utilise pour
les étapes 1 à 4 et la page produit pour les étapes 5 à 8. L’apparence, le
contraste, les libellés accessibles et le comportement `aria-current="step"`
restent identiques.

### Carte de découverte

Les composants `DemoSwipeCard` et leur animation spécifique sont supprimés.
L’onboarding utilise directement `discovery/SwipeCard.vue` avec les données des
deux profils pédagogiques adaptées au type `DiscoveryProfile`.

`SwipeCard` reçoit une contrainte optionnelle `allowedDecision` valant `pass`,
`like` ou `both`, avec `both` comme valeur par défaut pour la découverte réelle :

- à l’étape Passer, le bouton J’aime est désactivé et le geste vers la droite
  ne peut pas émettre de décision ;
- à l’étape J’aime, le bouton Passer est désactivé et le geste vers la gauche
  ne peut pas émettre de décision ;
- un geste interdit ramène la carte à sa position initiale avec l’animation de
  retour normale ;
- les flèches clavier suivent exactement la même contrainte ;
- une décision autorisée conserve l’animation de sortie accélérée par
  `translate3d`, la capture de pointeur et le respect de
  `prefers-reduced-motion` du composant réel.

Le client empêche donc l’action incorrecte à la source. Le serveur continue de
valider l’ordre des transitions. Si une transition serveur échoue ou si une
requête réseau échoue, un toast compact décrit l’erreur et l’étape reste en
place. Aucun bloc d’erreur ne décale la carte dans la page.

### Dialogue de match

Le dialogue actuellement défini dans `Discovery/Index.vue` est extrait dans un
composant partagé. Il reçoit le nom du membre, son état d’ouverture et deux
actions : ouvrir la conversation et continuer la découverte. La découverte
réelle conserve ses liens et son comportement actuels.

Dans l’onboarding, le dialogue utilise le même titre « C’est un match ! », la
même description et le même bouton « Ouvrir la conversation ». L’action
secondaire permettant de continuer à découvrir est masquée, car l’ouverture de
la conversation est obligatoire. Aucun identifiant de vrai match ou de vraie
conversation n’est transmis.

### Interface de discussion

L’en-tête, la timeline et le composer de la conversation sont rendus avec les
mêmes composants que `Conversations/Show.vue`. Les parties actuellement liées
directement aux routes sociales sont séparées de leur présentation par de petits
contrats :

- la timeline reçoit une collection de messages et peut désactiver le
  chargement infini lorsqu’elle est utilisée dans l’onboarding ;
- le composer reçoit une fonction d’envoi. Par défaut, cette fonction appelle
  l’endpoint réel ; dans l’onboarding, elle ajoute le message uniquement à
  l’état local ;
- l’en-tête reçoit le participant et une action de retour optionnelle. Le retour
  est absent pendant l’onboarding.

L’étape affiche un premier message pédagogique d’Alex. Le membre écrit une
réponse dans le véritable composer. Après l’envoi local réussi, l’action finale
« Commencer à découvrir » devient disponible et complète l’onboarding. Les
libellés restent ceux de l’interface réelle : « Échange privé », « Message »,
« Écrire un message… » et « Envoyer le message ».

## Données et sécurité

Les deux avatars configurés dans l’administration restent les sources des
profils pédagogiques et restent protégés contre l’archivage et la suppression.
Les noms, biographies, intérêts et premier message restent des contenus internes
localisés ; ils ne proviennent jamais de membres réels.

La page peut construire des objets conformes aux contrats frontend réels, mais
les transitions backend ne reçoivent toujours aucun identifiant de profil,
swipe, match, conversation ou message. Elles n’appellent jamais
`CreateSwipe`, les modèles sociaux, le contrôleur de messages ou Reverb.

La réponse saisie à l’étape Discussion vit uniquement en mémoire dans le
navigateur jusqu’à la complétion. Elle n’est ni persistée, ni journalisée, ni
diffusée. L’ensemble du parcours doit laisser les tables `swipes`, `matches`,
`conversations` et `messages` inchangées.

## Mise en page et accessibilité

La navigation principale et la navigation inférieure restent absentes pendant
les étapes 5 à 8. Le stepper occupe le haut de la page, suivi uniquement de
l’instruction de l’étape et du composant réel concerné. La hauteur disponible
est allouée au contenu utile sur mobile, sans grand en-tête répétitif.

Le focus rejoint le titre ou la zone principale de chaque nouvelle étape. Les
boutons interdits exposent réellement `disabled`. La carte décrit au lecteur
d’écran uniquement les gestes disponibles pour l’étape courante. Les gestes
verticaux restent réservés au défilement et les gestes horizontaux sous le seuil
ramènent la carte en place. Les toasts utilisent le système global et une zone
annoncée accessible sans modifier le flux de mise en page.

## Gestion des erreurs

Le client verrouille une transition pendant son traitement pour éviter les
doubles soumissions. Une réponse inattendue ou une panne réseau conserve l’étape
et la carte, réactive l’action autorisée et affiche un toast réessayable. Une
transition hors ordre forgée est refusée par la validation serveur.

Si la configuration des deux avatars est absente ou invalide, le membre voit un
état bloquant concis demandant de contacter l’équipe ; il n’est jamais redirigé
vers l’espace membre. L’administration conserve la capacité de corriger les
avatars configurés.

## Tests

Les tests métier et HTTP couvrent :

- la conversion des anciens statuts `skipped` en `in_progress/pass_demo` ;
- la suppression des routes d’ignorance, de redémarrage et de relance ;
- le middleware obligatoire sur les routes membre et ses exemptions exactes ;
- la reprise de l’étape persistée après interruption ;
- les transitions ordonnées jusqu’à `completed` ;
- le refus serveur des transitions forgées ;
- l’absence d’écriture dans les quatre tables sociales ;
- les statistiques administratives sans statut `skipped`.

Les tests Pest Browser couvrent :

- le stepper 5 à 8 et l’absence des anciens menus et textes ;
- l’utilisation du vrai `SwipeCard` ;
- le bouton J’aime désactivé et le geste droit neutralisé à l’étape Passer ;
- le bouton Passer désactivé et le geste gauche neutralisé à l’étape J’aime ;
- les gestes autorisés fluides, les boutons et le clavier ;
- le vrai dialogue de match sans échappatoire secondaire ;
- la vraie présentation de conversation et l’envoi local ;
- un échec de transition présenté en toast sans bloc d’erreur ;
- la complétion suivie de l’accès à la découverte ;
- l’absence de navigation membre pendant le parcours.

Les contrôles finaux comprennent les tests ciblés, les tests frontend unitaires,
les contrôles TypeScript et de formatage, le build Vite, `composer test` et la
CI de la pull request.

## Hors périmètre

Cette refonte n’ajoute aucun vrai profil pédagogique, aucune écriture sociale,
aucun événement analytique supplémentaire, aucune pièce jointe ou fonctionnalité
de messagerie, et aucune possibilité administrative d’éditer les textes du
parcours.
