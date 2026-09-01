# Design des interactions et retours utilisateur

## Contexte

L’issue 40 vise à rendre les interactions importantes de DLP Friends plus
fluides, compréhensibles et agréables sans ralentir les parcours. Le produit
dispose déjà d’états de traitement, d’erreurs récupérables, de squelettes et de
quelques transitions locales. Il manque cependant un langage de mouvement
commun et une expérience suffisamment expressive lors des moments forts.

La direction retenue distingue deux niveaux : une animation lumineuse et
expressive pour le like et le match, puis des retours courts et discrets pour
la messagerie, les formulaires et la navigation. Le vocabulaire visuel reste
abstrait et original afin de ne reprendre aucun asset ou personnage protégé.

## Principes de mouvement

- Centraliser les durées, courbes et intensités réutilisables dans les tokens
  CSS du design system.
- Réserver les effets expressifs aux moments émotionnels : like confirmé et
  match réciproque.
- Employer des transitions de 100 à 200 ms pour les retours fonctionnels et
  une durée maximale d’environ 500 ms pour un moment fort non bloquant.
- Animer uniquement `transform`, `opacity`, les ombres et les pseudo-éléments
  superposés afin d’éviter les décalages de mise en page.
- Garder les contrôles, le clavier et les lecteurs d’écran utilisables pendant
  toute animation.
- Ne jamais retarder une action métier pour attendre la fin d’une animation.
- Fournir les mêmes informations et actions lorsque les animations sont
  désactivées.

## Architecture

L’implémentation n’ajoute pas de bibliothèque d’animation. Les primitives CSS
et les composants Vue existants suffisent, limitent le JavaScript exécuté sur
mobile et suivent KISS.

Les tokens partagés vivent dans `resources/css/app.css` et couvrent les durées,
les courbes et les animations communes. Un petit composable Vue expose la
préférence `prefers-reduced-motion` lorsqu’un composant doit adapter un délai
fonctionnel ou ne pas créer des éléments décoratifs. Les états asynchrones
restent pilotés localement par leur vraie source : `processing` des formulaires
Inertia, `pending` des requêtes `fetch` et callbacks des visites Inertia.

Les composants métier peuvent ajouter des couches décoratives en position
absolue. Ces couches sont `aria-hidden`, ignorent les événements pointeur et
n’affectent jamais la taille du contenu.

## Découverte optimiste

Un like ou un pass retire immédiatement la carte active et révèle la suivante,
sans attendre la réponse du serveur. La requête est envoyée en arrière-plan et
une seule décision réseau peut être active à la fois pour empêcher les doubles
actions et préserver l’ordre des suggestions.

Le like produit une impulsion violette et rose, quelques particules lumineuses
et une sortie vers la droite. Le pass utilise une traînée plus sobre et une
sortie vers la gauche. La carte suivante remonte légèrement depuis la pile pour
donner une impression de profondeur.

Pendant la requête, la carte retirée et sa décision sont conservées en mémoire.
En cas de succès, elles sont oubliées et les suggestions renvoyées par le
serveur deviennent la source de vérité. En cas d’erreur HTTP ou réseau, la
carte revient au sommet avec sa saisie intacte, les actions redeviennent
disponibles et l’alerte récupérable existante décrit l’échec. Le bouton de
nouvel essai reprend exactement la décision échouée.

Un match ne peut être connu qu’après la réponse serveur. La carte part donc
immédiatement, puis le dialogue de match apparaît dès la confirmation reçue,
sans bloquer entre les deux états.

## Match

Le dialogue de match reste une primitive accessible avec gestion du focus dès
son ouverture. Son entrée ajoute un halo doré, des étincelles abstraites et un
rapprochement visuel des représentations des deux membres. Ces décorations ne
contiennent aucun asset tiers, sont masquées aux technologies d’assistance et
ne retardent ni la fermeture ni l’ouverture de la conversation.

Le titre et la description existants restent la source accessible de
l’information. L’animation ne porte aucun sens exclusif.

## Messagerie

L’envoi verrouille immédiatement le bouton et expose un état occupé stable.
Le texte reste dans le champ tant que le serveur n’a pas accepté le message.
Après succès, il est effacé et le nouveau message apparaît avec une brève
lueur et une transition d’opacité. En cas d’échec, la saisie reste présente,
l’erreur demeure liée au champ et l’envoi redevient possible.

Les messages reçus en temps réel utilisent uniquement une apparition discrète.
Ils ne modifient pas les règles existantes de position de défilement ni les
annonces `aria-live`.

## Formulaires et navigation

Les actions principales des formulaires affichent un indicateur de chargement
dans un espace réservé du bouton, utilisent `aria-busy` lorsque pertinent et
restent désactivées pendant le traitement. Les erreurs conservent toutes les
valeurs saisies. Les succès déjà signalés par toast ou contenu de page ne sont
pas dupliqués.

Les visites Inertia donnent un retour discret au niveau de la navigation et du
contenu courant. Le contenu existant reste visible et stable jusqu’à la
réponse ; aucun écran de chargement plein format n’est ajouté.

## Réduction des animations

Avec `prefers-reduced-motion: reduce`, les déplacements, rotations,
scintillements, pulsations, halos animés et particules sont supprimés. Les
cartes changent instantanément, le dialogue apparaît sans transition et les
états chargement, succès et erreur restent visibles sous forme statique.

Les temporisations qui ne servent qu’à coordonner un effet visuel deviennent
nulles. Les requêtes, verrouillages, rollbacks, annonces accessibles et
changements de focus conservent exactement le même comportement.

## Erreurs et stabilité

- Toute action asynchrone importante se verrouille dès son déclenchement.
- Une erreur rend toujours l’action de nouveau disponible.
- Une saisie utilisateur n’est effacée qu’après un succès confirmé.
- Les zones de chargement réservent leurs dimensions avant le traitement.
- Les animations décoratives ne capturent ni focus ni événements pointeur.
- Les effets restent limités en nombre et en surface pour éviter un coût
  notable sur mobile.

## Tests et vérification

Le projet utilise actuellement `bun test` pour les tests frontend plutôt que
Vitest. Les comportements attendus par l’issue seront couverts avec cette
infrastructure existante, sans ajouter un second moteur de tests uniquement
pour cette évolution.

Les tests frontend couvrent la préférence de réduction des animations et les
fonctions d’état isolables. Les tests Pest Browser couvrent le retrait
optimiste de la carte, le verrouillage des doubles décisions, le rollback sur
erreur, l’ouverture du match, la conservation d’un message en erreur, les
états occupés et l’absence de mouvement lorsque la réduction est active.

La vérification finale comprend les tests ciblés puis les contrôles frontend et
backend concernés, un audit manuel mobile et clavier, un passage avec
`prefers-reduced-motion`, ainsi qu’un contrôle Lighthouse des performances et
des décalages de mise en page.

## Hors périmètre

Sons, vibrations natives, récompenses, animations décoratives longues,
refonte de l’identité visuelle, nouvelle bibliothèque d’animation et refonte
générale des composants sans rapport direct avec les actions citées par
l’issue restent hors périmètre.
