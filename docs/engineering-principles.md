# Principes d'ingénierie

Ces principes encadrent l’implémentation. Le contrat fonctionnel appartient au
[`PRD.md`](PRD.md) et les règles visuelles partagées au
[`design-system.md`](design-system.md).

## Objectif

Le code de DLP Friends doit être le plus simple, lisible et maintenable possible. Une solution plus courte, explicite et conforme aux conventions du framework est préférée à une architecture théorique ou prématurée.

## Principes non négociables

- **KISS** : choisir la solution la plus simple qui satisfait réellement le besoin.
- **YAGNI** : ne pas implémenter une option, une extension ou une flexibilité qui n'est pas requise par le MVP ou une décision documentée.
- **Lisibilité d'abord** : un nom précis, une fonction courte et un flux évident valent mieux qu'une indirection difficile à suivre.
- **Conventions avant abstractions** : utiliser les conventions Laravel, Eloquent, Inertia et Vue avant de créer une couche maison.
- **Une responsabilité claire** : chaque classe, composant et fonction doit avoir un rôle compréhensible sans lire tout le projet.
- **Tests ciblés** : tester les règles métier et les cas limites qui comptent, sans tester les détails d'implémentation ou la bibliothèque elle-même.

## Règles d'abstraction

- Ne pas créer de repository, service, interface, factory, event ou couche générique « au cas où ».
- Introduire une abstraction seulement si elle répond à un besoin actuel vérifiable : logique métier réutilisée, dépendance externe à isoler, ou unité devenue trop complexe pour être comprise/testée directement.
- Une abstraction doit réduire la complexité globale. Si elle ajoute des fichiers, des indirections ou un vocabulaire sans supprimer une difficulté réelle, ne pas la créer.
- Préférer une Policy Laravel pour une autorisation, une Form Request pour une validation HTTP, une Action pour un cas d'usage métier non trivial et un Eloquent scope pour une requête réutilisée. Ne pas dupliquer ces rôles.
- Éviter les composants Vue « universels » avec de nombreuses props conditionnelles. Extraire un composant quand une structure et un comportement sont effectivement réutilisés.
- Tout texte visible doit être ajouté aux catalogues Laravel français et anglais, organisés par feature métier avec `common.php` pour les libellés partagés. Dans Vue, utiliser `useTranslations`. Ne pas créer de catalogue TypeScript parallèle ni écrire de libellé visible directement dans le code.

## Revue de code

Avant toute fusion, vérifier :

1. La solution respecte-t-elle les conventions déjà présentes ?
2. Une version plus directe supprimerait-elle une couche ou une dépendance ?
3. Chaque nouveau fichier a-t-il une responsabilité nécessaire et explicite ?
4. Les tests couvrent-ils le comportement utile, sans sur-spécifier l'implémentation ?
5. La modification reste-t-elle strictement dans le périmètre documenté ?

Si une réponse est non, simplifier avant de fusionner.
