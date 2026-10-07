# Direction en cours — un univers magique, compact sur mobile

## Brief révisé le 7 octobre 2026

La première proposition, calme et utilitaire, est refusée : elle manque de personnalité et ses éléments sont trop grands. Le nouveau brief demande le sentiment d’entrer dans un endroit magique, une place importante pour l’imaginaire et une utilisation confortable sur mobile, navigateur comme PWA installée.

**Les décors reconnaissables en fond sont également refusés.** Seule une ambiance très discrète, fortement floutée, est admissible. Explorer expérimente actuellement un voile de lumière à 12 % d’opacité avec 60 px de flou. Les illustrations originales ne sont jamais affichées nettes dans cette vue. Ce traitement est provisoire ; les questions sur les ornements, la lumière et la force des saisons restent ouvertes. Il ne constitue pas la DA validée.

Explorer est l’écran témoin de cette révision. Les 66 autres surfaces restent la couverture fonctionnelle de la première proposition et doivent être revues après le choix de direction. Le logo, le violet, le rose et les polices restent provisoirement conservés. Le rôle de Cinzel Decorative reste limité à la marque et au grand titre public ; Instrument Sans assure la lecture et les commandes.

Les pages à contenu borné doivent tenir dans la hauteur disponible sans scroll de page, avec accès au détail si nécessaire. Les listes, messages et autres contenus variables peuvent défiler. La maquette Explorer utilise `100svh`, garde la navigation dans le layout et conserve des cibles de 44 px malgré des textes et espacements plus compacts. Les tests incluent un viewport de 320 × 480 px pour simuler la hauteur utile réduite d’un navigateur mobile. Cela ne remplace pas la vérification sur appareil des safe areas, du clavier et de la PWA.

## Tokens et hiérarchie

| Élément     | Proposition                                                                                                                                                          |
| ----------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Texte       | Explorer révisé : corps 14 px / 1,4 ; secondaire 11–12 px ; titres 20 px ; cibles 44 px. Autres surfaces : valeurs V1 à reprendre                                    |
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
