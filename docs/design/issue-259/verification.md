# Vérifications — 7 octobre 2026

Le dossier et son prototype sont les seuls ajouts. Aucune route, page Vue, traduction de production, dépendance, migration ou règle métier n’a changé. Les vérifications portent sur la proposition autonome, pas sur l’application Laravel.

## Contrôles du prototype

Commande reproductible avec le serveur local décrit dans le README :

```sh
node docs/design/issue-259/prototype/verify.mjs
bunx eslint docs/design/issue-259/prototype/*.js docs/design/issue-259/prototype/*.mjs
bunx prettier --check docs/design/issue-259/
```

Chromium vérifie **67 surfaces et 504 rendus** : les surfaces en FR/EN à 320/1440 px ; le modèle de composants dans six combinaisons thème/saison, deux langues, deux largeurs et neuf états ; puis des parcours et captures à 390 px. Aucun débordement horizontal du document ou du panneau ouvert, aucune erreur JavaScript. Les résultats détaillés sont dans [checks.json](captures/checks.json).

Les gestes vérifiés : consentement initial désactivé puis activé par la case ; tutoriel jusqu’à l’installation ; amorce qui préremplit et message envoyé ; discussion en lecture seule ; participants → profil → retour, historique navigateur et Échap ; profil passé en panneau ; confirmation de bannissement avec motif ; notifications marquées lues ; suppression des transitions avec mouvement réduit.

Les 30 paires de tokens texte/fond principales passent un ratio de 4,5:1 dans les six variantes. Ce contrôle n’est pas un certificat WCAG : il ne couvre pas toutes les superpositions, les couleurs hors tokens, les états système ou la production. Le focus et les noms accessibles sont présents ; le contrôle lecteur d’écran, les safe areas et le clavier virtuel doivent être effectués lors de l’intégration.

Six [captures](captures/) ont été inspectées visuellement pour la hiérarchie et la lisibilité. Les corrections portent sur la navigation à libellés courts, le retour des profils passés et la saisie de discussion en panneau. Les contrôles de revue sont repliables pour ne pas occuper l’écran applicatif. Les commandes de découverte restent au-dessus de la navigation sur téléphone.

## Contrôles de dépôt

ESLint est exécuté sur l’ensemble du dépôt ; Prettier sur le nouveau dossier et via le contrôle frontend habituel. Vérification des sources référencées dans le registre et des liens locaux du dossier. Les tests backend, build de production et Docker ne sont pas nécessaires pour un artefact isolé de conception ; aucun succès de ces suites n’est revendiqué.

## Limites explicites

- Données et gestes simulés, sans preuve des autorisations ou du comportement réseau de Laravel.
- Contenu légal présenté comme extrait de mise en page ; les textes juridiques actuels restent la référence.
- Les états génériques servent à valider le langage visuel ; chaque écran ne possède pas nécessairement tous ces états en production.
- Comparaison publié/soumis, mode d’inscription, retrait d’annonce et suppression illustrent les décisions, sans faire évoluer les règles.
- Les captures historiques de l’audit ne décrivent pas toutes le code courant ; l’audit le signale.
- Animation : pas de benchmark de production ni de nouvelle bibliothèque ; stratégie à évaluer après approbation.
- **Approbation du porteur du produit encore attendue.** La refonte n’est pas intégrée et l’issue reste ouverte.
