# Migration des tests frontend vers Pest Browser

## Contexte

Le projet exécute actuellement ses tests backend avec Pest et ses tests Vue avec
Vitest, Vue Test Utils et jsdom. L'issue 58 demande de faire de Pest l'unique
outil d'exécution des tests, y compris pour les comportements frontend, au moyen
du plugin Pest Browser fondé sur Playwright.

L'issue a été rédigée lorsqu'il existait huit fichiers de tests frontend. Le
dépôt en contient désormais dix-sept. La migration couvre ces dix-sept fichiers
afin de ne perdre aucun scénario actif, sans conserver mécaniquement leurs
assertions sur les détails internes des composants.

## Objectifs

- Exécuter les tests unitaires, fonctionnels et navigateur depuis une seule
  commande Pest.
- Vérifier les comportements frontend dans un vrai navigateur Chromium à
  travers les routes et écrans réels de l'application.
- Préserver les scénarios couverts par les dix-sept fichiers `*.spec.ts`
  actuels.
- Supprimer entièrement Vitest, Vue Test Utils et jsdom lorsqu'aucun usage ne
  subsiste.
- Faire fonctionner la suite localement et dans GitHub Actions.
- Conserver ESLint, Prettier, TypeScript et le build Vite comme contrôles
  frontend indépendants.

## Hors périmètre

- Ajouter de nouvelles fonctionnalités produit.
- Reconcevoir les écrans ou refactoriser les composants sans nécessité pour la
  migration.
- Réécrire les documents historiques sous `docs/superpowers/`, à l'exception de
  la présente spécification et du plan qui en découlera.
- Ajouter des pages ou routes applicatives réservées aux tests.
- Étendre la matrice à Firefox ou WebKit. Chromium constitue le navigateur de
  référence de cette migration.

## Architecture

### Dépendances et configuration

Le projet ajoute `pestphp/pest-plugin-browser` aux dépendances Composer de
développement et `playwright` aux dépendances Bun de développement. Playwright
est installé et invoqué avec Bun, notamment au moyen de `bunx playwright`.

Une suite `Browser` est déclarée dans `phpunit.xml` et pointe vers
`tests/Browser`. Les conventions Laravel communes et la remise à zéro de la
base sont configurées dans `tests/Pest.php` en suivant les conventions déjà
présentes. Les captures et autres artefacts générés sous le répertoire des
tests navigateur sont ignorés par Git.

Les assets Inertia/Vue sont générés par Wayfinder puis compilés avec Vite avant
l'exécution des tests navigateur. Pest Browser démarre le serveur Laravel et
pilote Chromium ; aucune route de test ni serveur frontend spécifique n'est
introduit.

### Organisation des tests

Les tests sont regroupés par parcours produit plutôt que par ancien composant :

- accueil et authentification ;
- profil et navigation membre ;
- tableau de bord et administration ;
- découverte et interactions de swipe ;
- apparence, responsive et accessibilité.

Chaque test prépare ses données avec les factories et capacités Laravel, visite
une route réelle, interagit avec l'interface et vérifie un résultat observable.
Les assertions portent sur le contenu, les rôles accessibles, les destinations,
les états visibles, le stockage navigateur et les effets persistés côté serveur.
Les sélecteurs privilégient les noms accessibles et les champs de formulaire ;
les sélecteurs techniques ne sont employés que lorsqu'aucune surface accessible
stable n'existe.

## Couverture fonctionnelle

### Accueil et authentification

- Présentation française du service amical réservé aux adultes.
- Actions proposées aux visiteurs et accès à l'espace membre pour un utilisateur
  connecté.
- Présence des champs contractuels du formulaire d'inscription, sans nom public.
- Structure accessible du layout d'authentification, marque et contrôle de thème.
- Comportement responsive de l'en-tête sur petit écran.

### Profil et navigation

- Contrat accessible complet du formulaire de profil et catalogue d'intérêts.
- Propagation visible des erreurs de validation liées aux intérêts.
- Affichage du résumé public et de l'action de modification.
- Affichage du nom public avec repli sur l'adresse e-mail.
- Navigation différente pour un membre et un administrateur.
- État actif des destinations, y compris dans les réglages.
- Masquage de la navigation avant la fin de l'onboarding.
- Nettoyage de l'état client lors de la déconnexion.
- Layout membre mobile sans en-tête et espace réservé au dock sécurisé.

### Tableau de bord et administration

- Statistiques de comptes et inscriptions récentes.
- État, historique, limite de sélection et bornes de tri des intérêts.
- Création, modification, déplacement, archivage, réactivation et suppression
  via leurs formulaires réels.
- Confirmations requises avant archivage ou suppression.
- États désactivés pendant les soumissions et conservation observable de la
  position lorsque l'interface le permet.
- Structure accessible et disposition des contrôles essentiels.

### Découverte

- États de chargement, vide et pile préchargée limitée à cinq profils.
- Carte sans contrôles de décision visuellement concurrents, tout en conservant
  des actions accessibles aux technologies d'assistance.
- Décisions par clavier, contrôles accessibles et geste horizontal au-delà du
  seuil attendu ; rejet des gestes diagonaux ou annulés.
- Verrouillage pendant une décision et prévention des doubles soumissions.
- Conservation de la suggestion et possibilité de réessayer après une erreur
  de validation ou une erreur HTTP inattendue.
- Absence de rejeu d'une ancienne décision sur une nouvelle suggestion.
- Dialogue de nouvelle correspondance accessible, fermable et rouvert uniquement
  pour une nouvelle correspondance.

### Apparence

- Priorité de la préférence enregistrée sur la préférence système.
- Initialisation depuis la préférence système en l'absence de valeur enregistrée.
- Stabilité de l'initialisation lors des navigations, sans effet visible dû à des
  écouteurs dupliqués.

## Stratégie de migration et TDD

La migration progresse par parcours. Pour chaque groupe :

1. écrire un test Pest Browser représentant le comportement observable ;
2. confirmer qu'il échoue pour la raison attendue avant son raccordement ou son
   ajustement ;
3. le faire passer avec le minimum de changement nécessaire ;
4. comparer sa couverture aux anciens scénarios associés ;
5. supprimer les fichiers Vitest remplacés seulement lorsque le groupe est vert.

Les assertions historiques purement structurelles sont traduites vers leur
intention utilisateur. Si un comportement n'est pas accessible par une route
réelle, l'interface existante est testée depuis l'écran qui l'emploie ; aucune
page de démonstration dédiée aux tests n'est créée.

## Commandes et CI

`composer test` demeure l'entrée unique de la suite et découvre `Unit`,
`Feature` et `Browser`. La commande conserve les gardes de base de données et
les contrôles PHP déjà en place. Elle suppose que les dépendances et le
navigateur Playwright ont été installés ; elle prépare les assets frontend
nécessaires aux tests navigateur de manière déterministe.

`composer ci:check` n'appelle plus de script Vitest. Il exécute les contrôles
PHP et frontend indépendants, prépare les assets, puis lance la commande Pest
unique.

Dans GitHub Actions, le job exécutant Pest :

1. configure PHP, Composer et Bun ;
2. installe les dépendances Composer et Bun ;
3. génère les modules Wayfinder et compile Vite ;
4. installe Chromium et ses dépendances système avec Playwright ;
5. prépare et vérifie la base MySQL de test ;
6. exécute toute la suite Pest.

Le job de qualité frontend conserve ESLint, Prettier et TypeScript, mais ne lance
plus Vitest. Le build Vite reste contrôlé indépendamment conformément au pipeline
actuel.

## Suppression de Vitest

Après validation de tous les équivalents Pest Browser :

- supprimer les dix-sept fichiers `*.spec.ts` ;
- supprimer `vitest.config.ts` ;
- retirer `vitest`, `jsdom` et `@vue/test-utils` de `package.json` et `bun.lock` ;
- supprimer ou adapter les scripts `test` et `test:unit` de `package.json` afin
  qu'ils ne constituent plus une seconde entrée de tests ;
- retirer toute référence active à Vitest de la CI et de la documentation.

Une recherche globale finale sur `vitest`, hors historique Git et documents
historiques explicitement exclus, ne doit retourner aucune référence active.

## Gestion des erreurs et artefacts

Les tests attendent les transitions visibles nécessaires avant d'asserter afin
d'éviter les temporisations arbitraires. Les erreurs HTTP, JavaScript et de
console pertinentes sont rendues explicites dans les assertions lorsque cela
améliore le diagnostic. Les captures, traces et sorties générées pour diagnostiquer
un échec restent locales ou attachées au job CI et ne sont jamais versionnées.

## Documentation active

`AGENTS.md`, `CONTRIBUTING.md`, `README.md`, `docs/technical-architecture.md` et
`docs/quality-ci-cd.md` sont mis à jour lorsque leurs commandes ou descriptions
sont concernées. Les exemples ciblés utilisent des fichiers Pest sous
`tests/Browser` et les instructions d'installation mentionnent Playwright.

## Critères de validation

- Les dix-sept fichiers de tests frontend ont une couverture Pest équivalente.
- Aucun fichier `*.spec.ts`, import Vitest ou configuration Vitest ne subsiste.
- Vitest, jsdom et Vue Test Utils ont disparu de `package.json` et `bun.lock`.
- Une invocation de Pest exécute les suites backend et navigateur.
- Les tests navigateur utilisent Chromium sur les routes réelles.
- La CI installe Playwright et exécute la suite Pest complète avec MySQL.
- `composer ci:check` n'exécute plus Vitest.
- La documentation active ne prescrit plus Vitest.
- Pest, Pint, PHPStan/Larastan, ESLint, Prettier, TypeScript et Vite réussissent.
- Les artefacts Playwright ne sont pas versionnés.
