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
- **Réutilisation avant ajout** : rechercher systématiquement dans le projet ce qui répond déjà au besoin avant d'ajouter du code ou une dépendance. Réutiliser l'existant lorsqu'il convient afin de réduire les doublons et le coût de maintenance.
- **Une responsabilité claire** : chaque classe, composant et fonction doit avoir un rôle compréhensible sans lire tout le projet.
- **Tests ciblés** : tester les règles métier et les cas limites qui comptent, sans tester les détails d'implémentation ou la bibliothèque elle-même.
- **Pages publiques légères** : rendre côté serveur les pages indexables et ne charger Vue/Inertia ou du JavaScript que lorsqu’une interaction utile le nécessite.

## Recherche et réutilisation avant chaque ajout

Avant toute nouvelle fonctionnalité, composant, fonction ou dépendance :

1. Rechercher les parcours similaires et les éléments existants : composants Vue et UI, composables, fonctions utilitaires, actions Laravel, scopes Eloquent, policies, Form Requests, traductions et tests.
2. Lire leur implémentation et leurs usages pour vérifier qu'ils répondent au besoin, notamment en matière d'autorisations, d'accessibilité et de comportement.
3. Réutiliser l'élément adapté ou apporter une modification ciblée si elle reste cohérente avec ses usages actuels. Vérifier les parcours qui le partagent pour éviter une régression.
4. Créer un nouvel élément seulement si l'existant ne convient pas, et expliquer brièvement ce choix dans la pull request. Ne pas forcer une réutilisation qui ajouterait des conditions, des couplages ou une abstraction plus complexe que le besoin.

Cette recherche est obligatoire avant l'implémentation, pas seulement lors de la revue. L'objectif est d'obtenir le moins de code dupliqué possible tout en conservant une solution simple et lisible, conformément à KISS et YAGNI.

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
6. Une nouvelle page publique fournit-elle son contenu dans le HTML initial, ses variantes localisées et ses métadonnées sans charger de bundle applicatif inutile ?
7. Les performances, l’accessibilité et le SEO de chaque langue ont-ils été vérifiés sans régression significative ?
8. L'existant a-t-il été recherché et réutilisé lorsque possible, et chaque nouvel élément est-il justifié ?

Si une réponse est non, simplifier avant de fusionner.
