# Couverture des parcours de bout en bout

Les parcours Pest Browser utilisent Chromium, les assets Vite compilés et les
routes Laravel réelles, avec MySQL. Les assertions portent sur l’interface et,
pour les opérations sensibles, sur les données persistées. Ce document décrit
une couverture de scénarios, pas une garantie d’absence de bugs.

## Parcours vérifiés

Les fichiers ci-dessous sont dans `tests/Browser/`, sauf mention contraire.
Certains scénarios s’exécutent en français et en anglais, en thème clair et
sombre ou sur plusieurs largeurs ; ces variantes ne sont pas systématiques pour
chaque scénario. Les nouveaux parcours compte et authentification s’exécutent à
320 et 1 440 pixels.

| Domaine | Comportements observables | Tests |
| --- | --- | --- |
| Accueil et inscription | Navigation publique, cadre amical et majeur, formulaires, consentement, quota atteint entre affichage et soumission | `WelcomeAndRegistrationTest`, `RegistrationQuotaTest`, `AuthenticationJourneyTest` |
| Authentification | Mauvais identifiants puis connexion, déconnexion et refus d’accès privé, lien signé de vérification, lien de réinitialisation et refus de réutilisation | `AuthenticationJourneyTest` |
| Double authentification | Code TOTP, code de récupération à usage unique, accès refusé à un compte banni après le challenge | `AuthenticationJourneyTest`, `ConversationModerationTest` |
| Compte et confidentialité | E-mail déjà utilisé, présence persistée, nouvelle vérification après changement d’e-mail, confirmation puis changement de mot de passe et reconnexion, suppression refusée avec mauvais mot de passe puis révocation, téléchargement JSON du compte sans données d’un autre membre | `AccountJourneyTest` |
| Profil et tutoriel | Création et modification, visibilité, catalogues, limites des intérêts, liens sociaux, parcours obligatoire et absence d’écritures sociales pendant le tutoriel | `ProfileAndNavigationTest`, `ProfileSocialLinksTest`, `OnboardingTest` |
| Disneyland Pass et Cast Member | Sélection, cumul, modification et retrait ; carte SVG dans la zone avatar, libellés FR/EN et absence de collision à 320/1 280 px en clair/sombre | `ProfilePassTest` |
| Découverte et profils passés | Décisions, ordre et renouvellement sans doublons, match réciproque puis message, filtres enregistrés, pagination et erreurs réseau | `DiscoveryTest`, `DiscoveryPreferencesTest`, `PassedProfilesTest` |
| Conversations privées | Navigation, envoi, brouillon, réactions, pagination et défilement, annonces accessibles, lecture | `ConversationTest`, `MessageReactionAccessibilityTest` |
| Temps réel privé | Aperçu et ordre des conversations, nouvelle conversation, message reçu sans déplacer la lecture ancienne, accusé de lecture sans déplacement | `tests/Realtime/ConversationTest.php`, avec serveur Reverb réel |
| Blocage et modération | Blocage/déblocage, signalement avec choix explicite, historique, consultation et clôture administrateur, bannissement et levée | `MemberBlockingTest`, `ConversationModerationTest` |
| Événements | Création, inscriptions et décisions, retrait, annulation, participants et profils intégrés, dialogue adaptatif et discussion | `EventTest`, `EventChatMembershipTest` |
| Trajets | Création, candidatures et décisions, places disponibles, point de rendez-vous privé, annulation, signalement de départ, consultation administrateur | `TripTest`, `TripDepartureReportTest` |
| Discussions de groupe | Discussions événement/trajet, réactions, reprise de toutes les pages manquées, lecture seule, deux sessions navigateur et perte d’accès après exclusion | `TripChatTest`, `GroupChatContinuityTest`, `MessageReactionAccessibilityTest` |
| Séjours | Création, modification, suppression, confirmation de visibilité, calendrier, pagination et recherche des membres, ouverture/fermeture des profils et restauration du focus | `VisitTest` |
| Notifications | Filtres, lecture et suppression, destinations, préférences indépendantes, appareils, reprise après erreur réseau | `NotificationCenterTest`, `NotificationSettingsTest` |
| Partenaires | Profil bilingue avec image, publication administrateur, annonce puis approbation et diffusion, statistiques et limites de délai, consultation et préférences membre | `PartnerTest`, `AdminTest` |
| Administration | Rôles, gestion des membres et catalogues, assistance, thèmes saisonniers, configuration du tutoriel, modération et statistiques | `AdminTest`, `AdminScrollTest`, `ConversationModerationTest` |
| Navigation et présentation | Réglages par rôle, historique et liens directs, langues, apparences, défilement et petits écrans, reprise du viewport PWA, ouverture hors navigateur intégré | `SettingsNavigationTest`, `LocalizationTest`, `AppearanceTest`, `DocumentScrollTest`, `PwaViewportRecoveryTest`, `ExternalBrowserTest`, `BetaBrandingTest`, `BrowserRuntimeTest` |
| Passkeys | Traduction des erreurs navigateur et possibilité de réessayer | `PasskeyFeedbackTest` ; authentificateur matériel non couvert |

Les contrôles d’autorisation, de concurrence et de livraison asynchrone restent
également testés dans `tests/Feature/` et `tests/Unit/`. Un parcours navigateur
heureux ne remplace pas ces cas serveur : notamment accès aux canaux privés,
révocation des sessions, confidentialité des exports, inscriptions concurrentes,
quota de places, idempotence des notifications et traitements planifiés.

## Exécution et isolation

Configurer une base MySQL **de test jetable** dans `.env.testing`. Les tests
temps réel utilisent `DatabaseMigrations` et reconstruisent son schéma. Ne
jamais exécuter deux suites simultanément sur la même base, ni recompiler les
assets pendant qu’une suite navigateur les utilise.

Contrôles complets habituels :

```sh
PAO_DISABLE=1 COMPOSER_PROCESS_TIMEOUT=0 composer ci:check
```

`PAO_DISABLE` désactive uniquement l’assistant de diagnostic PAO. La désactivation
du délai Composer évite d’interrompre une suite navigateur longue.

Pour cibler les parcours ajoutés, après un build :

```sh
bun run build
PAO_DISABLE=1 ./vendor/bin/pest tests/Browser/AuthenticationJourneyTest.php tests/Browser/AccountJourneyTest.php
```

Les quatre tests Reverb sont ignorés sans activation explicite. Pour les lancer
localement, construire d’abord les assets avec les mêmes paramètres que le
serveur de test :

```sh
export REVERB_APP_ID=ci-realtime
export REVERB_APP_KEY=ci-realtime-public-key
export REVERB_APP_SECRET=ci-realtime-test-only-secret
export REVERB_HOST=127.0.0.1 REVERB_PORT=18081 REVERB_SCHEME=http
VITE_REVERB_APP_KEY="$REVERB_APP_KEY" VITE_REVERB_HOST="$REVERB_HOST" VITE_REVERB_PORT="$REVERB_PORT" VITE_REVERB_SCHEME="$REVERB_SCHEME" bun run build
PAO_DISABLE=1 bash .github/scripts/test-realtime.sh
```

Utiliser un shell réservé à cette commande, puis reconstruire les assets avec
la configuration habituelle avant de reprendre le développement. Le script
vérifie la base de test, démarre Reverb et l’arrête même après un échec.

## Répartition en CI

La CI conserve les quatre shards backend et quatre shards navigateur existants,
sur huit runners et huit bases distincts. Les fichiers sont répartis par Pest
selon les durées versionnées dans `tests/.pest/shards.json`. Après une évolution
importante des tests, régénérer ces durées par une exécution complète :

```sh
PAO_DISABLE=1 ./vendor/bin/pest --update-shards --display-warnings
```

Un neuvième job, `Pest realtime (Reverb)`, possède son serveur et sa base MySQL.
Il ne partage pas les fixtures avec les shards transactionnels. Le check requis
`Backend tests` exige le succès de ces trois groupes ; aucun groupe annulé ou
ignoré ne passe silencieusement. Les captures d’échec et journaux de chaque
runner sont conservés sept jours. Le build et les dépendances ordinaires sont
préparés une seule fois ; le job Reverb reconstruit ses propres assets pour
injecter sa configuration WebSocket.

## Résultats locaux du 24 octobre 2026

Les étapes de `composer ci:check` ont été exécutées individuellement, avec Pest
directement pour la suite complète et la régénération des durées.

| Validation | Résultat |
| --- | --- |
| Suite Pest complète avec `--update-shards --display-warnings` | 2 078 réussis, 6 ignorés, 35 248 assertions ; code de sortie 0 |
| Reverb réel, exécuté séparément par le script CI | 4 réussis, 33 assertions ; code de sortie 0 et serveur arrêté |
| Tests frontend Bun | 159 réussis, 0 échec, 278 assertions |
| Pint, PHPStan, ESLint, Prettier et TypeScript | Réussis |
| Génération Wayfinder et build Vite | Réussis |
| Inventaire et répartition des shards | 254 classes avec durées et checksum cohérents ; 218 backend, 35 navigateur et 1 temps réel ; aucune omission ni duplication entre les shards de chaque groupe |
| Syntaxe du workflow et du script | YAML analysé, `bash -n` et `git diff --check` réussis |

La référence locale précédente comptait 2 059 tests Pest réussis et 6 ignorés
(35 110 assertions). L’augmentation correspond aux 18 nouveaux cas navigateur
et au contrôle de configuration CI supplémentaire. Les quatre tests Reverb
existaient déjà mais deviennent obligatoires dans leur job dédié. Les durées
locales ne constituent pas une mesure du gain de temps sur GitHub Actions.

Parmi les six tests ignorés dans la suite ordinaire, les quatre tests Reverb
ont réussi séparément. Les deux autres nécessitent respectivement un bucket
Garage préparé (`GARAGE_INTEGRATION=1`) et une image Docker de runtime construite
(`DLP_RUNTIME_TEST_IMAGE`) ; ces environnements n’ont pas été préparés pour
cette validation. Aucun build Docker ni workflow GitHub distant n’a été exécuté
dans ce lot.

## Limites connues

- Les e-mails sont interceptés avec `Mail::fake()` : les liens Laravel réels
  sont visités, mais la délivrabilité Resend n’est pas vérifiée.
- Google OAuth, Photon, Web Push et les erreurs WebAuthn utilisent les doubles
  prévus par leurs tests. Leur fonctionnement chez les fournisseurs externes,
  sur un authentificateur physique ou sur un appareil réel reste à vérifier.
- Chromium mobile est une émulation de viewport ; Safari iOS, Android réel,
  installation PWA et réception d’une notification système ne sont pas prouvés.
- Le job Reverb utilise une instance locale sans mise à l’échelle Redis. Les
  files et caches utilisent la configuration de test ; ce n’est pas un test de
  charge, de reprise après panne Redis ou de déploiement Coolify.
- Les tests d’intégration Docker/Garage restent conditionnels à leur
  environnement dédié. Leur omission doit être mentionnée avec le résultat de
  chaque validation complète.

Les scénarios décrits ne couvrent pas toutes les combinaisons possibles de
données, de droits et d’erreurs réseau. Toute régression découverte doit ajouter
un cas observable au domaine concerné.

## Complément : transitions et contrôles privés

Le complément du 24 octobre ajoute **34 cas navigateur** (datasets compris)
aux parcours existants. Il examine les branches métier plutôt que de compter
uniquement les pages visitées.

| Domaine | Cas ajoutés | Fichier |
| --- | --- | --- |
| Trajets | Création d’un retour en train, RER et bus sans places de voiture ; retrait d’une demande puis nouvelle candidature sans doublon ; désistement accepté avec abandon de confirmation puis validation, restitution de place, trace de sortie et révocation du chat ; dernière place prise pendant l’affichage des demandes ; diminution de capacité refusée puis corrigée | `TripTest.php` |
| Trajets et notifications | Demande dans le navigateur, acceptation par l’organisateur, ouverture de la notification par le candidat, rendez-vous privé puis accès à la discussion | `TripTest.php` |
| Confidentialité des trajets | Rendez-vous absent de l’interface et refus HTTP des discussions, participants et demandes pour les états sans inscription, en attente, refusé, retiré et exclu | `TripTest.php` |
| Événements | Verrouillage du mode après une demande ; capacité incluant l’organisateur ; refus des modifications majeures à moins de 24 heures ; correction et soumission du même formulaire après chaque refus | `EventTest.php` |
| Confidentialité des événements | Lieu précis absent de l’interface et refus HTTP des discussions, participants, demandes et édition pour les cinq mêmes états non acceptés | `EventTest.php` |
| Visites | Matrice des trois audiences (masqué, matchs, membres) pour un visiteur avec et sans match ; ouverture du profil depuis une visite autorisée | `VisitTest.php` |
| Sécurité du compte | Activation réelle de la double authentification, confirmation TOTP, affichage puis renouvellement des huit codes, désactivation et effacement des secrets ; 320 et 1 440 px | `AuthenticationJourneyTest.php` |
| Majorité | Rejet serveur d’une personne mineure, absence de compte créé, correction avec une personne atteignant 18 ans et arrivée à la vérification d’e-mail | `AuthenticationJourneyTest.php` |
| Pages publiques | Deux guides et nouveautés ; bascule FR → EN → FR conservant la page, langue du document et absence de débordement à 320 px | `WelcomeAndRegistrationTest.php` |
| Partenaires | Refus motivé, création d’un nouveau brouillon prérempli, correction et nouvelle soumission ; conservation de la décision originale et absence de diffusion avant approbation | `PartnerTest.php` |

Les requêtes `fetch` des matrices de droits sont exécutées depuis la session
Chromium et vérifient les réponses Laravel réelles. Elles complètent les
assertions d’affichage ; aucun endpoint de test ni faux contrôle d’accès n’est
ajouté. Les fixtures de statuts servent à couvrir chaque état indépendamment ;
les parcours de transition et les tests serveur couvrent leur création.

### Ce que cette couverture ne prouve pas

Toutes les familles fonctionnelles de l’inventaire ci-dessus possèdent des
parcours automatisés, mais **toutes leurs combinaisons ne sont pas couvertes**.
Les traductions, thèmes et largeurs ne sont pas croisés avec chaque permission,
chaque état et chaque panne. Les tâches planifiées, purges, files d’attente,
reprises de livraison et courses concurrentes sont principalement validées par
les tests serveur ; simuler des jours d’attente dans une page Chromium ne les
transformerait pas en essais de production.

Restent notamment des validations sur appareils et services réels : Google
OAuth, authentificateur passkey, permissions et réception Web Push, Resend,
Photon, Safari/iOS et déploiement multi-processus Redis/Reverb/Coolify. Les
doubles externes, appels d’Actions de workers et limites d’upload navigateur
sont explicités dans les tests concernés. Leur présence ne doit pas être
présentée comme une validation de ces fournisseurs ou de ces appareils.

### Résultats après ce complément — 24 octobre 2026

| Validation | Résultat |
| --- | --- |
| Suite Pest complète, `--update-shards --display-warnings` | **2 112 réussis**, 6 ignorés, 35 488 assertions, 786,81 s ; code de sortie 0 |
| Parcours Chromium de la suite complète | **399 cas réussis**, contre 365 avant ce complément |
| Reverb réel par `.github/scripts/test-realtime.sh` | **4 réussis**, 38 assertions ; deux nouvelles exécutions avec code de sortie 0, dont une avec trace shell et rapport JUnit sans erreur ni échec |
| Total des parcours navigateur réussis, Reverb compris | **403 cas** ; les variantes de datasets sont comptées séparément |
| Tests frontend Bun | 159 réussis, 0 échec, 278 assertions |
| Pint, PHPStan, ESLint, Prettier et TypeScript | Réussis |
| Wayfinder et build Vite | Réussis ; assets ordinaires reconstruits après la validation Reverb |
| Durées des shards | Régénérées par la suite complète pour 254 classes : 218 backend, 35 navigateur et 1 temps réel ; checksum vérifié |

La progression depuis la référence précédente est de **34 tests et 240
assertions Pest**. Les six tests ignorés dans la suite ordinaire restent les
quatre Reverb exécutés séparément, l’intégration Garage et l’image Docker de
runtime. Aucun environnement Garage ni image de runtime n’a été préparé pour
ce complément ; aucun workflow distant n’a été exécuté. Les quatre shards
backend et les quatre shards navigateur déjà configurés en CI sont conservés.

Un premier passage Reverb a affiché quatre réussites mais renvoyé le code 1,
sans diagnostic d’échec. Les deux passages suivants ont renvoyé 0, dont un
instrumenté avec trace shell et rapport JUnit. La cause de cet écart intermittent
n’est pas établie : il reste à surveiller dans les diagnostics CI et ne doit pas
être considéré comme corrigé par ces seules relances.
