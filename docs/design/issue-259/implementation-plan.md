# Lots d’intégration après validation

Ce plan prépare l’intégration ; il ne l’autorise pas. Chaque lot part du code et des composants existants, avec PR vers `main`. Éviter une réécriture globale ou le transfert direct des renderers HTML du prototype.

| Lot                        | Périmètre et réemploi                                                                                                                                          | Preuve de fin                                                                                                         |
| -------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------- |
| 1 — Socle                  | Tokens sémantiques de `app.css`, primitives `ui/`, focus, typo, formulaires, dialogues et feedback. Comparer les valeurs saisonnières approuvées aux actuelles | Contrastes des paires actives ; clavier ; six combinaisons thème/saison ; motion réduit                               |
| 2 — Navigation             | `AppLayout`, layouts membre/partenaire/admin, `BottomNavigation`, Workspace, choix de langue et thème                                                          | Destinations selon rôle ; aucune entrée admin Notifications ; 320 px et multirôle ; libellé accessible et visible     |
| 3 — Public et premiers pas | Pages publiques/auth, création profil, onboarding existant, consentement analytics, PWA                                                                        | Consentement et majorité ; aides/errors ; FR/EN ; parcours Passer → Découvrir → échange → installation facultative    |
| 4 — Membre                 | SwipeCard, Discovery, Passed, MemberProfile ; conversations ; notifications ; réglages                                                                         | Règles de visibilité et disponibilité ; limite message ; likes et amorces ; signaler/bloquer ; export et suppression  |
| 5 — Événements             | Deux pages principales, EventDetail/Form/ParticipantList/Profile/Chat, demandes organisateur                                                                   | URL/back/scroll ; lieu privé ; états complet/accepté/annulé/J+7 ; autorisations et verrous H−24                       |
| 6 — Partenaire             | Publié/soumis, formulaires FR/EN, annonces, statistiques                                                                                                       | Statuts et révisions ; envoi après approbation ; retrait d’annonce ; agrégation sans identité destinataire            |
| 7 — Administration         | Tableau/cartes membres, catalogues, saisons, partenaires, signalements                                                                                         | Mobile et desktop ; motifs ; protections admin ; actions sensibles côté serveur ; aucune modification de règle métier |
| 8 — Finition               | Saisons, actifs, transitions et éventuelle bibliothèque justifiée                                                                                              | Mesure taille/CPU ; reduced motion ; aucun effet bloquant ; revue visuelle transversale                               |

## Vérification de chaque lot

Écrire/adapter les tests Pest de comportement avant un changement de comportement et constater le rouge pour la bonne raison. Réutiliser les tests existants et ne pas créer de tests qui vérifient seulement une classe CSS. Les tests navigateur doivent exercer les gestes, l’ordre des retours, les textes/états visibles et le focus après fermeture. Tester côté serveur les permissions, pas seulement l’absence de boutons.

Pour les interactions du prototype, prévoir lors de l’intégration : navigation clavier et lecteur d’écran, focus piégé/restauré dans les panneaux, clavier virtuel iOS/Android, safe areas, zoom 200 %, nom/bio/messages longs, messages réseau différés ou échoués, contraste des textes et focus dans chaque saison. Valider que les contrôles simulés ne survivent pas dans Vue.

Exécuter les tests ciblés puis les contrôles pertinents de `AGENTS.md` : Wayfinder, lint, format, types, build et tests frontend pour les lots Vue ; Pint/PHPStan/Pest si PHP change ; `composer ci:check` pour la validation transversale avant fin d’intégration. Le build Docker est séparé et nécessaire seulement au contrôle de l’image. Rapporter les sorties et les limites ; ne pas déclarer la performance mesurée à partir du prototype.

## Bornes de périmètre

Aucune nouvelle règle de matching, paiement, fonction romantique, photo upload, messagerie administrative ou nouvelle API. Toute contradiction produit/documentation appelle une validation avant code. L’adoption d’une bibliothèque d’animations reste une décision technique argumentée dans la PR du lot, après validation visuelle.
