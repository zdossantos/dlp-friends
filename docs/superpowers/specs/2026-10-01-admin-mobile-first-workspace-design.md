# Administration mobile-first et navigation par espaces — conception

## Statut et objectif

Cette spécification formalise l’issue 235 et les décisions produit validées le
1er octobre 2026. Elle remplace le shell d’administration à barre latérale par
une expérience mobile-first cohérente avec les espaces membre et partenaire,
sans modifier les règles métier, les autorisations ou les données administrées.

Le résultat doit permettre d’accomplir toutes les tâches d’administration dès
320 px, sans défilement horizontal imposé, tout en conservant une présentation
efficace sur ordinateur.

## Périmètre

Le périmètre comprend :

- un shell d’administration sans barre latérale, avec navigation basse ;
- un sélecteur d’espace partagé entre les contextes membre, partenaire et
  administration ;
- des groupes de navigation ouvrant un panneau de sous-menu ;
- la refonte responsive des écrans Tableau de bord, Membres, Intérêts,
  Avatars, Tutoriel, Thèmes saisonniers, Fiches partenaires, Annonces
  partenaires et Statistiques partenaires ;
- les traductions françaises et anglaises nécessaires ;
- les tests automatisés de navigation, de responsive et de non-régression des
  actions existantes.

Sont exclus : toute nouvelle capacité métier, toute modification des rôles ou
des permissions, toute nouvelle route, la refonte des espaces membre et
partenaire hors extraction des composants partagés, et l’ajout d’un système de
personnalisation de navigation.

## Navigation de l’administration

La navigation basse de l’administration contient au plus cinq actions :

1. **Tableau de bord**, lien direct ;
2. **Membres**, lien direct ;
3. **Catalogues**, groupe ouvrant Intérêts, Avatars, Tutoriel et Thèmes
   saisonniers ;
4. **Partenaires**, groupe ouvrant Fiches, Annonces et Statistiques ;
5. **Changer d’espace**, affiché lorsqu’au moins un autre espace est accessible.

Un bouton de groupe ne navigue pas directement. Il ouvre un panneau bas qui
reprend le comportement visuel et accessible du sélecteur d’espace actuel. Le
bouton du groupe et l’entrée correspondant à la page courante exposent leur
état actif. Choisir une entrée ferme le panneau puis lance la navigation
Inertia.

Les notifications ne font pas partie de la navigation d’administration. Elles
restent une capacité de l’espace membre, y compris pour la catégorie
« Administration ».

## Sélecteur d’espace partagé

Le sélecteur d’espace devient un composant commun utilisé par les navigations
membre, partenaire et administration. Il ne mémorise aucun rôle ni contexte :
il reçoit les destinations autorisées depuis les propriétés Inertia et déduit
l’espace courant de l’URL.

Les entrées possibles sont :

- **Membre**, si le compte possède le rôle `user` ;
- **Partenaire**, si le compte possède le rôle `partner` ;
- **Administration**, si le compte possède le rôle `admin`.

Le sélecteur n’est affiché que lorsqu’une destination différente de l’espace
courant existe. Un compte administrateur seul reste donc dans l’administration
sans bouton inutile. Les contrôles serveur existants restent la seule source
d’autorisation ; masquer une destination dans Vue ne confère ni ne retire un
droit.

## Composants et responsabilités

La navigation existante est extraite sans construire un moteur de navigation
générique :

- un composant de barre basse fournit le conteneur, l’état actif, l’état de
  navigation en cours, les compteurs facultatifs et les cibles tactiles ;
- un composant de groupe affiche un bouton et un panneau de destinations ;
- un composant de sélection d’espace construit les espaces visibles à partir
  des rôles et du contexte courant ;
- les navigations membre/partenaire et administration conservent chacune leur
  configuration explicite et leurs icônes.

Cette séparation partage les comportements réellement communs sans introduire
une configuration globale difficile à comprendre ou à tester.

## Shell et mise en page

`AdminLayout` cesse d’utiliser le shell à barre latérale. Il fournit un cadre
simple comprenant la zone de contenu, la navigation basse fixe, les marges de
sécurité iOS et un espace inférieur suffisant pour qu’aucun contrôle ne soit
masqué.

Les pages utilisent un conteneur commun mobile-first avec largeur maximale,
espacement horizontal progressif, titre, description et éventuelles actions.
Les fils d’Ariane de l’ancienne barre latérale ne sont pas reproduits : la
navigation active et les titres de page suffisent dans cette hiérarchie peu
profonde.

Le rendu doit rester utilisable en thème clair, sombre et saisonnier, au clavier
et avec réduction des animations.

## Adaptation des écrans

### Tableau de bord

Les indicateurs restent des cartes empilées sur mobile puis distribuées en
grille à mesure que la largeur augmente. Les accès rapides ne dupliquent pas la
navigation basse.

### Membres

Le tableau de largeur fixe est remplacé sur mobile par des cartes. Chaque carte
présente l’identité et les rôles, les indicateurs utiles puis les actions dans
un ordre stable. Sur grand écran, le tableau peut être conservé pour faciliter
la comparaison. La recherche, la gestion des rôles, l’ouverture d’une
conversation et la suppression restent disponibles avec les confirmations et
autorisations actuelles.

### Intérêts et avatars

Les formulaires de création et réglages s’empilent sur mobile. Les éléments du
catalogue restent des cartes réordonnables, avec libellés, champs et actions
présentés sans chevauchement. Les boutons ont une cible tactile d’au moins
44 px et le réordonnancement conserve une alternative explicite aux gestes de
glisser-déposer.

### Tutoriel

Les statistiques restent en cartes. Le suivi des membres devient une liste de
cartes sur mobile et demeure un tableau sur les écrans qui permettent une
comparaison lisible. La configuration des profils de démonstration s’empile
avant de passer en colonnes.

### Thèmes saisonniers

L’état courant et les cartes de thème s’empilent sur mobile. La planification,
l’activation et la désactivation gardent leurs confirmations et affichent les
erreurs près du contrôle concerné.

### Partenaires

Les files de fiches et d’annonces conservent leurs cartes de modération. Les
contenus, décisions et motifs s’empilent sur mobile. L’ordre des fiches possède
des contrôles explicites utilisables au toucher.

Les statistiques sont présentées en cartes synthétiques sur mobile et en
tableau détaillé sur ordinateur. Les volumes restent agrégés conformément au
contrat produit et aucune identité de destinataire n’est ajoutée.

## Accessibilité et états d’interface

- chaque navigation possède un nom accessible ;
- les boutons de groupe annoncent l’ouverture du panneau et leur état actif ;
- la destination courante utilise `aria-current` ;
- les panneaux gèrent le focus, la touche Échap et le retour du focus au
  déclencheur via les primitives Reka UI existantes ;
- les cibles tactiles mesurent au moins 44 × 44 px ;
- les états chargement, vide, erreur et succès restent perceptibles sans
  dépendre uniquement de la couleur ;
- aucun texte visible ou libellé d’accessibilité n’est écrit en dur.

## Tests et critères d’acceptation

La livraison est acceptée lorsque :

1. l’administration ne rend plus la barre latérale et affiche la navigation
   basse à 320 px comme sur ordinateur ;
2. Tableau de bord et Membres naviguent directement ;
3. Catalogues et Partenaires ouvrent leurs panneaux, signalent la section
   active et donnent accès à toutes leurs sous-sections ;
4. aucune entrée Notifications n’apparaît dans l’administration ;
5. le sélecteur partagé propose exactement les espaces correspondant aux rôles
   du compte et permet les transitions membre, partenaire et administration ;
6. un administrateur sans autre rôle ne voit pas de sélecteur inutile ;
7. aucun écran admin n’impose de défilement horizontal à 320 px ;
8. Membres, suivi du tutoriel et statistiques partenaires utilisent une
   présentation mobile lisible et conservent leur présentation détaillée sur
   ordinateur ;
9. les actions administratives existantes et leurs autorisations continuent de
   fonctionner ;
10. les libellés français et anglais, le clavier, le focus et les thèmes sont
    vérifiés ;
11. les contrôles frontend et backend pertinents ainsi que le build réussissent.

Les tests navigateur couvrent au minimum les rôles combinés, les panneaux de
groupe, le contexte actif, les transitions entre espaces et l’absence de
débordement horizontal. Les tests fonctionnels existants restent la preuve des
autorisations et des règles métier ; ils ne sont modifiés que si la projection
Inertia attendue change réellement.

