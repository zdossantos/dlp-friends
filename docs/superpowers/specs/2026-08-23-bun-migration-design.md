# Migration de npm vers Bun

## Contexte

DLP Friends utilise npm pour installer ses dépendances JavaScript et exécuter
les scripts Vite, ESLint, Prettier, TypeScript et Vitest. npm est présent dans
les commandes Composer, les workflows GitHub Actions, l'image Docker et la
documentation développeur. L'issue 45 demande de remplacer entièrement cet
outillage par Bun sans modifier le comportement de l'application ni les
versions de ses dépendances.

## Objectif

Bun 1.3.14 devient l'unique gestionnaire de paquets et exécuteur de scripts
JavaScript du dépôt. Cette version stable est verrouillée dans
`package.json`, GitHub Actions et Docker. Les installations locales, CI et
Docker utilisent le lockfile texte `bun.lock` et refusent toute divergence
entre le manifeste et le lockfile.

## Gestion des dépendances

`package.json` déclare `"packageManager": "bun@1.3.14"`. Le lockfile
`bun.lock` est généré par Bun 1.3.14 à partir de l'actuel `package-lock.json`.
Les versions résolues des dépendances directes et transitives sont comparées
avant et après la conversion afin de détecter toute mise à jour involontaire.

Après validation de cette équivalence, `package-lock.json`, `.npmrc` et la
configuration `pnpm-workspace.yaml` sont supprimés. Aucun lockfile Yarn ou pnpm
n'est ajouté. Les dépendances
optionnelles propres aux plateformes continuent d'être décrites dans
`package.json`; Bun choisit les artefacts compatibles au moment de
l'installation sans rendre le lockfile dépendant de la plateforme.

## Scripts et commandes Composer

Tous les scripts existants conservent leur nom et leur comportement. Le script
`test` de `package.json` appelle `bun run test:unit` au lieu de déléguer à npm.
Les scripts `setup` et `ci:check` de `composer.json` utilisent `bun install`,
`bun run build` et les commandes `bun run` correspondantes.

La migration ne remplace aucun outil applicatif par une API native de Bun :
Vite, ESLint, Prettier, vue-tsc et Vitest restent les exécutables déclarés par
le projet et sont seulement lancés par Bun.

## GitHub Actions

Les jobs `Frontend quality` et `Vite build` remplacent `actions/setup-node`
par l'action officielle `oven-sh/setup-bun`, épinglée sur un SHA de commit et
configurée avec Bun 1.3.14. Les dépendances sont installées avec
`bun install --frozen-lockfile`, puis chaque contrôle est exécuté avec
`bun run`.

Le cache npm est remplacé par un cache du répertoire global de téléchargement
de Bun, `~/.bun/install/cache`, indexé par le système d'exploitation, la
version Bun et le hash de `bun.lock`. `node_modules` n'est pas mis en cache.
Les noms des jobs et des checks obligatoires restent inchangés.

## Docker

Le build multi-stage ajoute une étape fondée sur l'image officielle
`oven/bun:1.3.14-alpine`. Le binaire Bun est copié dans l'étape PHP Alpine de
build. Les paquets Alpine `nodejs` et `npm` sont supprimés.

L'image installe les dépendances avec `bun install --frozen-lockfile`, produit
les assets avec `bun run build`, puis supprime `node_modules` comme avant. Bun
n'est pas requis dans l'étape runtime finale.

## Dependabot et documentation

Dependabot utilise `package-ecosystem: "bun"` et conserve la cadence, le
cooldown, la limite de pull requests et le regroupement existants. Le groupe
est renommé pour refléter Bun.

Les sources de vérité actives sont mises à jour : `README.md`,
`CONTRIBUTING.md`, `docs/technical-architecture.md`,
`docs/quality-ci-cd.md`. Les anciens documents sous
`docs/superpowers/specs/` et `docs/superpowers/plans/` restent inchangés : ils
décrivent l'état et les décisions historiques au moment de leur rédaction.

Les exclusions `npm-debug.log` et `yarn-error.log` sont retirées de
`.gitignore` et `.dockerignore`, puisqu'aucun de ces gestionnaires n'est encore
utilisé.

## Tests et vérification

Un test d'infrastructure PHP vérifie les invariants durables de la migration :
version Bun déclarée, présence de `bun.lock`, absence de `package-lock.json`,
commandes Bun dans la CI et Docker, et écosystème Bun dans Dependabot. Il est
écrit avant les changements de configuration afin de constater son échec sur
l'état npm.

La validation finale comprend :

1. comparaison des versions verrouillées avant et après conversion ;
2. installation propre avec `bun install --frozen-lockfile` ;
3. `bun run lint:check`, `bun run format:check`, `bun run types:check`,
   `bun run test` et `bun run build` ;
4. contrôles Composer et suite PHP existante ;
5. démarrage bref du serveur Vite de développement ;
6. build de l'image Docker jusqu'à l'étape runtime ;
7. recherche finale des références actives à npm, npx, Yarn ou pnpm.

Les références historiques et les mentions indiquant explicitement qu'aucun
package npm n'est publié ne sont pas des commandes actives et peuvent être
conservées lorsqu'elles restent factuellement utiles.

## Hors périmètre

Cette migration ne change ni les dépendances applicatives, ni le code Laravel
ou Vue, ni les noms des checks de protection de branche, ni le processus de
release. Elle n'introduit pas les fonctions de runtime, test runner ou bundler
natives de Bun à la place des outils actuels.
