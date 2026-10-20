# Continuité de la messagerie — issue 280

**Objectif approuvé :** retrouver des messages, aperçus et compteurs à jour après navigation ou veille, avec un historique utilisable pendant la saisie.

**Architecture :** conserver les composants partagés des échanges privés et de groupe. Réconcilier les échanges privés depuis leur route Laravel autorisée, indépendamment de la livraison Reverb. Dimensionner les conversations plein écran avec VisualViewport sans modifier les métadonnées viewport.

**Contraintes :** traductions français/anglais, autorisations existantes, historique paginé et messages dédupliqués, aucune nouvelle dépendance. Préserver les modifications de l’utilisateur dans le checkout principal.

## Vérifications et réalisation

- [x] Reproduire avec Pest les aperçus périmés et le rattrapage de plus d’une page après veille.
- [x] Ajouter une propriété Inertia optionnelle `messageUpdates` et fusionner les messages par identifiant.
- [x] Réconcilier à la navigation, à la reprise, au retour réseau et périodiquement lorsque l’écran est visible.
- [x] Reproduire le passage du champ sous le clavier et l’envoi accidentel pendant une composition IME.
- [x] Conserver le focus pendant l’envoi, masquer les suggestions après le premier message et respecter la composition IME.
- [x] Vérifier le suivi du bas du fil, la conservation de la position de lecture et le bouton « Derniers messages » pendant les changements de hauteur.
- [x] Exécuter les contrôles complets concernés, inspecter le rendu mobile et demander une revue indépendante.

## Points de revue

Retour navigateur avec état Inertia restauré ; reconnexion sans événement reçu ; absence dépassant dix messages ; redimensionnement pendant la lecture d’anciens messages ; clavier et focus pendant un envoi lent ou échoué. Les tests Chromium simulent la géométrie du clavier ; le comportement natif iOS reste à vérifier sur appareil.

## Résultats de revue

Deux régressions corrigées avant livraison : le curseur de rattrapage ne suit pas les événements temps réel, qui peuvent arriver hors ordre ; les rechargements Inertia fusionnent l’historique par identifiant pour éviter l’accumulation de doublons. Le test couvre également le retour arrière après récupération de 25 messages et la rédaction du message suivant pendant un envoi retardé. La revue indépendante ne relève plus de défaut bloquant confirmé.

## Validation

Les contrôles lint/format/TypeScript, Pint, Larastan, Wayfinder, les 137 tests frontend et le build Vite passent. La suite Pest complète termine avec 1 559 tests réussis et six ignorés ; le build Docker de production passe. Les régressions de messagerie ajoutées passent sous Chromium et le rendu mobile a été inspecté. Le clavier natif iOS reste à vérifier sur appareil.

La première CI a exposé une réaction écrasée par un snapshot retardé, reproduite avec une réponse XHR retenue puis corrigée : la synchronisation préserve les réactions et accusés de lecture reçus pendant la requête, y compris pour un message apparu entretemps. Le test de retour attend désormais l’URL de la liste avant de revenir au fil. La vérification CI finale reste consultable dans la pull request.
