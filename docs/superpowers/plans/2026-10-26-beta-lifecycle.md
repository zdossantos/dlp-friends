# Cycle de vie manuel de la bêta — issue 306

Spec: `docs/superpowers/specs/2026-10-26-beta-lifecycle-design.md`.
Décision validée : administrateur dédié au mode référentiels, identifiants privés sur le serveur.

## Contraintes globales
Réutiliser Operations/Release et leurs vérifications. Aucun import ni arrêt réel dans cette session.
Sérialiser toutes les opérations ; un échec laisse la bêta fermée. Aucun secret dans Actions.

## Task 1 — Administrateur dédié
Tester le refus hors bêta fermée, la validation du fichier privé et le refus d'un compte préexistant.
Créer une commande d'amorçage sans notification, sans données personnelles de production.
Expected: tests ciblés verts après un échec initial de commande inexistante.

## Task 2 — Copie des référentiels
Ajouter un mode explicite à la copie existante ; schéma complet mais données sur liste fermée.
Copier uniquement les objets référencés ; vérifier les clés étrangères et l'absence de membres.
Conserver les tests du mode intégral et interdire une répétition production sur référentiels.
Expected: scénarios Python existants et nouveaux verts après RED.

## Task 3 — Orchestration privée
Étendre le relais SSH avec actions fixes update/initialize-production/initialize-references/stop.
Réutiliser le verrou Operations, vérifier les ressources et attendre Coolify ; état durable et fermeture sur échec.
Tester préconditions, ordre des effets, reprise après arrêt, échecs et protection production.
Expected: tests du relais et de l'orchestration verts après RED.

## Task 4 — Actions et documentation
Workflow bêta manuel uniquement, entrées action et confirmation ; workflow stop distinct et même concurrence.
Revalider le candidat avant toute mutation et utiliser le digest existant. Documenter installation et limites.
Expected: lint PHP, analyse, tests infrastructure, contrôles des workflows et build concernés réussis.

## Review Focus
Protection des ressources partagées, état partiellement copié, maintenance Redis après arrêt,
absence de fuite de secrets, absence d'ouverture si migration/copie/déploiement échoue.
