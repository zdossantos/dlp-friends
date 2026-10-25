# Profile Passes Implementation Plan

> **For agentic workers:** Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Enregistrer un pass précis et Cast Member, et les afficher sur les profils autorisés.

**Architecture:** Deux attributs profiles, validation partagée, sérialisation existante. Composants NativeSelect/Checkbox et SVG original dans ProfilePresentation.

**Tech Stack:** Laravel 13, Pest, Vue 3, TypeScript, Reka UI.

**Spec:** docs/superpowers/specs/2026-10-25-profile-passes-design.md

## Global Constraints

- FR/EN, aucun droit applicatif, aucune dépendance nouvelle.
- Aucun pass et Cast Member faux par défaut, ancien Bronze distinct.
- Pas de visuel Disney copié ; cartes de découverte inchangées.

## Review Focus

- Champs absents dans un ancien client : conserver le choix courant.
- Valeurs falsifiées : refuser sans modifier les données.
- Blocage et profil indisponible : ne pas exposer les statuts.
- Les cinq types restent distincts et Cast Member est cumulable.
- À 320 px : visuel lisible sans collision avec actions ou liens sociaux.

### Task 1: Persistence et autorisations

Files: app/Enums/DisneylandPass.php, database/migrations/*add_pass_status_to_profiles.php, app/Models/Profile.php, app/Http/Requests/MemberProfileRequest.php, app/Http/Controllers/MemberProfileController.php, app/Data/PublicMemberData.php, app/Actions/BuildUserDataExport.php, tests/Feature/ProfilePassTest.php.

Interface: disneyland_pass nullable (bronze_one, bronze_max, silver, gold, bronze), is_cast_member boolean. Champs optionnels dans les requêtes ; champs absents préservés. Données publiques absentes en cas de blocage.

- [x] Écrire les tests Pest : chaque pass, création/modification/retrait, absence, invalides, cumul sans droits, affichage/masquage, export/purge.
- [x] Exécuter php artisan test tests/Feature/ProfilePassTest.php ; constater les échecs fonctionnels avant implémentation.
- [x] Ajouter migration, enum, casts/règles et champs des réponses existantes.
- [x] Exécuter tests ciblés et tests de profil existants.

### Task 2: Formulaire et visuels

Files: resources/js/types/{auth,member}.ts, resources/js/components/profile/{ProfileForm,ProfilePassCard,ProfilePresentation}.vue, tous les appelants de ProfilePresentation, lang/{fr,en}/profile.php, tests/Browser/ProfilePassTest.php, docs/{PRD,data-model,design-system,security-privacy}.md.

Interface: disneylandPass optionnel nullable, isCastMember optionnel faux ; passOptions fournis par le contrôleur au formulaire partagé.

- [x] Écrire le parcours navigateur (saisie, cumul, modification/retrait, FR/EN, mobile clair/sombre), constater l'absence du sélecteur.
- [x] Ajouter contrôles, SVG, traductions et props de tous les profils détaillés.
- [x] Vérifier parcours navigateur et largeur/positions du visuel.
- [x] Mettre à jour documentation ; lancer lint PHP/JS, analyse, types, build et suite complète pertinente.
- [ ] Relire le diff puis commit et PR vers main ; attendre les checks requis.
