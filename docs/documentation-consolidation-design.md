# Conception de la consolidation documentaire

## Objectif

Rationaliser la documentation de référence de DLP Friends sans perdre de
décision produit, technique, opérationnelle, de qualité ou de sécurité encore
applicable. Le parcours de lecture courant doit être court, chaque sujet
normatif doit posséder une seule source de vérité et la documentation doit
distinguer le produit prévu de l’état réellement implémenté.

## Contraintes validées

- Ne modifier, déplacer ni supprimer aucun fichier sous `docs/superpowers/`.
- Classer ces plans et spécifications comme historiques et non normatifs sans
  les inclure dans le parcours de lecture courant.
- Conserver séparément les documents dont la fonction l’exige : installation,
  contribution, architecture, données, opérations, qualité, sécurité et
  principes d’ingénierie.
- Ne modifier aucune règle métier et ne pas étendre le MVP.
- Préserver le positionnement strictement amical et l’absence d’affiliation à
  Disney ou Disneyland Paris.

## Architecture documentaire cible

### Entrées racine

- `README.md` reste le guide d’installation et l’index documentaire destiné aux
  nouveaux contributeurs.
- `CONTRIBUTING.md` reste le guide du workflow Git, des contrôles et des
  releases.
- `AGENTS.md` reste l’entrée opérationnelle reconnue par les agents. Son
  parcours métier devient : `avatar.md`, `docs/PRD.md`, puis la référence du
  domaine concerné.
- `avatar.md` définit uniquement la persona de l’agent, sa manière de
  collaborer, son style de réponse et ses principes de décision. Il ne duplique
  ni les commandes, ni la stack détaillée, ni les règles métier.

### Références produit et design

- `docs/PRD.md` devient la source produit canonique. Il absorbe
  `product-vision.md`, `mvp-v1.md` et `roadmap.md`, et sépare explicitement la
  cible produit, l’état implémenté vérifié et le hors-périmètre.
- `docs/design-system.md` devient la source de vérité du langage visuel. Il
  absorbe les règles pertinentes de `ux-design.md` concernant les tokens,
  thèmes, typographies, composants, états, responsive et accessibilité.
- Les parcours et règles métier présents dans `ux-design.md` migrent vers le
  PRD plutôt que vers le design system.

### Références spécialisées conservées

- `docs/data-model.md` : entités, contraintes de stockage et règles du modèle.
- `docs/technical-architecture.md` : architecture réellement en place.
- `docs/security-privacy.md` : sécurité, confidentialité et contrôle des
  données.
- `docs/operations.md` : exploitation et procédures opérateur.
- `docs/quality-ci-cd.md` : qualité, branches, CI, livraison et release.
- `docs/engineering-principles.md` : conventions et principes de conception.

Chaque règle répétée sera conservée dans sa source spécialisée et remplacée
ailleurs par un lien ou un résumé non normatif.

### Inventaire et traçabilité

`docs/documentation-inventory.md` inventorie chaque fichier Markdown existant
au début de la migration. Pour chacun, il indique :

- l’audience et le rôle ;
- le caractère normatif ou historique ;
- les principaux doublons et liens entrants ;
- la décision de conservation, fusion ou suppression ;
- la destination explicite des informations utiles ou la justification d’une
  conservation historique.

Les documents sous `docs/superpowers/` y sont classés comme historiques,
consultables pour la traçabilité, non normatifs et strictement inchangés.

## Migration des sources actuelles

| Source | Décision | Destination principale |
| --- | --- | --- |
| `docs/product-vision.md` | Fusion puis suppression | `docs/PRD.md` |
| `docs/mvp-v1.md` | Fusion puis suppression | `docs/PRD.md` et références spécialisées |
| `docs/roadmap.md` | Fusion puis suppression | `docs/PRD.md` |
| `docs/ux-design.md` | Fusion puis suppression | `docs/design-system.md` et `docs/PRD.md` |
| `docs/superpowers/plans/*.md` | Conservation sans modification | Historique non normatif |
| `docs/superpowers/specs/*.md` | Conservation sans modification | Historique non normatif |

Une source fusionnée n’est supprimée qu’après présence de toutes ses
informations encore utiles dans une destination visible dans le diff.

## Produit prévu et état implémenté

Le PRD contient une matrice de capacités avec trois statuts :

- **Implémenté** : comportement confirmé par le code, les migrations, les
  routes et les tests ;
- **Partiel** : une partie observable existe, mais au moins une exigence de la
  cible manque ;
- **Planifié** : capacité produit documentée mais absente du code actuel.

Au moment de la migration, l’audit doit notamment distinguer les capacités
sociales déjà présentes des fonctions encore absentes comme les connexions
Google/Apple, la photo personnelle, l’export des données et la suppression
différée. Le code détermine l’état réel ; les décisions produit validées
déterminent la cible.

Les documents techniques ne décrivent que l’existant. Les fonctions planifiées
restent dans le PRD et ne sont pas présentées comme des composants actuels.

## Vérifications

La consolidation est validée par :

1. un inventaire exhaustif des documents racine, de `docs/` et de
   `docs/superpowers/` ;
2. une recherche de toutes les références vers les fichiers fusionnés ou
   supprimés ;
3. une vérification automatisée de tous les liens Markdown internes ;
4. une comparaison des affirmations sur l’existant avec les routes, migrations,
   modèles, pages et tests ;
5. une recherche des règles normatives encore dupliquées ;
6. une relecture de la terminologie amicale et de la mention de non-affiliation ;
7. une relecture de `AGENTS.md`, `README.md` et `CONTRIBUTING.md` comme nouveau
   contributeur et comme agent de développement ;
8. la confirmation par `git diff` qu’aucun fichier sous `docs/superpowers/` n’a
   changé.

Le changement étant documentaire, les contrôles applicatifs complets ne sont
pas requis. Les vérifications ciblées de format, de liens, de références et de
cohérence constituent les preuves de validation attendues.

## Résultat attendu

Le parcours courant devient court et explicite, le PRD porte le contrat produit,
les références spécialisées ne se recouvrent plus et les documents historiques
restent consultables sans être confondus avec les sources normatives. Chaque
suppression est reliée à une destination dans l’inventaire et aucun document
Superpowers n’est modifié.
