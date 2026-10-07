# Direction proposée : des univers partagés, une interface calme

## Choix argumenté

Trois directions ont été considérées : une interface très festive sur chaque écran ; une interface purement utilitaire ; une marque expressive sur le public et une application calme dans les tâches. La troisième est proposée : elle conserve la personnalité déjà documentée sans faire porter à un échange, un formulaire ou une décision de modération la densité d’une page promotionnelle. C’est une proposition à valider.

La marque garde son logo, son violet et son rose. Le rôle de Cinzel Decorative reste limité à la marque et au grand titre public. Instrument Sans assure les textes, chiffres, formulaires et titres fonctionnels. Les avatars de revue sont abstraits ; l’intégration réemploiera le catalogue existant, sans upload de photos ni asset Disney ajouté.

## Tokens et hiérarchie

| Élément     | Proposition                                                                                                                                                          |
| ----------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Texte       | Corps 16 px / 1,5 ; secondaire 13–14 px ; titres 28–40 px selon largeur ; poids 400 / 600                                                                            |
| Couleur     | Fond ivoire, surfaces blanches, texte violet très sombre ; inversion calme en sombre. Violet pour l’action principale, rose pour l’affinité, rouge réservé au danger |
| Saison      | Halloween : cuivre et accents chauds ; Noël : vert et accents rosés. La sémantique danger, succès, indisponibilité reste indépendante                                |
| Espacement  | Base de 4 px ; groupes 8–12 px ; sections 24–32 px ; marge mobile 16 px                                                                                              |
| Forme       | Rayon 14 px repris du système ; champs 9 px ; bordure sobre plutôt que multiplication des ombres                                                                     |
| Interaction | Cible de 44 px minimum dans les parcours applicatifs ; focus visible ; état actif également identifiable par sa forme et son libellé                                 |

Les couleurs standard reprennent les familles existantes. Les valeurs saisonnières du prototype sont des **propositions** à comparer aux tokens de production, puis à transférer uniquement après validation et contrôle de contraste. Le prototype CSS n’est pas destiné à être importé dans l’application.

## Téléphone d’abord

À 320 et 390 px : une colonne, textes qui reviennent à la ligne, cartes pour les tableaux opérationnels, boutons primaires explicites et navigation basse. Les libellés visibles courts proposés sont « Agenda » / Events et « Alertes » / Alerts ; les titres de pages et libellés accessibles conservent Événements et Notifications. Ce choix de navigation reste à valider. Le membre multirôle conserve six destinations ; un rôle simple n’affichera que les destinations autorisées par l’existant. Le desktop garde ces destinations, élargit les listes et propose une comparaison en deux colonnes lorsque cela aide la tâche.

Panneaux : feuille presque pleine hauteur sur téléphone, dialogue plus étroit sur desktop ; le contenu défile, le titre et la fermeture restent identifiables. Les événements gardent la liste en arrière-plan. Profil participant → participants → événement → liste. En intégration, réutiliser les panneaux existants, leurs routes et leur restauration de scroll ; le prototype ne reproduit pas leur transport Inertia.

Échanges : bulles à largeur limitée, longue chaîne et liens qui se coupent proprement, saisie et compteur de 2 000 caractères. Vérifier le clavier virtuel et les safe areas sur appareil réel lors de l’intégration. Ne pas transformer la page en capture figée nécessitant un scroll automatique forcé.

## États et langue

Le prototype propose normal, vide, chargement, erreur, succès, indisponible, lecture seule, sélection et non lu. Certains états sont des compositions génériques pour valider le langage ; ils ne créent pas de nouvelle réponse serveur. Les états spécialisés sont le consentement non précoché, le verrouillage du mode d’inscription, le lieu privé, le compte bloqué, la discussion archivée et les révisions partenaire.

FR/EN sont disponibles dans le catalogue de revue. Les futures traductions doivent être intégrées aux catalogues Laravel/frontend existants. Les exemples fictifs et les mentions destinées à la revue ne doivent pas apparaître dans le produit. L’appellation « univers », les intentions amicales, la majorité et la non-affiliation sont conservées.

## Mouvement et bibliothèques

La demande utilisateur autorise une bibliothèque optimisée si elle améliore l’intégration. Commencer avec Vue Transition, les animations CSS et les outils déjà présents (`tw-animate-css`, dotLottie pour les usages existants). Aucun package n’est ajouté à cette phase.

Réponse de commande : 120–180 ms ; entrée d’un panneau : 180–240 ms ; célébration d’Univers croisés : une seule séquence courte, pouvant être ignorée. Animer `opacity` et `transform`, pas les dimensions/layout ; ne pas bloquer l’action ni différer une confirmation métier. Aucun fond en mouvement permanent ni effet sonore.

Si une orchestration plus complexe est nécessaire, comparer une bibliothèque actuelle à ces primitives au moment de l’intégration : taille compressée du chunk, import à la demande, coût CPU sur téléphone, compatibilité Vue et `prefers-reduced-motion`. Documenter le gain avant ajout. La préférence de mouvement réduit supprime les transitions et conserve tous les retours d’état en texte. La performance et le confort priment sur la richesse d’un effet.
