# Tableau de bord d’usage — Implementation Plan

> Exécution native dans cette session, autorisée par « Vas-y fait ça ».

**Goal:** Enrichir `/dashboard` avec des statistiques exploitables à partir des seules données existantes.

**Architecture:** Une Form Request valide les dates UTC (30 jours par défaut, maximum 366 jours). Un rapport SQL agrégé calcule les séries quotidiennes, la période précédente de même durée, l’activation de la cohorte inscrite et des instantanés actuels. Vue réutilise les composants admin et affiche des courbes SVG accessibles avec leurs données tabulaires. Aucune dépendance ou collecte supplémentaire.

**Tech Stack:** Laravel 13, MySQL 8.4, Inertia 3, Vue 3, TypeScript, Pest/Playwright.

## Contrat et limites

- Usage social : comptes ayant le rôle `user`, hors administrateurs ; les échanges d’assistance sont exclus des matchs et messages privés.
- Les actions sont datées par `created_at` ; les swipes sont des décisions actuellement conservées, pas un journal de gestes. Les annonces système des groupes sont exclues.
- Les volumes portent sur les données encore conservées et les rôles actuels. Les suppressions et modifications peuvent changer les résultats historiques.
- « Membres contributeurs » signifie au moins une action enregistrée (découverte, message ou création d’activité), pas une visite ni une connexion. Aucun calcul de rétention ou de durée de session.
- Activation : état actuel des inscrits de la période, étapes imbriquées ; pas de prétendue chronologie des conversions.
- Communauté et intérêts : état actuel indépendant du filtre. Participation : inscriptions créées pendant la période, quel que soit leur état actuel ; aucune affirmation de présence physique.
- Comparaison avec la période immédiatement précédente de même durée ; pas de pourcentage lorsque la base précédente est nulle.
- Accès admin existant conservé, FR/EN, graphiques accompagnés d’un tableau, petits écrans et thèmes existants.

## Review Focus

- Dates invalides, inversées, futures ou dépassant 366 jours : refus avant les requêtes.
- Jours sans action et base de comparaison nulle : zéros et absence de pourcentage trompeur.
- Comptes administrateurs, partenaires seuls et messages système : exclusion des métriques d’usage.
- Messages privés et identifiants : seuls des agrégats entrent dans le rapport.
- Cohorte et instantanés : libellés explicites, pas de DAU/rétention inventés.

## Exécution

### 1. Rapport backend
- Ajouter `tests/Feature/Admin/UsageDashboardTest.php` en Pest : bornes inclusives/exclusives, comparaison, autorisation, exclusions, déduplication, activation et absence de contenu privé.
- Vérifier le rouge sur MySQL isolé avec `php tests/Support/verify-test-database.php`, puis `php artisan test tests/Feature/Admin/UsageDashboardTest.php`.
- Ajouter `app/Http/Requests/Admin/DashboardRequest.php`, `app/Support/AdminUsageStatistics.php` et brancher le rapport dans `DashboardController` ; conserver ses props historiques.
- Vérifier le vert et `tests/Feature/DashboardTest.php`.

### 2. Interface
- Ajouter un parcours Pest Browser dans `tests/Browser/UsageDashboardTest.php` : filtres, tables de données, zéro, mobile/desktop et anglais.
- Enrichir `Dashboard.vue`, ajouter `components/admin/UsageChart.vue` (courbe multi-séries et tableau) et `types/usage-statistics.ts`.
- Ajouter le catalogue `usage.php` FR/EN via le mécanisme existant ; mettre à jour PRD et documentation des définitions.
- Générer Wayfinder, vérifier lint, format, types et build ; exécuter les parcours navigateur.

### 3. Vérification finale
- Lint PHP et analyse statique ; suites pertinentes puis suite complète si disponible, sans concurrence MySQL.
- Vérifier le diff, l’accessibilité des données et le rendu desktop/mobile. Signaler les contrôles indisponibles et les limites réelles.
