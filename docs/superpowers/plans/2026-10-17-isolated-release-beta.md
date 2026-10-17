# Bêta isolée et livraison contrôlée — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Livrer une bêta privée suivant la Release PR, avec données isolées, identité visuelle distincte et répétition obligatoire des migrations avant production.

**Architecture:** Garder les quatre processus applicatifs et les scripts GHCR/Coolify existants. Ajouter un Compose bêta autonome, une vérification Laravel du schéma et des opérations serveur explicites pour les copies et livraisons ; GitHub ne reçoit jamais les données copiées. La publication d'une release reste automatique, son déploiement et sa réouverture deviennent contrôlés.

**Tech Stack:** PHP 8.4, Laravel 13, Inertia 3, Vue 3, TypeScript, Bun 1.3.14, Pest/Chromium, MySQL 8.4, Redis 7.4, Garage, Mailpit, Docker Compose, GitHub Actions/GHCR, Coolify/Traefik.

**Spec:** `docs/superpowers/specs/2026-10-17-isolated-release-beta-design.md` — approuvée le 17 octobre 2026. Lire aussi `AGENTS.md`, `avatar.md` et les documents d'exploitation référencés dans la spécification.

## Global Constraints

- Utiliser `https://beta.dlp-friends.fr` et un hôte WebSocket distinct `https://ws-beta.dlp-friends.fr`.
- Aucun nouveau VPS payant n'est nécessaire.
- `APP_DEBUG=false` et protections des commandes destructives équivalentes à la production malgré `APP_ENV=beta`.
- Aucune clé Resend de production et aucun envoi réel depuis la bêta.
- Les migrations sont manuelles, conformément à `AGENTS.md` et `docs/operations.md`.
- Ne jamais exécuter du code de PR non fiable avec des secrets de déploiement.
- Avant chaque livraison, répéter la mise à niveau sur une copie de production créée depuis moins de 24 heures.
- Un rollback d'image ne restaure pas les données.
- Les textes visibles et noms accessibles viennent des catalogues existants.
- Conserver les changements locaux préexistants hors des commits de ce travail.

## Review Focus

- Une cible MySQL désignée par un autre nom DNS pointe sur la production : refuser avant toute écriture (tâche 4).
- Un ancien workflow termine après un nouveau candidat : aucune mutation Coolify pour le SHA dépassé (tâche 5).
- Une base indisponible ou sans table `migrations` ne doit jamais être considérée comme à jour (tâche 1).
- Un rapport récent concerne un autre contenu après squash, ou le schéma source a changé : refuser la livraison (tâche 6).
- Une URL directe de média, une connexion WebSocket ou un en-tête IP forgé contourne la page de connexion : refuser l'accès en amont (tâche 8).

---

## Carte des fichiers et interfaces communes

| Fichiers | Responsabilité |
| --- | --- |
| `app/Support/DeploymentReadiness.php`, `app/Console/Commands/CheckDeployment.php`, `app/Http/Middleware/EnsureDeploymentReady.php`, `config/deployment.php` | Schéma courant, configuration bêta sûre et fermeture si non prêt. |
| `app/Console/Commands/SanitizeBetaCopy.php` | Neutralisation ciblée d'une copie fermée, sans utiliser les clés de production. |
| `compose.production.yaml`, `compose.beta.yaml`, `docker/php/entrypoint.sh`, `.env.beta.example` | Processus, ressources et variables séparés ; aucune migration automatique. |
| `resources/js/components/AppLogoIcon.vue`, `app/Http/Middleware/HandleInertiaRequests.php`, `resources/js/types/index.ts`, `resources/views/components/brand-head.blade.php`, `public/beta/`, `lang/{fr,en}/brand.php` | Marque bêta décidée à l'exécution et PWA distincte. |
| `.github/workflows/beta.yml`, `.github/scripts/select-beta-candidate.sh`, `.github/scripts/deploy-beta.sh` | Sélection fiable, build validé et déploiement du candidat courant. |
| `.github/workflows/release-please.yml`, `.github/scripts/deploy-release.sh` | Publication de release sans livraison implicite ; transport Coolify réutilisable après autorisation serveur. |
| `scripts/operations/beta-refresh.sh`, `scripts/operations/production-release.sh`, `scripts/operations/lib.sh`, `scripts/operations/config.example.json` | Opérations explicites sur le serveur ; contrôle des identités, sauvegardes et rapports. |
| `tests/Feature/Infrastructure/{BetaDeployment,ReleaseDeployment,BetaRefresh,ProductionRelease}Test.php`, `tests/Support/coolify-api-router.php`, `tests/Support/github-api-router.php`, `tests/Support/operations/` | Tests Pest des scripts contre services/commandes locaux simulés. |
| `tests/Feature/{DeploymentReadiness,BetaEnvironment,BetaCopySanitization,BrandAssets,PwaManifest}Test.php`, `tests/Browser/BetaBrandingTest.php` | Comportements applicatifs et isolation observable. |
| `docs/beta-environment.md`, `docs/operations.md`, `docs/quality-ci-cd.md`, `docs/PRD.md`, `docs/design-system.md` | Procédures exactes et état réellement livré. |

Les nouveaux noms de fichiers sont des décisions de ce plan. Vérifier les fichiers/types existants avant ajout et étendre leurs structures actuelles ; ne pas créer un second client Coolify ni une seconde primitive de logo.

Les scripts serveur consomment `--config /chemin/prive/config.json`, fichier opérateur `0600` hors dépôt. Il contient les UUID application/MySQL/Redis source et cible, noms des bases/buckets, chemins de fichiers clients MySQL/rclone, répertoire privé des rapports et référence au jeton Coolify. Aucun secret n'est un argument CLI, une sortie JSON ou une variable GitHub publique. `lib.sh` expose `validate_targets CONFIG`, `schema_fingerprint CONFIG source|beta`, `migration_fingerprint IMAGE`, `request_coolify METHOD PATH BODY_FILE` ; les fonctions renvoient leur résultat public sur stdout, échouent avec un code non nul sans corps API privé. L'empreinte de schéma trie les définitions et normalise noms de base et compteurs `AUTO_INCREMENT`, sans masquer colonnes/index/contraintes ; une simple insertion ne l'invalide pas. L'empreinte des migrations SHA-256 couvre chemins triés et octets de tous les fichiers de migration présents dans l'image.

## Tâche 1 : fermer les parcours quand le schéma ou la configuration sont dangereux

**Fichiers :** créer les quatre fichiers `DeploymentReadiness`/`CheckDeployment`/`EnsureDeploymentReady`/`config/deployment.php` de la carte et `tests/Feature/DeploymentReadinessTest.php`, `tests/Feature/BetaEnvironmentTest.php` ; modifier `bootstrap/app.php`, `app/Providers/AppServiceProvider.php`, `app/Http/Middleware/PreventSearchIndexing.php`, `config/services.php`, `routes/web.php`, les composants Blade `analytics-consent` et `google-tags`.

**Interfaces :** `DeploymentReadiness::assertReady(bool $allowMaintenance = false): void`, lève une exception publique générique en cas d'échec ; `CheckDeployment` expose `deployment:check {--allow-maintenance} {--json}` avec code 0 seulement si prêt. JSON : `{ready: bool, reason: string, pending: list<string>}` sans coordonnées ni erreurs SQL. `EnsureDeploymentReady::handle(Request $request, Closure $next): Response` rend 503 sans détails pour production/beta, hors `/up` ; les environnements local/testing gardent leur comportement.

- [ ] Écrire `rejects_missing_migrations_table`, `rejects_unreachable_database`, `rejects_pending_migration`, `accepts_current_schema`, `keeps_up_independent_of_database` : assertions codes 1/503 pour les trois premiers, 0/200 pour les suivants. Utiliser une connexion MySQL de test dédiée pour les scénarios destructifs, jamais retirer des tables de la connexion partagée des autres tests.
- [ ] Écrire `beta_keeps_production_password_and_destructive_command_guards`, `beta_rejects_external_mail_or_resend_key`, `beta_is_not_indexable_or_tracked` : mots de passe identiques à production, interdiction destructive, refus de mail hors SMTP Mailpit dédié, absence de scripts GA4/Search Console malgré variables injectées ; `/robots.txt` contient `Disallow: /`, toutes les réponses bêta portent `X-Robots-Tag`.
- [ ] Exécuter `php artisan test tests/Feature/DeploymentReadinessTest.php tests/Feature/BetaEnvironmentTest.php` ; constater les échecs correspondant aux protections absentes.
- [ ] Implémenter avec le Migrator Laravel et sa repository, sans traiter une erreur de connexion comme une liste vide. Vérifier la maintenance Redis ; `--allow-maintenance` ignore seulement la maintenance. La configuration bêta impose SMTP Mailpit, Redis dédié, cookies hôte et débogage désactivé ; comparer les identités réelles des ressources dans les opérations serveur, pas dans un fichier PHP contenant des secrets de production. Les traductions utilisent les catalogues FR/EN. Installer le middleware avant les parcours accédant au schéma, sans changer la sonde `/up`.
- [ ] Rejouer les deux fichiers de test ; attendu PASS. Vérifier aussi les tests existants d'authentification et d'indexation touchés, puis `composer lint:check` et `composer analyse`.
- [ ] Commiter seulement ces fichiers : `feat: protéger les environnements déployés et vérifier leur schéma`.

## Tâche 2 : identité bêta du logo, du navigateur et de la PWA

**Fichiers :** fichiers de marque de la carte, `vite.config.ts`, `docker/nginx/default.conf`, `tests/Feature/BrandAssetsTest.php`, `tests/Feature/PwaManifestTest.php`, `tests/Feature/Infrastructure/PwaProductionTest.php`, nouveau `tests/Browser/BetaBrandingTest.php`.

**Interfaces :** prop Inertia `environment.isBeta: boolean` issue de `app()->environment('beta')` ; traduction `brand.beta_badge` = `Bêta`/`Beta`, noms PWA traduits `brand.beta_app_name`. Les liens Blade sélectionnent `/beta/favicon.svg`, `/beta/favicon.ico`, `/beta/apple-touch-icon.png`, `/beta/app.webmanifest` seulement en bêta. Le manifeste possède `id: '/beta'`, `start_url: '/app'`, `scope: '/'`, noms identifiables et quatre icônes `/beta/pwa/icon-{192,512}.png`, `/beta/pwa/icon-maskable-{192,512}.png`.

- [ ] Écrire les assertions bêta/production FR/EN sur les liens de `<head>` et la prop ; vérifier les dimensions PNG/ICO existantes, l'existence des quatre icônes et la zone sûre maskable. Ajouter le parcours Chromium `shows_beta_badge_in_each_theme` avec clair/sombre/Halloween et `does_not_badge_production`.
- [ ] Exécuter `php artisan test tests/Feature/BrandAssetsTest.php tests/Feature/PwaManifestTest.php tests/Browser/BetaBrandingTest.php` ; attendu échec sur assets/prop/badge absents.
- [ ] Ajouter dans le composant existant un petit badge en bas à droite, fond blanc, contour et texte sombres, sans masquer le logo saisonnier et sans doubler son nom accessible. Générer les SVG/PNG/ICO depuis les SVG existants avec l'outillage disponible, sans image IA ni nouvelle dépendance applicative ; le raster porte `BETA`. Servir le manifeste bêta sans cache long ; inclure les assets bêta dans les règles PWA appropriées. Le choix reste serveur, sans `VITE_APP_ENV` figé dans l'image.
- [ ] Rejouer les tests ciblés et `php artisan test tests/Feature/Infrastructure/PwaProductionTest.php tests/Browser/AppearanceTest.php` ; attendu PASS. Inspecter visuellement les icônes à 16/32/180/192/512 px et les captures synthétiques.
- [ ] Commiter : `feat: identifier la bêta dans les logos et la PWA`.

## Tâche 3 : décrire les ressources bêta séparées et le démarrage fermé

**Fichiers :** `compose.beta.yaml`, `.env.beta.example`, `compose.production.yaml`, `docker/php/entrypoint.sh`, `tests/Feature/Infrastructure/BetaDeploymentTest.php`, `tests/Feature/Infrastructure/ProductionDeploymentTest.php`.

**Interfaces :** `compose.beta.yaml` reprend web/worker/scheduler/reverb utilisant un `APP_IMAGE` par digest obligatoire et `APP_ENV=beta`, avec MySQL/Redis/Garage/Mailpit externes dédiés. `APP_MAINTENANCE_DRIVER=cache`, `APP_MAINTENANCE_STORE=redis` et préfixe stable propre à chaque environnement. Pas de `ports` publiés sur les processus privés. Les commandes worker/scheduler/reverb attendent un `deployment:check` réussi avant lancement ; aucune commande `migrate` n'est ajoutée à l'entrypoint.

- [ ] Écrire les assertions de Compose rendu : quatre processus, aucune dépendance/clé Resend, SMTP privé, debug false, sessions Redis hôte seulement, variables Reverb et stockage bêta explicites. Valeurs manquantes image/DB/Redis/bucket font échouer le rendu ; aucun fallback vers une ressource de production.
- [ ] Ajouter un test de l'entrypoint avec Artisan simulé : readiness code 1 => aucun worker/scheduler/reverb lancé ; code 0 => commande habituelle ; aucun appel de migration. La commande web reste capable de servir `/up` sans DB.
- [ ] Exécuter `php artisan test tests/Feature/Infrastructure/BetaDeploymentTest.php tests/Feature/Infrastructure/ProductionDeploymentTest.php` ; attendu échec pour Compose et garde absents.
- [ ] Implémenter sans abstraction Compose partagée nécessitant un support Coolify supplémentaire. Budgets initiaux bêta : web 0.5 CPU/256 Mio, worker 0.25 CPU/192 Mio, scheduler 0.1 CPU/128 Mio, Reverb 0.25 CPU/128 Mio ; confirmer le démarrage réel avant de les conserver. Utiliser un délai borné puis échec/restart pour l'attente readiness. Garder la maintenance identique entre deux images et la configuration production actuelle hors changements requis pour la garde.
- [ ] Rejouer les tests ciblés ; attendu PASS. Exécuter `docker build --target runtime --tag dlp-friends:ci .` et `bash .github/scripts/smoke-image.sh dlp-friends:ci` ; attendu image saine sans base et assets présents.
- [ ] Commiter : `feat: ajouter le déploiement bêta isolé`.

## Tâche 4 : copier la production sans recopier ses effets de bord

**Fichiers :** `app/Console/Commands/SanitizeBetaCopy.php`, `tests/Feature/BetaCopySanitizationTest.php`, `scripts/operations/{beta-refresh.sh,lib.sh,config.example.json}`, `tests/Feature/Infrastructure/BetaRefreshTest.php`, fixtures `tests/Support/operations/`, `docs/beta-environment.md`.

**Interfaces :** `beta:sanitize-copy {--force}` exige beta, maintenance et confirmation `--force`, puis supprime sessions/jetons de réinitialisation/abonnements Push/jobs restaurés dans les tables réellement présentes ; aucune clé privée n'est affichée. `beta-refresh.sh --config FILE --replace-beta-tests` réalise seulement la copie/neutralisation, sans migration ni réouverture ; sans ce drapeau, aucune mutation. Rapport privé `snapshot.json` : `created_at`, `source_schema_sha256`, `source_migrations_sha256`, `tables_verified`, `objects_verified`, `sanitized`, identifiant aléatoire du snapshot ; aucune ligne métier.

- [ ] Écrire `refuses_source_target_alias` avec deux DNS et la même paire MySQL `@@server_uuid`/base ; attendre code non nul et zéro import/suppression. Tester aussi même UUID/volume, bucket identique, compte bêta capable d'écrire dans le bucket source, remplacement sans drapeau explicite et processus bêta encore actifs. Aucun de ces cas ne peut commencer un effacement. Tester l'égalité des empreintes malgré un compteur `AUTO_INCREMENT` différent et leur différence après ajout d'une colonne.
- [ ] Écrire `preserves_profiles_messages_and_files_but_removes_delivery_credentials` avec fixtures synthétiques : profils/messages inchangés, push chiffré supprimé sans déchiffrement, sessions/tokens/jobs absents. Inventorier les colonnes chiffrées, passkeys/OAuth, préférences de notification et URL sortantes avant de définir la liste finale ; couvrir tout nouvel effet de bord trouvé. Les identifiants OAuth et autres jetons utilisables sont neutralisés, les mots de passe peuvent rester pour les parcours derrière l'accès privé.
- [ ] Exécuter `php artisan test tests/Feature/BetaCopySanitizationTest.php tests/Feature/Infrastructure/BetaRefreshTest.php` ; attendu échecs commande/script absents.
- [ ] Implémenter le script avec `set -euo pipefail`, `umask 077`, verrou opérationnel par environnement et fichiers clients protégés. Refuser les alias avec identités serveur/base, UUID/volumes et droits bucket vérifiés. Exiger bêta fermée, worker/scheduler/Reverb arrêtés et drainés ; n'effacer une ancienne copie qu'après validation et `--replace-beta-tests`. Export cohérent MySQL 8.4 via la procédure existante, restauration dans cible vide, comparaison schéma/comptages avant neutralisation, copie rclone vers bucket dédié privé, inventaire objets avec détection des fichiers manquants. Un fichier source supprimé pendant copie interdit d'annoncer une copie complète. Aucun `migrate:fresh`, aucune copie Redis, aucun dump sur GitHub.
- [ ] Rejouer les deux fichiers ; attendu PASS, y compris import incomplet, comparaison incorrecte, interruption et secret présent dans un corps d'erreur. La bêta reste fermée sur tous les échecs. Documenter la commande exacte et l'avertissement « remplace les essais bêta » avant usage réel.
- [ ] Commiter : `feat: préparer une copie bêta contrôlée de la production`.

## Tâche 5 : suivre exclusivement la Release PR courante

**Fichiers :** `.github/workflows/beta.yml`, `.github/scripts/{select-beta-candidate.sh,deploy-beta.sh}`, tests `BetaDeploymentTest.php`, fixtures GitHub et Coolify de la carte.

**Interfaces :** `select-beta-candidate.sh` utilise `GITHUB_REPOSITORY`, `GH_TOKEN` lecture seule et produit `{number, sha, tree, head_ref}` ou `null`. Vérifier `main`, même dépôt, auteur réellement utilisé par Release Please, branche `release-please--branches--main--components--dlp-friends`, label Release Please observé et état ouvert non draft. Ne pas figer #268. `deploy-beta.sh` consomme ce JSON, `APP_IMAGE`, `COOLIFY_BETA_APPLICATION_UUID`, `COOLIFY_PRODUCTION_APPLICATION_UUID` et refuse UUID identiques ; le transport reprend la séquence PATCH image, PATCH commit, POST deploy existante.

- [ ] Écrire `rejects_untrusted_or_maintenance_pr`, `waits_for_checks_on_exact_head`, `ignores_stale_build_before_any_coolify_mutation`, `retains_beta_when_no_release_pr` et `never_targets_production`. Fixtures couvrent fork, auteur/label incorrect, `release/1.14`, checks verts d'un autre SHA, CI échouée et deux workflows arrivés dans l'ordre inverse. Attendre zéro mutation Coolify pour chaque refus.
- [ ] Exécuter `php artisan test tests/Feature/Infrastructure/BetaDeploymentTest.php` ; attendu échecs sur sélection/scripts manquants.
- [ ] Déclencher le workflow par `workflow_run` de CI terminé et par `workflow_dispatch` de rattrapage. Le workflow de confiance vient de `main` ; ses scripts sont lus depuis cette référence, le candidat uniquement dans un répertoire de build distinct. Revérifier par API dépôt/auteur/branche/labels et les six checks requis exacts : Conventional PR title, PHP quality, Backend tests, Frontend quality, Vite build, Docker build (confirmer les noms actuels de protection avant activation). Ne pas exécuter une commande de la PR dans le job possédant le jeton Coolify.
- [ ] Construire AMD64 et publier `ghcr.io/zdossantos/dlp-friends` avec tag technique `beta-<sha>`, labels commit/tree, arguments publics Vite bêta puis smoke par digest ; conserver les pins d'actions existants. Exposer le jeton package uniquement au job de build d'un candidat vérifié ; le job de déploiement n'exécute que les scripts de confiance. Concurrence `beta-deploy` sans annulation d'une mutation en cours ; resélectionner juste avant la première mutation, refuser un SHA ancien. Le transport n'effectue aucun retry de mutation et n'imprime aucun corps privé. Après démarrage, la garde de tâche 1 ferme les parcours et celle de tâche 3 empêche les traitements si des migrations attendent ; afficher ce statut opérateur, sans considérer `/up` comme preuve d'aptitude. Le workflow ne lance pas `migrate` et ne lève jamais une maintenance opérateur.
- [ ] Rejouer les tests locaux et cas d'erreur API de `ReleaseDeploymentTest.php` ; attendu PASS. Vérifier les permissions minimales du workflow, sans secret production ni utilisation dangereuse de `pull_request_target`.
- [ ] Commiter : `feat: déployer en bêta la Release PR validée`.

## Tâche 6 : lier la répétition au contenu livré et empêcher la livraison implicite

**Fichiers :** `scripts/operations/{production-release.sh,lib.sh}`, `.github/workflows/release-please.yml`, `.github/scripts/deploy-release.sh`, tests `ProductionReleaseTest.php` et `ReleaseDeploymentTest.php`, `docs/operations.md`, `docs/quality-ci-cd.md`.

**Interfaces :** `production-release.sh --config FILE rehearse|prepare|deploy|verify|resume --image DIGEST --commit SHA`. `rehearse` utilise le snapshot de tâche 4 et un lancement ponctuel de l'image bêta avec ses seules ressources ; c'est l'action opérateur explicite de migration. `prepare` ferme et sauvegarde ; `deploy` vérifie l'autorisation puis demande Coolify ; `verify` contrôle sans migrer ; `resume` requiert vérification et confirmation opérateur. La migration production est une commande documentée séparée `php artisan migrate --force` dans l'image précise, jamais un effet de `deploy`.

Rapport `rehearsal.json` protégé hors conteneur bêta : `snapshot_id`, `snapshot_created_at`, `candidate_commit`, `candidate_tree`, `candidate_image`, `migration_sha256`, `source_schema_sha256`, `applied_migrations`, `second_run_noop`, `journeys_passed`, `completed_at`, `status`. Un rapport incomplet n'est pas réussi. La correspondance après squash exige l'égalité du tree Git complet entre candidat et release et celle de l'empreinte des migrations contenues dans l'image publiée ; les SHA de commit peuvent différer. Les arguments Vite/Reverb diffèrent légitimement par environnement.

- [ ] Écrire les refus avant mutation : rapport absent/échoué, snapshot âgé de 24 h ou plus, date future, tree ou empreinte différents, schéma source changé, image mutable, mauvaise plateforme, processus non arrêtés, maintenance absente ou sauvegarde finale non vérifiée. Ajouter le succès « SHA différents, tree égal » et le refus « SHA cohérent mais tree différent ».
- [ ] Écrire `release_publication_does_not_deploy_production` et le parcours simulé « échec migration/readiness => maintenance conservée, aucun resume, aucun rollback ». Les appels directs de l'ancien script sans autorisation ne déploient plus. Tester qu'un timeout Coolify n'est ni retenté ni interprété comme succès.
- [ ] Exécuter `php artisan test tests/Feature/Infrastructure/ProductionReleaseTest.php tests/Feature/Infrastructure/ReleaseDeploymentTest.php` ; attendu échecs sur les protections absentes.
- [ ] Implémenter le rapport de répétition : relever schéma avant, appliquer les migrations dans la copie, enregistrer durée/résultat, relancer et exiger zéro migration, puis exiger les parcours connexion/profil existant/découverte/message temps réel/fichier/mail capturé/file. Les parcours réels sont attestés par l'opérateur dans le rapport privé ; aucune case ne se valide par défaut. Refuser une production dont le schéma n'est plus celui du snapshot. Capturer source schema à nouveau immédiatement avant fermeture/livraison.
- [ ] Retirer uniquement l'appel automatique Coolify du workflow Release Please, conserver publication et pièce jointe `container-image.json`. Ajouter aux images bêta/release les labels `io.dlp-friends.source-tree` et `io.dlp-friends.migrations-sha256`, calculés depuis le checkout exact et les fichiers embarqués ; les inclure dans la référence de publication. Retirer le secret de livraison des jobs de publication. Encadrer le transport Coolify production par le script serveur et un fichier d'autorisation produit par `prepare`, lié au rapport/image/commit ; l'absence de ce fichier refuse l'ancien chemin. Désactiver aussi les auto-déploiements/webhooks Coolify production à la tâche 8.
- [ ] Implémenter `prepare` : maintenance Redis persistante, arrêt/drain effectif worker/scheduler/Reverb et arrêt des nouvelles écritures, export final vérifié, conservation des anciens digest/commit. `deploy` garde fermé ; `verify` exige état déploiement sain, digest attendu, `deployment:check --allow-maintenance` réussi et parcours via accès maintenance privé ; `resume` redémarre les processus puis lève la maintenance seulement si vérifié. Une erreur conserve le service fermé, sans `migrate:rollback` ni restauration automatique. Documenter la transition initiale : l'ancienne image doit elle aussi utiliser la maintenance Redis avant toute nouvelle procédure.
- [ ] Rejouer les deux fichiers ; attendu PASS. Revoir la procédure contre un incident « colonne attendue absente » : readiness ne remplace pas un test métier de migration oubliée ; inclure un test d'upgrade pertinent pour chaque futur changement de schéma, en réutilisant `tests/Feature/Database/`.
- [ ] Commiter : `feat: conditionner les livraisons à une répétition des migrations`.

## Tâche 7 : vérifier la branche et préparer une PR relisible

**Fichiers :** uniquement les fichiers des tâches précédentes ; `docs/beta-environment.md` porte l'état « préparé, non activé » tant que tâche 8 inachevée.

- [ ] Exécuter `composer lint:check`, `composer analyse`, `composer test`, `php artisan wayfinder:generate --with-form`, `bun run lint:check`, `bun run format:check`, `bun run types:check`, `bun run test:unit`, `bun run build` puis le build runtime et son smoke. Utiliser MySQL de test et Chromium configurés, pas de données copiées. Attendu tous les contrôles concernés verts ; attribuer séparément tout échec issu des modifications préexistantes sans les corriger hors périmètre.
- [ ] Relire le diff par rapport à `main`, les assertions de non-fuite des scripts et la couverture de chaque ligne de Review Focus. Faire la revue fraîche prévue par le mode d'exécution choisi ; corriger les constats avant intégration.
- [ ] Ouvrir une PR Conventional Commits vers `main`, décrire copie séparée, impact sur les livraisons et vérifications ; attacher son URL au chat. Ne jamais inclure les modifications locales étrangères. Attendre les checks requis, résoudre les conversations. Le merge utilise Squash & Merge et respecte les autorisations Git déjà données ; aucune création de tag/release manuelle.

## Tâche 8 : activer sur Coolify et contrôler les accès avant tout import

**Fichiers :** `docs/beta-environment.md`, métadonnées vérifiées dans les docs d'exploitation ; aucun secret dans Git. Cette tâche dépend de code de confiance disponible et des checks réussis.

**Interfaces :** environnement Coolify `beta` dans projet `v07iwpqm3wftr6zfnxissd5z`, serveur `y5j0mvuo7rl64wa6z95m178j`. Revalider ces identités et l'application production `kma0dju8bamipey0sd1up9gs` avant mutation. UUID nouveaux enregistrés dans la configuration privée et variables GitHub appropriées ; jamais réutiliser les UUID MySQL/Redis/bucket de production.

- [ ] Lire capacité RAM/CPU/disque et sauvegardes du serveur, charge et réserve production. Provisionner seulement si la somme des budgets laisse une réserve observable ; sinon terminer le code et exposer la capacité manquante sans acheter de VPS. Créer environnement/application, MySQL 8.4 et volume, Redis 7.4 et volume, bucket et clé Garage limités, Mailpit privé, secrets Laravel/Reverb/VAPID et OAuth bêta distinct si Google est testé. Vérifier que le compte bêta ne peut lire/écrire la base ni le bucket de production.
- [ ] Protéger les routeurs Traefik bêta web, WebSocket, éventuel accès médias et Mailpit par `IPAllowList`, limité initialement aux IP publiques de l'opérateur relevées au moment de l'activation (IPv4/IPv6). Le DNS direct vers Traefik évite un proxy intermédiaire non vérifié ; sinon configurer exclusivement les relais réellement fiables, jamais les X-Forwarded-For arbitraires. Aucun port alternatif public. Ce choix ne casse ni cookies, ni PWA, ni handshake WebSocket ; documenter comment mettre à jour la liste après changement de réseau et révoquer un testeur. Référence : [Traefik IPAllowList](https://doc.traefik.io/traefik/reference/routing-configuration/http/middlewares/ipallowlist/).
- [ ] Vérifier avant import depuis une origine hors liste : web/media/WebSocket/Mailpit refusés, y compris avec X-Forwarded-For forgé ; depuis le réseau autorisé, TLS et handshake corrects. Vérifier chaque URL de média réellement émise, pas seulement la page applicative. Si aucun client hors liste n'est disponible, la validation reste incomplète et l'import privé n'est pas lancé.
- [ ] Configurer DNS `beta`/`ws-beta`, certificats TLS et cookies sans domaine parent. Démarrer sur base synthétique seulement ; vérifier absence GA4/Resend, capture Mailpit, Redis et fichiers dédiés, maintenance persistante après redéploiement et fermeture sur migration en attente. Vérifier installabilité PWA et icône depuis l'appareil opérateur. Les limites mémoire peuvent être ajustées uniquement dans la réserve constatée.
- [ ] Activer le workflow bêta sur la Release PR courante vérifiée ; confirmer checks/SHA/digest/commit Compose. Désactiver les anciens déclencheurs production qui contournent le script contrôlé et tester sans release réelle que la publication n'appelle plus Coolify production. La mise en place initiale des protections de production est une opération d'exploitation à préparer avec image précédente et fenêtre de maintenance explicite ; ne pas simuler un succès si elle n'a pas été exécutée.
- [ ] Commiter la procédure et les métadonnées publiques vérifiées : `docs: documenter l'environnement bêta et les livraisons contrôlées`.

## Tâche 9 : première répétition réelle et état final

**Fichiers :** `docs/beta-environment.md`, `docs/operations.md`, `docs/quality-ci-cd.md`, `docs/PRD.md`, modifications ciblées des règles de marque dans `docs/design-system.md` sans embarquer son diff préexistant.

- [ ] Annoncer que le rafraîchissement remplace les essais bêta, puis exécuter la copie explicitement demandée pour cette activation avec la configuration privée ; conserver export et rapport sur le serveur. Comparer schéma/comptages/objets et neutraliser avant démarrage applicatif ; confirmer que la production continue normalement et que ses ressources n'ont pas changé d'identité.
- [ ] Lancer explicitement `rehearse` pour l'image candidate, vérifier seconde migration sans effet et parcourir les cas convenus avec des comptes de test. Réouvrir seulement la bêta autorisée après succès ; vérifier que ni e-mail, ni abonnement Push restauré, ni fichier ne sortent vers les membres réels. Une notification n'est recevable que sur l'appareil inscrit volontairement en bêta.
- [ ] Produire un bilan public sans données privées : date snapshot, commit/image bêta, migrations appliquées, contrôles effectués, limites restantes. Documenter restauration contrôlée, réexécution après snapshot expiré, mises à jour automatiques bloquées par migration et changement d'IP testeur. Ne pas provoquer une mise en production de version simplement pour tester la procédure ; le premier déploiement production reste volontaire.
- [ ] Mettre à jour l'état d'implémentation seulement pour ce qui est effectivement vérifié, puis commit ciblé. S'il reste un accès/capacité/DNS/provider externe manquant, indiquer précisément la partie livrée et le blocage au lieu d'annoncer une bêta opérationnelle.

## Contrôle du plan avant exécution

Couverture relue : isolation et sorties (3/4/8), candidat exact et courses (5), logo/PWA (2), répétition/rapport/maintenance/rollback (1/6/9), tests et documentation (toutes, 7). Les cinq risques de Review Focus possèdent chacun leur test ou vérification d'accès explicite. Les opérations données réelles sont séparées des tests CI ; aucun déploiement production n'est implicite dans l'activation bêta. Les tests et signatures sont définis ; les valeurs dépendant du serveur sont relevées avant mutation plutôt qu'inventées.
