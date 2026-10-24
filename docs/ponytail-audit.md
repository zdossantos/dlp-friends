# Audit Ponytail — DLP Friends

Date : 23 octobre 2026. Branche : `refactor/ponytail`. Référence examinée : `af9a5dc4e2e1365619202e9947acd73fdcb18077`, issue de `main` synchronisé.

## Objet, méthode et limites

**Ce que fait ce dépôt :** une application Laravel/Inertia/Vue de rencontres strictement amicales entre adultes fans de Disneyland Paris, indépendante de Disney. Elle réunit découverte et profils, matches, messages privés, événements, visites, trajets, conversations de groupe, modération et espace partenaire.

Cet audit examine le code applicatif existant, les autorisations, les traitements différés, les contraintes de données, le frontend, les tests et la livraison. Il ne se limite pas au diff de la branche. **Aucun code applicatif, configuration, test ou dépendance n’a été modifié. Ce rapport est l’unique modification du dépôt.** Aucune migration, opération de production ou suppression n’a été exécutée.

Lecture intégrale des références demandées : `AGENTS.md`, `avatar.md`, `docs/PRD.md`, `docs/engineering-principles.md`. Lecture des documents d’architecture et de données ; consultation ciblée de `docs/security-privacy.md`, `docs/quality-ci-cd.md`, `docs/operations.md` et des documents spécialisés liés aux domaines inspectés. Le code, les migrations et les tests ont été confrontés à ces contrats. Les anciens plans de `docs/superpowers/` sont des traces de conception, pas une preuve de comportement actuel.

La cartographie porte sur tout l’arbre suivi par Git ; la lecture approfondie privilégie les chemins sensibles et leurs usages. Elle **ne signifie pas une lecture ligne par ligne des 1 671 fichiers suivis**. La matrice de couverture et les inspections restant à mener figurent plus bas.

Les performances sont évaluées structurellement : nombre de requêtes, collections chargées, boucles et effets réseau. Aucun volume de production, profil de trafic, temps de réponse ni plan `EXPLAIN` n’était disponible. Hypothèse de travail : une communauté de taille modeste aujourd’hui, avec des historiques et audiences qui augmentent. Aucun seuil de saturation ni gain chiffré de latence n’est affirmé.

### Lecture des résultats

- **Avéré** : chemin et conséquence établis par inspection statique, parfois explicitement couverts par un test existant. Cela ne signifie pas qu’un incident a été observé en production.
- **Écart de contrat** : comportement établi, mais arbitrage produit nécessaire avant de changer une règle actuellement testée.
- **Suspicion** : scénario plausible nécessitant une reproduction dédiée ; ne pas le traiter comme une faille démontrée.
- **Style** : amélioration de lisibilité sans défaut fonctionnel établi ; exclue des corrections urgentes.

Gravité : **P1** confidentialité ou autorisation à corriger en premier ; **P2** comportement, robustesse ou coût significatif ; **P3** entretien et documentation. Aucun P0 établi. Le risque de régression porte sur la correction proposée. Les efforts sont des ordres de grandeur incluant tests ciblés et revue, hors attente d’arbitrage ou infrastructure.

## Synthèse et classement

**29 constats : 23 problèmes avérés, 4 écarts de contrat et 2 suspicions.** Le complément backend ajoute les n° 18–20 et approfondit les n° 6, 10 et 14. Le complément frontend ajoute les n° 21–25, confirme le mécanisme du n° 13 et précise les tests manquants du n° 7. Le complément transversal ajoute les n° 26–29 et approfondit les n° 1 et 10. Les trois P1 concernent la révocation des autorisations en temps réel et des sessions. La liste finale et la couverture transversale, en fin de rapport, remplacent les ordres de travail intermédiaires. Les principaux coûts évitables sont le chargement intégral de la découverte, les requêtes par jour du calendrier et les requêtes par groupe/message.

| N°  | Constat                                                                    | Statut                                       | Gravité      | Impact principal                                                        | Régression | Effort indicatif                               |
| --- | -------------------------------------------------------------------------- | -------------------------------------------- | ------------ | ----------------------------------------------------------------------- | ---------- | ---------------------------------------------- |
| 1   | Diffusion privée différée après blocage                                    | Avéré statiquement                           | P1           | Confidentialité, révocation                                             | Élevé      | 1–2 j                                          |
| 2   | Chat d’événement : rôle et vérification non recontrôlés                    | Avéré statiquement                           | P1           | Accès temps réel après perte d’éligibilité                              | Élevé      | 1–2 j                                          |
| 3   | Découverte et cible de swipe : éligibilité incomplète                      | Avéré statiquement                           | P2           | Exposition de profils devenus inéligibles                               | Élevé      | 1–2 j                                          |
| 4   | Réglages de sécurité et compte réservés au rôle membre                     | Avéré, intention testée                      | P2           | Autonomie des comptes partenaire/admin seuls                            | Élevé      | 1–2 j après décision                           |
| 5   | Timeout worker égal au délai de réservation Redis                          | Avéré dans les valeurs versionnées           | P2           | Reprise concurrente et doublons possibles                               | Moyen      | 0,5–1 j                                        |
| 6   | Envoi du récapitulatif e-mail dans une transaction SQL                     | Avéré statiquement                           | P2           | Verrous longs, doublons après incident                                  | Élevé      | 1–3 j                                          |
| 7   | Chat de groupe marqué lu dans un onglet masqué                             | Avéré statiquement                           | P2           | Compteurs et notifications perdus prématurément                         | Moyen      | 0,5–1 j                                        |
| 8   | Tous les candidats chargés pour cinq suggestions                           | Avéré                                        | P2           | Mémoire et travail proportionnels à l’audience                          | Élevé      | 2–3 j                                          |
| 9   | Calendrier : deux requêtes principales par jour                            | Avéré                                        | P2           | Coût SQL par affichage du mois                                          | Élevé      | 1–3 j                                          |
| 10  | Requêtes répétées dans les groupes et statistiques partenaires             | Avéré                                        | P2           | N+1 à la liste et au chargement des messages                            | Moyen      | 1–2 j                                          |
| 11  | Historique après blocage : PRD et tests opposés                            | Écart de contrat                             | P2           | Promesse produit ambiguë                                                | Élevé      | 0,5–2 j après décision                         |
| 12  | Export : propres messages exclus selon visibilité du pair                  | Écart de contrat                             | P2           | Portée de l’export ambiguë                                              | Élevé      | 1–2 j après décision                           |
| 13  | Reconnexion de groupe après plus de dix messages                           | Avéré statiquement, fusion reproduite        | P2           | Intervalle absent de la chronologie, lecture trop avancée               | Moyen      | 1–2 j                                          |
| 14  | Envoi de groupe concurrent au retrait d’accès                              | Suspicion                                    | P2 potentiel | Écriture après révocation ou interblocage                               | Élevé      | 1 j de reproduction puis 1–2 j                 |
| 15  | Maximum lu et mise à jour privée non bornée                                | Suspicion                                    | P2 potentiel | Accusés de lecture incohérents                                          | Moyen      | 0,5–1 j de reproduction puis correction ciblée |
| 16  | Helper d’incrément des non-lus sans usage applicatif                       | Avéré, références vérifiées                  | P3           | Code et tests sans effet produit                                        | Faible     | < 0,5 j                                        |
| 17  | Documentation de production contradictoire                                 | Avéré                                        | P3           | Mauvaise procédure opérateur                                            | Faible     | 0,5 j                                          |
| 18  | Validation des dates d’événement : exception et heure inexistante acceptée | Avéré, reproduit sans SQL                    | P2           | Erreur serveur et horaire déplacé silencieusement                       | Moyen      | 0,5–1 j                                        |
| 19  | Push : reprise précoce acquittée malgré une réservation abandonnée         | Avéré statiquement, déclencheur conditionnel | P2           | Notification définitivement perdue après arrêt brutal                   | Moyen      | 1–2 j                                          |
| 20  | Annonces partenaires : canal push déclaré mais circuit réel sans push      | Écart de contrat                             | P2           | Comportement et test de couverture trompeurs                            | Moyen      | 0,5 j de décision puis 1–2 j                   |
| 21  | Abonnements de frappe figés sur la liste initiale                          | Avéré, sonde isolée                          | P2           | Nouveaux interlocuteurs sans indicateur ; abonnements inutiles          | Moyen      | 0,5–1 j                                        |
| 22  | Première réaction inaccessible au clavier sur mobile                       | Avéré dans le template et le test existant   | P2           | Action indisponible aux technologies d’assistance                       | Moyen      | 0,5–1 j                                        |
| 23  | Erreurs réseau/stockage mal terminées dans le push frontend                | Avéré, branches reproduites avec doublures   | P2           | Contrôles bloqués, erreurs silencieuses, refus mémorisé après panne     | Moyen      | 1–2 j                                          |
| 24  | Double rechargement pour un même changement d’accès de groupe              | Avéré statiquement                           | P3           | Visites Inertia redondantes et annulations possibles                    | Faible     | < 0,5 j                                        |
| 25  | Erreurs 2FA/passkeys et nom proposé hors traduction                        | Avéré                                        | P3           | Messages techniques anglais ou français imposés                         | Faible     | 0,5–1 j                                        |
| 26  | Révocation des sessions SQL alors que la production utilise Redis          | Avéré, sonde du garde/cache                  | P1           | Ancienne session réutilisable après levée de bannissement               | Élevé      | 1–2 j                                          |
| 27  | Journaux Laravel dans des fichiers de conteneurs sans persistance déclarée | Avéré dans la configuration versionnée       | P2           | Diagnostic perdu au remplacement, croissance sans rotation              | Moyen      | 0,5–1 j                                        |
| 28  | Portée de la 2FA locale non définie pour la connexion Google               | Écart de contrat                             | P2           | Authentification et révélation de sanction différentes selon la méthode | Élevé      | 0,5 j de décision puis 1–2 j                   |
| 29  | Clé dédiée Resend Usage absente du Compose de production                   | Avéré, déclencheur conditionnel              | P2           | Inscription e-mail suspendue avec une clé d’envoi restreinte            | Faible     | 0,5–1 j                                        |

## Cartographie du dépôt

Inventaire au commit de référence : 443 fichiers dans `app/`, 418 dans `resources/js/` dont 165 primitives UI, 53 migrations, 28 factories, 3 seeders ; 282 fichiers suivis dans `tests/`, dont 193 Feature, 30 Browser, 31 Frontend et 15 Unit. Quatre fichiers de routes, 18 configurations, quatre fichiers sous `docker/`, douze scripts et quatre workflows. Les comptes incluent les supports et ne sont pas des comptes de classes métier distinctes.

| Couche / domaine     | Structure et flux examinés                                                                                          | Évaluation                                                                                                                             |
| -------------------- | ------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------- |
| Entrées HTTP         | `routes/web.php`, `settings.php`, `channels.php`, `console.php`, middleware de `bootstrap/app.php`                  | Séparation membre/admin/partenaire, statut, vérification, conditions et onboarding ; routes sensibles confrontées aux Policies/Actions |
| Identité             | Fortify, identité sociale, rôles et audits, profil, conditions, passkeys/2FA, réglages                              | Plusieurs critères d’accès distribués ; cohérence de révocation prioritaire                                                            |
| Découverte           | `DiscoveryService`, `DiscoveryController`, `ProfilePolicy`, `CreateSwipe`, passes et matches                        | Filtrage des cibles et matérialisation intégrale approfondis                                                                           |
| Messages privés      | Actions d’envoi/lecture/réaction/blocage, `ConversationPolicy`, événements, notifications, page et rafraîchissement | Bonne défense à l’écriture ; divergence sur l’historique et contrôle différé incomplet                                                 |
| Groupes / événements | Inscriptions, retrait, messages système, `GroupChatPolicy`, sérialisation et `GroupChat.vue`                        | Réutilisation déjà présente ; droits des événements et états frontend à renforcer                                                      |
| Visites              | `Visit`, visibilité serveur, requêtes d’audience et `VisitWorkspaceData`                                            | Confidentialité explicite ; coût du calendrier établi                                                                                  |
| Trajets              | `Trip`, Policy, inscriptions, départs, capacité, signalement, rétention et chat                                     | Éligibilité plus complète ; parcours concurrents à approfondir                                                                         |
| Partenaires          | Rôles, fiches/révisions, annonces, livraisons et statistiques                                                       | Pipeline de livraison et instantanés étudiés ; administration exhaustive restant à couvrir                                             |
| Vie du compte        | Demande/annulation/purge, données conservées pour trajets, export direct                                            | Vérifications et verrous présents ; portée de l’export à décider                                                                       |
| Données              | 44 modèles, migrations, FK, unicités, suppressions et rétention                                                     | Contraintes des paires, réactions, inscriptions et contexte de groupe contrôlées                                                       |
| Asynchrone           | Six jobs, 11 événements, 14 notifications, quatre mails, planification                                              | Réévaluation des droits, après-commit, livraison et réservation examinés                                                               |
| Temps réel           | Reverb, canaux privés signés, identité active/conditions, Echo, push et service worker                              | Le statut global est recontrôlé ; il ne remplace pas l’autorisation du contenu                                                         |
| Frontend             | Pages Inertia, composants partagés, composables, état privé/groupe, formulaires, traductions                        | Parcours sensibles et usages étudiés ; pas de revue visuelle exhaustive                                                                |
| Infrastructure       | Docker multi-stage, entrypoint, nginx, Compose local/production, CI, releases, scripts                              | Sources examinées ; aucun déploiement, image construite ou environnement distant vérifié                                               |

L’architecture Laravel/Inertia est adaptée au produit. Actions pour les cas d’usage, Policies pour les droits, Form Requests et scopes existent déjà. Les contraintes SQL et les tests ne sont pas absents. Le refactoring utile est ciblé ; cet audit ne justifie ni API séparée, ni couche repository générale, ni remplacement des composants UI.

## À corriger en premier

### 1. La diffusion privée différée ne recontrôle pas le blocage

**Avéré statiquement — P1.** Un message enregistré avant un blocage peut encore être transmis ensuite par le worker à des canaux déjà ouverts.

**Preuves :** `app/Events/MessageSent.php:25` sélectionne le canal de conversation et les deux canaux personnels ; `:40` ne vérifie que les deux statuts actifs ; `:61` inclut le contenu privé. `app/Actions/BlockUser.php:60` archive la conversation, sans que cette archive ou le blocage soit pris en compte par cet événement. `MessageReactionUpdated.php:36` et `MessagesRead.php:34` utilisent également le statut actif comme garde. `app/Reverb/IdentityAwarePrivateChannel.php:54` contrôle identité, statut et conditions courantes, pas le droit sur cette conversation.

**Scénario :** envoi accepté et événement mis en file ; blocage validé avant consommation ; les deux comptes restent actifs ; le worker diffuse encore le contenu. L’autorisation initiale du canal ne suffit pas à révoquer une connexion existante. Ce constat ne prétend pas qu’un nouvel envoi HTTP après blocage serait accepté : l’Action d’envoi le protège. Il ne s’étend pas au push, dont les notifications recontrôlent déjà davantage de droits.

**Solution recommandée :** déterminer les destinataires à partir de l’état courant à la consommation et appliquer une règle explicite de livraison après blocage/archive aux événements privés. Auditer aussi les réactions, reçus et signaux de présence/frappe associés. Réutiliser les critères existants de conversation non bloquée sans confondre droit d’historique et droit de livraison d’un nouvel événement. L’après-commit ne garantit pas, à lui seul, une autorisation encore valable.

**Validation :** tests événementiels « envoyer → bloquer → consommer », dans les deux sens ; tests Reverb avec abonnement ouvert avant blocage ; contrôles de ban, suppression et conditions déjà présents à conserver. Si rien n’est fait, la révocation reste incomplète pendant les délais de file. Risque de régression élevé : notifications légitimes et historique doivent rester cohérents avec la décision n° 11.

### 2. Le chat d’événement conserve des destinataires devenus inéligibles

**Avéré statiquement — P1.** Une inscription acceptée et un statut actif suffisent encore à recevoir du contenu de groupe après retrait du rôle membre ou de la vérification de l’adresse.

**Preuves :** `app/Policies/GroupChatPolicy.php:12` recharge l’utilisateur ; sa branche événement, `:23–33`, exige organisateur actif et participant actif/accepté, sans rôle `user` ni vérification e-mail. La branche trajet délègue au contraire à `TripPolicy`. `app/Models/User.php`, scope `inGroupChat`, sélectionne les actifs organisateurs/acceptés. `app/Events/GroupChatMessageSent.php:38–40` utilise cette sélection et cette même Policy pour les canaux personnels. `app/Actions/SyncManageableUserRoles.php:41` retire le rôle sans effacer l’inscription ; le transport Reverb vérifie l’activité et les conditions, pas ces critères sociaux.

**Scénario :** un membre accepté garde son websocket personnel ; son rôle `user` est retiré, mais son compte reste actif ; les routes sociales lui répondent désormais 403, tandis qu’un message d’événement continue de le sélectionner comme destinataire. Le même décalage existe pour l’adresse redevenue non vérifiée.

**Solution recommandée :** expliciter l’éligibilité courante du lecteur d’événement dans les autorisations serveur et l’appliquer aux diffusions et notifications différées. Partager les critères communs déjà utilisés ailleurs, sans rendre identiques les règles métier des trajets et événements. Décider séparément du cas d’un profil volontairement masqué : ne pas retirer son historique par accident.

**Validation :** inscription puis retrait de rôle / changement d’e-mail avant consommation ; HTTP, Policy, événement et notification doivent converger. Tester aussi les rôles cumulés et l’organisateur. Sans correction, le middleware protège les requêtes mais pas tous les abonnements existants.

### 3. La découverte et les swipes acceptent des cibles sans rôle membre ou adresse vérifiée

**Avéré statiquement — P2.** L’éligibilité du visiteur ne garantit pas celle du profil cible.

**Preuves :** `app/Services/DiscoveryService.php:40–65` filtre visibilité, onboarding, avatar actif, statut et âge, mais ni rôle membre ni vérification e-mail du propriétaire. `app/Policies/ProfilePolicy.php:59–68` présente la même omission. `app/Actions/CreateSwipe.php`, `ensureTargetIsEligible` vers `:134`, ne la compense pas. Le retrait de rôle dans `SyncManageableUserRoles.php:41` conserve le profil ; la modification d’adresse dans `app/Http/Controllers/Settings/AccountController.php` invalide sa vérification sans supprimer ce profil.

**Conséquence :** un ancien membre au profil complet, devenu partenaire seul ou non vérifié, peut encore apparaître et recevoir un swipe. Cela ne signifie pas que tout partenaire neuf possède un profil public. Les scopes de visibilité de `Visit` et `Trip` fournissent déjà des exemples de contrôle plus complet.

**Solution recommandée :** compléter les critères de cible dans la requête de découverte, la Policy et la validation transactionnelle du swipe ; réutiliser un scope d’éligibilité précisément nommé si ces critères sont réellement communs. Éviter un booléen global qui assimilerait visibilité, droit de connexion et accès à un historique.

**Validation :** profils existants après retrait de rôle ou changement d’adresse ; vérifier liste, URL directe, swipe et révocation concurrente. Sans correction, les règles de publication varient selon la surface. Risque élevé : classement, comptes à rôles cumulés et profils masqués.

### 4. Les comptes partenaire/admin seuls n’ont pas les réglages ordinaires de sécurité et de compte

**Avéré, intention actuelle testée — P2.** `routes/settings.php:12–29` place édition/suppression du compte, export, écran de sécurité et modification de mot de passe derrière `role:user`, profil complet et onboarding. Les réglages de notifications sont, eux, accessibles dans le groupe plus général `:35`.

**Preuves :** `tests/Feature/Partner/PartnerRoleAuthorizationTest.php:15` et `:28` confirment notamment le refus de l’écran compte au partenaire seul et à l’admin sans rôle membre. Le point d’entrée passkeys de `routes/settings.php:51` renvoie vers cet écran de sécurité protégé. Aucune route équivalente de gestion personnelle n’a été trouvée pour ces comptes. Les mécanismes Fortify doivent être distingués de l’accessibilité réelle des écrans de réglages.

**Problème :** l’autonomie de gestion de l’identité dépend d’un rôle social et de son onboarding. Ce n’est pas un défaut d’autorisation permettant une élévation de privilèges ; c’est une restriction de gestion du compte, y compris de son écran de sécurité.

**Solution recommandée :** valider une matrice « compte personnel / profil social / administration / partenaire », puis séparer les réglages d’identité des préférences de découverte. Ne pas ouvrir en bloc les routes sociales ni rendre la suppression des comptes privilégiés automatique sans politique explicite. Aucun constat juridique n’est déduit de cette seule restriction.

**Validation :** comptes membre, partenaire seul, admin seul et rôles cumulés ; mot de passe et méthodes sociales ; maintien des contrôles de statut et des protections sensibles. Sans décision, ces comptes restent dépendants d’une intervention pour certaines opérations personnelles.

### 5. Le worker et Redis utilisent tous deux 90 secondes

**Avéré dans la configuration versionnée — P2.** `docker/php/entrypoint.sh:28` lance `queue:work --timeout=90`. `config/queue.php:71` donne à `REDIS_QUEUE_RETRY_AFTER` la valeur par défaut 90. `compose.production.yaml:33` sélectionne Redis sans imposer un délai supérieur.

**Problème :** il n’existe aucune marge entre expiration du travailleur et remise à disposition du job réservé. À la frontière, avec plusieurs consommateurs ou lors d’une transition de workers, une reprise peut rencontrer un traitement qui n’est pas encore définitivement arrêté. Les doublons ne sont pas démontrés en production ; une variable d’environnement externe peut aussi modifier la valeur effective, non vérifiée ici. Les files restent de toute façon à livraison au moins une fois.

**Solution recommandée :** garantir `timeout < retry_after`, avec marge documentée, délais réseau bornés et vérification des durées réelles. Par exemple 60/120 secondes uniquement si les jobs le permettent ; ne pas adopter ces nombres sans mesure. Conserver l’idempotence métier.

**Validation :** vérifier les valeurs effectives sans exposer de secrets, puis un job artificiellement lent avec deux consommateurs en environnement isolé. Sans correction, les reprises sont inutilement fragiles à la limite temporelle.

## À corriger ensuite

### 6. Le récapitulatif e-mail combine verrou SQL et effet externe non atomique

**Avéré statiquement — P2.** `app/Jobs/SendWeeklyEmailRecap.php:28–45` verrouille la livraison puis l’utilisateur, construit le récapitulatif, appelle `Mail::send` à `:43`, puis écrit `sent_at` à `:44`, le tout dans la transaction.

**Problème :** la latence du fournisseur conserve les verrous. Si le fournisseur accepte le mail puis que le worker s’arrête ou que le commit échoue, le rollback ne rappelle pas l’e-mail : le retry peut le renvoyer. Les verrous empêchent certains envois concurrents ordinaires, pas cette fenêtre d’incident. La formulation « livraison unique en file » du PRD ne constitue donc pas une garantie de livraison externe exactement une fois.

**Solution recommandée :** définir une livraison identifiée et un état de réservation court, séparer l’appel réseau des longues transactions, et employer une clé d’idempotence fournisseur si le transport retenu la supporte effectivement. Documenter la politique de reprise en cas d’acceptation incertaine. Ne pas simplement écrire `sent_at` avant l’appel : cela remplacerait les doublons par des pertes silencieuses.

**Validation :** fournisseur accepté puis exception/arrêt avant confirmation locale, retry et concurrence, désactivation des préférences avant consommation. Sans correction, les ralentissements bloquent des opérations du compte et un incident peut produire un doublon. Risque élevé : livraison, reprise et préférences.

### 7. Les groupes marquent les messages lus sans visibilité de l’onglet

**Avéré statiquement — P2.** `resources/js/components/groups/GroupChat.vue:63–85` poste le dernier ID visible dans l’état local sans tester `document.visibilityState`. Le callback websocket le déclenche à `:154`, la notification persistante à `:191` et le montage à `:212`. La page privée `resources/js/pages/Conversations/Show.vue:160` contient déjà un garde de visibilité.

**Conséquence :** laisser le chat de groupe ouvert en arrière-plan peut acquitter les nouveaux messages et les notifications alors que l’utilisateur ne les a pas regardés. Le mécanisme serveur de curseur monotone est utile mais ne permet pas de déduire que l’onglet est visible.

**Solution recommandée :** aligner la règle de lecture sur la visibilité réelle de la conversation et reprendre l’acquittement au retour au premier plan, en conservant les IDs bornés et la progression monotone. Réutiliser les comportements privés pertinents sans extraire immédiatement un framework de chat.

**Validation :** test navigateur onglet masqué → message reçu → compteur inchangé ; retour visible → lecture acquittée ; navigation vers un autre groupe ; reconnexion. Sans correction, les compteurs sous-estiment les messages réellement non consultés.

### 8. La découverte charge et trie toute l’audience pour cinq cartes

**Avéré — P2.** `app/Services/DiscoveryService.php:65` matérialise tous les profils admissibles et leurs relations, calcule les intérêts en commun en PHP, filtre à `:112` et trie à `:113`. `app/Http/Controllers/DiscoveryController.php:23–32` ne conserve cinq résultats qu’après ce travail.

**Impact :** mémoire et hydratation proportionnelles au nombre de candidats, tri en mémoire, répétition à chaque renouvellement du lot. Le chargement différé Inertia déplace le moment du travail ; il ne le borne pas. Aucun dépassement mémoire mesuré n’est affirmé.

**Solution recommandée :** pousser les critères et agrégats pertinents vers SQL, puis sélectionner un lot borné avec ordre déterministe et prise en compte de la file transmise. Préserver la priorité admin, les critères d’intérêts/fréquence et le départage actuel. Ne pas poser un simple `limit(5)` avant le classement : cela changerait le résultat.

**Validation :** tests de classement et absence de doublons, contraintes de visibilité/blocage, jeu de données représentatif, nombre de lignes hydratées et mémoire. Sans correction, le coût reste lié à l’audience entière plutôt qu’au lot demandé. Risque élevé : résultat fonctionnel du classement.

### 9. Le calendrier multiplie les requêtes par jour du mois

**Avéré — P2.** `app/Data/VisitWorkspaceData.php:24–31` parcourt les jours non passés et lance pour chacun un `count()` puis un `limit(3)->get()`. `:59–63` ajoute les conditions de visibilité, sous-requêtes et relations de profil/avatar.

**Impact :** un futur mois de 31 jours entraîne au moins 62 requêtes principales de comptage/aperçu, auxquelles s’ajoutent les chargements de relations lorsque des lignes existent et les autres données de la page. Le coût structurel est établi ; sa latence en production ne l’est pas.

**Solution recommandée :** traiter l’intervalle du mois en une requête ou un petit nombre de requêtes, puis produire agrégats et aperçus bornés en conservant exactement les scopes de confidentialité. Une liste de toutes les visites de tous les membres en mémoire serait une mauvaise substitution. Examiner `EXPLAIN` avant d’ajouter des index.

**Validation :** comparer les résultats par jour, recherches littérales, audience matches, masquage, blocages et période passée ; fixer un budget de requêtes sur le calendrier. Sans correction, chaque interaction reconstruisant le mois conserve ce coût.

### 10. Les représentations de groupe et statistiques partenaires déclenchent des requêtes par élément

**Avéré — P2.** Trois chemins se cumulent :

- `app/Data/GroupChatData.php:32–34` vérifie la réaction du lecteur par requête pour chaque message, même avec les compteurs préchargés ; dix messages peuvent ajouter dix requêtes `exists`.
- `:45–54` effectue une lecture du curseur et un comptage par chat. La construction de la catégorie groupes de `app/Http/Controllers/ConversationIndexController.php` les appelle dans une collection.
- `GroupChatData.php:16–17` appelle deux fois le calcul du mode lecture seule ; `app/Models/GroupChat.php:52–59` interroge `trip()->first() ?? event()->first()` à chaque appel au lieu de réutiliser une relation déjà chargée. Pour un événement, la recherche de trajet vide précède celle de l’événement.

**Extension backend :** `app/Http/Controllers/Admin/PartnerStatisticsController.php:31–39` charge toutes les annonces, puis appelle `PartnerAnnouncementCooldown::nextAvailableAt()` pour chacune. `app/Support/PartnerAnnouncementCooldown.php:30–48` relit le réglage global et cherche le dernier envoi du profil : **deux lectures SQL supplémentaires par annonce**, même lorsque plusieurs annonces appartiennent au même profil. Les `with` / `withCount` déjà présents ne suppriment pas ces lectures. Le contrôleur de statistiques partenaire et la liste de ses annonces chargent également tout l’historique ; leur coût en production reste à mesurer.

**Solution recommandée :** pour les statistiques, paginer l’historique, lire le réglage une fois et précharger les départs d’envoi nécessaires par profil. Préserver l’exclusion de l’annonce courante : un simple `MAX` incluant celle-ci changerait le résultat. Ne pas modifier la méthode transactionnelle et ses verrous uniquement pour accélérer cet affichage. Pour les groupes, charger le statut de réaction du lecteur avec `withExists`, agréger les non-lus pour la page, charger le contexte une fois pour la représentation et calculer une seule raison de lecture seule. Garder la vérification courante des droits lors des mutations et diffusions : une optimisation de DTO n’autorise pas à mettre en cache des permissions indéfiniment.

**Validation :** mêmes charges utiles et droits, listes de plusieurs groupes, pagination de messages ; compteur SQL démontrant une croissance bornée par page plutôt que par élément. Pour les statistiques, tester plusieurs annonces du même profil, l’exclusion de l’annonce courante, l’absence d’envoi précédent et la frontière du délai ; étendre `tests/Feature/Partner/PartnerStatisticsTest.php`. Sans correction, le coût augmente avec chaque groupe et chaque message affiché.

### 11. Le blocage conserve un historique que le PRD dit inaccessible

**Écart de contrat — P2, décision produit avant changement.** `docs/PRD.md:149–152` annonce que le blocage rend la conversation inaccessible aux deux membres. `app/Policies/ConversationPolicy.php:11–17` autorise la consultation selon l’appartenance au match sans examiner le blocage ou l’archive. `tests/Feature/ConversationTest.php:27` exige explicitement que les deux membres gardent l’historique mais ne puissent plus envoyer.

**Problème :** le test et la Policy sont cohérents entre eux ; ils divergent de la promesse du PRD. Ce n’est pas une régression nouvellement démontrée. La documentation de sécurité parle aussi d’archivage : les références produit doivent converger.

**Solution recommandée :** décider si le blocage cache l’historique ou arrête uniquement les nouvelles interactions. Mettre ensuite à jour ensemble PRD, documentation, Policy, réponses HTTP, UI et tests. Ne pas « corriger » le test sans cet arbitrage. Le n° 1 concerne la livraison différée, qui doit être décidée explicitement dans les deux variantes.

**Validation :** auteur et destinataire du blocage, lien direct deviné, liste, messages, réactions et notifications ; inclure le déblocage. Sans décision, la conformité du comportement ne peut pas être évaluée sur un contrat unique.

### 12. L’export des propres messages dépend de la visibilité actuelle de l’autre membre

**Écart de contrat — P2, décision produit avant changement.** `app/Actions/BuildUserDataExport.php:52–53` sélectionne les conversations via `forMember(...)->withVisibleParticipant(...)` avant d’exporter les messages de l’auteur. `tests/Feature/Settings/DirectUserDataExportTest.php:132` exige explicitement l’exclusion des conversations cachées de la liste ; le test ne manque donc pas, il fixe la restriction.

**Problème :** masquer, bloquer ou rendre indisponible l’interlocuteur peut faire disparaître de l’export des messages écrits par le demandeur et encore conservés. `docs/PRD.md:317` annonce un export des messages sans expliciter cette dépendance. Il ne faut pas confondre consultation sociale et périmètre d’un export personnel, ni exporter pour autant les messages privés de l’autre personne.

**Solution recommandée :** définir explicitement quelles données propres conservées sont exportables quand un pair est masqué, bloqué, inactif ou supprimé ; adapter une requête d’export dédiée si nécessaire. Documenter les exceptions de conservation et les données volontairement exclues. Aucune conclusion de conformité juridique n’est tirée ici.

**Validation :** matrice des états du pair, messages propres et reçus, conservation des données réellement encore présentes, absence de fuite de données du pair. Sans décision, l’export conserve une restriction difficile à déduire du contrat public.

## Continuité des groupes et suspicions de concurrence

### 13. La reconnexion de groupe ne rattrape pas explicitement tous les messages manqués

**Avéré statiquement ; fusion reproduite en sonde isolée — P2.** La passe frontend confirme le mécanisme précédemment classé comme suspicion. `resources/js/components/groups/GroupChat.vue:195–208` recharge les props de messages/panneau à la reconnexion, sans dernier ID reçu. Sur les pages de conversation de groupe, `app/Data/EventWorkspaceData.php:62–74` et `app/Data/TripWorkspaceData.php:22–34` renvoient les dix messages les plus récents pour la première page. `resources/js/lib/groupChatState.ts:3–15` conserve les anciens messages et fusionne uniquement les nouveaux IDs fournis.

**Preuve concrète :** ancien dernier message 100 ; quinze messages créés pendant la coupure ; réponse de première page 106 à 115 ; la fonction réelle de fusion produit `[100,106,107,108,109,110,111,112,113,114,115]`. Aucun code de rattrapage ne demande 101 à 105 dans ce chemin. `GroupChat.vue:64–85,186–192` peut ensuite marquer le dernier ID 115 comme lu au montage ou à la notification personnelle. L’historique peut être conservé à l’écran sans indiquer cet intervalle absent. Ce n’est pas une perte SQL : la pagination historique peut récupérer ces messages. La sonde valide la fusion réelle et les bornes des réponses ont été lues dans le backend ; elle ne remplace pas une reconnexion Reverb dans Chromium.

**Correction recommandée :** récupérer les messages après le dernier ID connu, par lots bornés jusqu’au rattrapage complet, puis fusionner et avancer la lecture seulement selon la visibilité effective (n° 7). Reprendre le contrat de rattrapage de la conversation privée et l’adapter au contexte groupe ; ne pas simplement augmenter arbitrairement la taille de page. Préserver les anciens messages, les réactions et la position de défilement.

**Tests nécessaires :** déconnexion avec 0, 1, 10, 15 et 25 messages entrants ; aucun ID manquant ni doublon ; réception HTTP/Echo simultanée ; historique déjà paginé ; onglet masqué ; accès révoqué pendant la coupure. Vérifier événement et trajet, page dédiée et panneau. **Risque moyen**, lié à la fusion Inertia et aux curseurs ; **effort 1–2 j**, avec test navigateur encore à exécuter.

### 14. Le verrouillage d’envoi de groupe n’est pas coordonné explicitement avec les retraits

**Mise à jour, lot 7 :** concurrence confirmée et corrigée pour les messages et
réactions de groupe. Voir la section « Lot 7 » pour les preuves et limites.
La description ci-dessous conserve l’état constaté avant correction.

**Suspicion — P2 potentiel.** `app/Actions/SendGroupChatMessage.php:17–28` verrouille le chat, évalue la Policy puis insère. Les Actions de retrait d’événement (`app/Actions/RemoveEventParticipant.php`, `app/Actions/WithdrawFromEvent.php`) verrouillent le contexte/l’inscription ; celles des trajets verrouillent aussi des utilisateurs. Elles ne partagent pas toutes un verrou explicite de chat avec l’envoi. `SyncManageableUserRoles` verrouille l’utilisateur, pas le chat.

**Scénario à vérifier :** révocation validée entre lecture d’éligibilité et insertion du message, ou attente cyclique liée aux verrous de FK. L’isolation MySQL et les verrous induits par les insertions peuvent modifier l’issue : cette lecture de code ne démontre ni contournement déterministe ni interblocage reproductible.

**Investigation / solution si confirmé :** deux connexions MySQL isolées avec barrières entre vérification, retrait et insertion ; tester événement, trajet, rôle et ban. Définir ensuite le point de sérialisation et l’ordre des verrous à partir des contraintes existantes. Ne pas ajouter un verrou dans un ordre inverse à celui des suppressions. Sans investigation, la garantie « plus d’écriture après retrait validé » reste insuffisamment établie.

### 15. Le marquage privé peut dépasser le maximum annoncé comme lu

**Mise à jour, lot 7 :** concurrence confirmée sous MySQL et mise à jour bornée
par le maximum annoncé. Voir la section « Lot 7 » pour les tests exécutés.
La description ci-dessous conserve l’état constaté avant correction.

**Suspicion — P2 potentiel.** `app/Actions/MarkConversationRead.php:29` calcule le maximum des messages non lus, puis `:35` met à jour la requête sans condition `id <= maximum`. L’événement à `:37–41` conserve le maximum calculé avant la mise à jour. Cette Action ne prend pas le verrou de conversation utilisé par l’envoi.

**Scénario à vérifier :** arrivée d’un nouveau message entre le `MAX` et l’`UPDATE`. Les lectures de mise à jour MySQL peuvent englober une ligne plus récente que la lecture initiale selon l’ordonnancement et l’isolation : un message pourrait avoir `read_at` alors que le reçu ne l’inclut pas. Pas de reproduction concurrente exécutée.

**Investigation / solution si confirmé :** test à deux connexions imposant cet intervalle ; borner la mise à jour par le maximum retenu ou choisir une stratégie de curseur cohérente. Inclure l’acquittement des notifications dans les assertions. Sans investigation, cohérence du reçu et état en base ne sont pas démontrés sous concurrence.

## Entretien à faible risque

### 16. Un helper de non-lus n’a aucun appel applicatif

**Avéré — P3.** `resources/js/lib/groupChatState.ts:16` exporte `groupChatUnreadIncrement`. Recherche de références dans tout le dépôt, fichiers cachés inclus, hors dépendances, `.git`, stockage et build : seules la déclaration, les assertions de `tests/Frontend/groupChatState.test.js`, celles de `tests/Frontend/eventChatState.test.js` et une ancienne documentation de plan apparaissent. Aucun appel de production trouvé.

**Problème :** les tests de ce helper ne prouvent rien sur le calcul effectivement utilisé à l’écran. Ils peuvent détourner l’attention du n° 7. Ce constat ne s’étend pas aux fonctions de fusion ou brouillon du même fichier, qui sont utilisées.

**Solution recommandée :** retirer le helper et ses assertions isolées si aucune intégration n’est prévue, puis tester le comportement réel des non-lus ; sinon justifier un usage concret avant de l’intégrer. Aucune suppression effectuée pendant cet audit. Sans correction, petite dette de compréhension, sans impact mesuré sur le bundle de production.

### 17. Les procédures de production décrivent deux états incompatibles

**Avéré — P3.** `README.md:99` indique que Redis et MinIO restent dans la stack de production. `compose.production.yaml` contient les services applicatifs ; `docs/operations.md:23–38` décrit Redis indépendant et Garage. `docs/operations.md:66` dit encore que le workflow demande le déploiement, alors que les sections de livraison contrôlée plus bas distinguent publication de l’image et déploiement volontaire.

**Problème :** un opérateur peut préparer de mauvaises ressources ou supposer qu’une image publiée est déjà déployée. Les divergences concernent la documentation versionnée ; aucun état réel de Coolify n’a été vérifié.

**Solution recommandée :** aligner le résumé README et le premier déploiement sur le Compose et le processus de release courant, conserver les anciennes procédures uniquement dans des sections de migration clairement datées. Vérifier les références croisées à Redis/Garage et aux déclencheurs de déploiement.

**Validation :** lecture de la procédure depuis un environnement vierge, sans effectuer de déploiement pendant la correction documentaire. Sans correction, le risque opérateur persiste malgré des scripts plus encadrés.

## Architecture, duplication et style : conclusions complémentaires

Les duplications à réduire en priorité sont **les critères d’éligibilité qui divergent**, pas toutes les expressions semblables. Les requêtes de visite/trajet, les Policies et les canaux ont des temporalités et des objets différents. Une abstraction ne doit partager que les invariants réellement communs et nommés, puis laisser les règles de chaque domaine explicites.

Les conversations d’événement et de trajet réutilisent déjà `GroupChat`, `GroupChat.vue`, les données et les composants de messages. Proposer leur fusion comme s’il s’agissait de deux implémentations indépendantes serait trompeur. Les petits doublons de fusion d’état privé/groupe ne justifient pas à eux seuls une infrastructure générique supplémentaire.

Les fichiers d’administration et d’onboarding les plus longs méritent une revue d’évolution, mais leur nombre de lignes n’établit pas un bug. `VisitWorkspaceData`, `TripWorkspaceData` et `PrepareMemberDataForDeletion` concentrent des chaînes et tableaux denses : découper quelques étapes nommées aiderait la revue des règles et des verrous. **C’est une amélioration de lisibilité**, à faire dans une correction liée et testée, pas un chantier préalable de réécriture. Aucun gain fonctionnel ou de performance n’est attribué à un simple reformatage.

Aucune dépendance entière ni composant UI n’est déclaré inutile sans preuve. Des helpers tels que `useInitials` et `useResponsiveModal` ont des usages ; ils ne font pas partie du code mort établi. Aucun paquet n’est proposé à la suppression sur la seule base de son nom ou de sa faible fréquence d’utilisation.

## Protections existantes à préserver

- Autorisations serveur présentes pour les écritures sensibles ; un `authorize(): true` de Form Request n’implique pas à lui seul une route ouverte lorsque l’Action contrôle la Policy.
- Envoi privé et réactions utilisent des verrous et vérifient l’accès ; le blocage arrête l’envoi ordinaire. Les défauts différés identifiés ne signifient pas une absence générale de contrôle.
- Le transport Reverb recontrôle activité et conditions courantes, avec traitement spécifique bêta. Ne pas le supprimer pour corriger une Policy métier.
- Les notifications privées/push recontrôlent déjà l’accès à la conversation et les préférences. Elles fournissent des comportements à comparer, sans supposer que toute diffusion bénéficie des mêmes gardes.
- Unicités et FK sur paires de membres, réactions et inscriptions ; contexte de groupe unique événement/trajet, avec contrainte d’exclusivité. Aucun manque généralisé de contraintes SQL établi.
- Suppression différée du compte avec nouvelle vérification du statut, révocation des abonnements et tentative de révocation des sessions SQL (limite Redis au n° 26), puis préparation des relations conservées. Les règles de rétention des trajets ne doivent pas être effacées par une simplification globale.
- Pipeline d’annonces partenaires avec identifiants de livraison, instantanés, insertion dédupliquée et traitement par lots. Ne pas remplacer cela par un envoi sans état au nom de la simplicité.
- Séparation des ressources externes, migrations explicitement opérées, images de livraison et protections nginx. Le service worker ne fournit pas de preuve de mise en cache générale des pages privées : aucune fuite de cache de ce type n’est établie.

## Contrôles exécutés et portée des résultats

Tous les résultats ci-dessous proviennent de commandes exécutées pendant cet audit. Ils ne remplacent pas les reproductions proposées dans les constats.

| Contrôle                                                         | Résultat                                                                                                                                | Interprétation                                                                                                                                      |
| ---------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------- |
| `bun run test:unit`                                              | **146 tests réussis**, 31 fichiers, 232 assertions                                                                                      | Tests frontend existants ; pas une validation navigateur des nouveaux scénarios                                                                     |
| `vendor/bin/phpstan analyse --memory-limit=512M --debug`         | **0 erreur**                                                                                                                            | Analyse statique PHP réussie sans parallélisme                                                                                                      |
| `vendor/bin/pint --test`                                         | **Réussi**                                                                                                                              | Format PHP existant conforme                                                                                                                        |
| `bun run format:check`                                           | **Réussi**                                                                                                                              | Format frontend existant conforme                                                                                                                   |
| `bun run types:check`                                            | **Échec : 9 TS2307**                                                                                                                    | Modules Wayfinder locaux absents, dont visites, signalements de départ et préférences de découverte ; ne démontre pas un défaut des sources suivies |
| `bun run lint:check`                                             | **Échec : 7 erreurs `import/order`**                                                                                                    | Fichiers liés aux mêmes modules générés absents ; lien probable avec la résolution locale, à confirmer après génération en environnement dédié      |
| `composer analyse` / `composer lint:check`                       | Parallélisme initial bloqué par `EPERM` sur socket locale                                                                               | Contournement par les commandes série ci-dessus ; ne pas confondre avec un échec d’analyse du code                                                  |
| `php tests/Support/verify-test-database.php`                     | Premier essai : `Operation not permitted` ; nouvelle tentative autorisée en lecture seule : `SQLSTATE[HY000] [2002] Connection refused` | Base MySQL de test inaccessible ; suites backend et Browser non exécutées ; aucune base modifiée                                                    |
| Validateur Laravel et Carbon, bootstrap console sans requête SQL | `not-a-date` lève `InvalidFormatException` ; `2027-03-28T02:30` accepté puis normalisé en `03:30 +02:00`                                | Reproduction locale du n° 18, sans requête HTTP authentifiée ni écriture                                                                            |

Les fichiers Wayfinder n’ont pas été régénérés : cela aurait pu modifier d’autres fichiers que le rapport. La CI prévoit leur génération ; **la CI courante n’a pas été consultée et n’est pas déclarée rouge sur la base de cet environnement local**. Lint et TypeScript doivent être relancés après préparation conforme, sans classer prématurément ces erreurs comme régressions applicatives.

Ni `composer ci:check`, ni build Vite, ni build Docker, ni tests de charge n’ont été exécutés. Pas d’audit externe de vulnérabilités des dépendances, de validation des secrets réels, de configuration Coolify/Redis effective, ni de contrôle des sauvegardes restaurées. Aucun succès de ces contrôles n’est implicite.

## Couverture : modules examinés et travail restant

| Domaine                          | Inspection réalisée                                                                                                  | Inspection approfondie restant à mener                                                                                                                                                          |
| -------------------------------- | -------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Authentification / identité      | Routes, middleware, rôles, invalidation e-mail, points d’entrée sécurité, callbacks sociaux et protections de statut | Matrice complète mot de passe/Google/passkeys/2FA, récupération et révocation multi-appareils ; politique de second facteur selon méthode d’entrée                                              |
| Profils / découverte / swipes    | Service, Policy, Action transactionnelle, requêtes, références et tests associés                                     | Benchmark SQL et mémoire sur audience représentative ; toutes les combinaisons de filtres et changements concurrents                                                                            |
| Messages privés                  | Envoi, blocage, lecture, réactions, canaux, événements différés, notifications, rafraîchissement frontend            | Reverb réel et délais de file ; concurrence lecture/envoi ; comportement de présence/frappe après chaque type de révocation                                                                     |
| Événements / groupes             | Inscriptions et retraits, Policy, messages système, données, frontend partagé, diffusions                            | Tests concurrents retrait/envoi, navigateur après longue coupure, revue exhaustive de chaque transition organisateur/participant                                                                |
| Visites                          | Scopes, audience, calendrier, profil contextualisé et filtres                                                        | Plans MySQL, budget de requêtes, validation navigateur complète mobile et accessibilité                                                                                                         |
| Trajets                          | Éligibilité, visibilité, inscriptions/départs, capacité et rétention, rattachement au chat                           | Stress à deux connexions sur dernier siège, départ/retrait/purge ; revue exhaustive des signalements et états de modération                                                                     |
| Partenaires                      | Rôles, révisions, pipeline d’annonce/livraison, préférences, instantanés et agrégats                                 | Tous les écrans admin/partenaire, conservation détaillée, métriques sous forte concurrence et envois multi-workers                                                                              |
| Export / suppression / rétention | Sélection des données, tests de visibilité, préparation et purge du compte, données de trajets conservées            | Export volumineux et consommation mémoire ; interruptions de purge et restauration ; rapprochement complet avec politique de conservation                                                       |
| Jobs / notifications / e-mails   | Délais de file versionnés, récapitulatif, livraisons et gardes d’accès                                               | Paramètres effectifs, comportement du fournisseur en acceptation incertaine, reprise après arrêt brutal de worker                                                                               |
| Frontend / UI                    | Inventaire, composants communs, états de chat, principaux formulaires et références de helpers                       | Lecture ligne par ligne des 418 fichiers non faite ; clavier/lecteurs d’écran, FR/EN exhaustif, PWA sur vrais appareils, toutes les pages admin                                                 |
| Base de données                  | Inventaire des 53 migrations, contraintes structurantes, FK et politiques de suppression des domaines sensibles      | Exécution des migrations montée/retour dans une copie isolée, index et plans réels, contention, volumes et restauration de sauvegarde                                                           |
| Tests                            | Inventaire des suites, tests existants confrontés aux contrats, 146 tests frontend exécutés                          | Suites backend/Browser non exécutées ; manque de preuve expérimentale pour les trois suspicions et les révocations websocket                                                                    |
| Infrastructure / CI              | Docker, nginx, entrypoint, Compose, workflows et processus documenté                                                 | Construction et démarrage de l’image ; audit ligne par ligne des scripts opérateur longs (`operations.py`, `release.py`, accès bêta), environnement distant et sécurité des ressources externes |
| Dépendances                      | Manifests, locks et usages associés aux chemins étudiés                                                              | Advisory scan à jour, inventaire des dépendances transitives et reproductibilité complète ; aucune suppression de paquet recommandée sans cette étape                                           |

Les tests actuels montrent un investissement réel, mais certains protègent une règle différente du PRD (n° 11 et 12). Ajouter davantage de tests sans clarifier ces règles ne résoudrait pas le désaccord. Les scénarios de concurrence et de reconnexion sont des trous de preuve prioritaires, pas une raison d’écarter les suites existantes.

## Plan de refactoring de la première passe

Ce plan conserve le contexte de la première passe. La liste finale dédupliquée en fin de rapport fait autorité pour l’ordre de correction.

### Étape 0 — Fixer les décisions et rendre la vérification reproductible

1. Valider le comportement après blocage : historique, nouvelles livraisons, réactions et déblocage (n° 1 et 11).
2. Valider la matrice d’éligibilité actuelle pour HTTP, cibles, historique et diffusion, ainsi que les réglages des comptes sans rôle membre (n° 2–4).
3. Définir le périmètre d’export des données propres et la garantie réaliste de livraison e-mail (n° 6 et 12). Clarifier si les annonces partenaires doivent produire un push (n° 20).
4. Préparer un environnement isolé avec Wayfinder et MySQL de test, puis obtenir la référence complète des contrôles. Ne pas utiliser la base de développement ou de production pour les tests de concurrence.

Livrable : quelques décisions explicites et des tests de caractérisation. Aucun besoin de redessiner l’application.

### Étape 1 — Corriger les révocations avant de déplacer du code

Petites PR distinctes : diffusion privée différée (n° 1), lecteurs de chat d’événement (n° 2), puis éligibilité des cibles (n° 3). Écrire d’abord les régressions qui échouent pour la bonne raison. Vérifier les rôles cumulés, le statut et les conditions, les transitions d’adresse, et les droits à la consommation du job. Conserver une séparation claire entre droit d’historique et droit d’interaction.

Critère de sortie : tests HTTP/Policy/événements cohérents, puis test réel d’abonnement préexistant. Les comptes ban ou supprimés ne doivent pas regagner un accès au passage.

### Étape 2 — Fiabiliser lecture, file et livraison

Corriger la lecture en arrière-plan (n° 7) et la marge de timeout (n° 5), la reprise de réservation push (n° 19), puis la livraison de récapitulatif (n° 6) selon le contrat validé. Corriger la validation des dates d’événement dans une PR locale distincte (n° 18). Corriger le rattrapage de groupe confirmé au n° 13 avec les tests frontend décrits plus bas. Reproduire séparément les n° 14–15 ; ne modifier les verrous qu’après preuve et définition de leur ordre. Faire la décision d’accès aux réglages et aux exports dans des PR séparées, car elle change un comportement actuellement testé.

Critère de sortie : navigateur masqué/reconnecté, scénario de job interrompu et tests à deux connexions. Pas d’affirmation « exactement une fois » sans mécanisme externe prouvé.

### Étape 3 — Réduire les coûts mesurables

Commencer par les requêtes de groupe (n° 10), correction assez locale ; traiter ensuite le calendrier (n° 9) et la découverte (n° 8), plus sensibles au résultat métier. Relever requêtes, lignes hydratées, mémoire et temps avant/après sur un jeu synthétique explicite. Garder les mêmes règles d’audience, classements, recherches et blocages.

Critère de sortie : résultats fonctionnels identiques et budget de requêtes/volume borné. Aucun index ou cache supplémentaire sans plan et mesure justifiant sa présence.

### Étape 4 — Nettoyage vérifié et documentation

Retirer le seul helper mort établi (n° 16), aligner les procédures (n° 17) et rendre plus lisibles les portions modifiées pendant les corrections. Vérifier de nouveau les références avant suppression. Ne pas mélanger grand nettoyage UI, changements métier et corrections de confidentialité dans la même PR.

Pour chaque correction : cycle rouge/vert/refactor prévu par `AGENTS.md`, contrôles ciblés puis ensemble pertinent PHP/frontend ; build et image si l’infrastructure est concernée. Aucune migration automatique à l’entrypoint. Réviser ce rapport après correction pour séparer ce qui est résolu, confirmé ou écarté.

## Verdict et éléments non vérifiés

Le dépôt dispose d’une structure cohérente et de protections réutilisables. Le chantier prioritaire est la **cohérence des droits dans le temps**, puis la fiabilité de livraison et les coûts de lecture. Une refonte globale augmenterait le risque sans traiter directement les défauts établis.

Le gain attendu est une suppression de chemins de diffusion indus et une réduction du travail proportionnel à l’audience, aux jours ou aux éléments. Aucun pourcentage d’économie, estimation de coût d’exploitation ou amélioration de latence n’est défendable sans mesure : ils ne sont donc pas inventés.

**Non vérifiés expérimentalement :** trois scénarios de concurrence/reconnexion, suites backend et Browser, environnement de production, fournisseurs externes, restauration de sauvegardes, charge, sécurité des dépendances à jour et accessibilité exhaustive. Les preuves statiques et les contrôles réussis ci-dessus ne valent pas certification de sécurité de l’ensemble du service.

## Complément — audit systématique du backend Laravel

Cette seconde passe porte sur `app/`, les quatre fichiers de `routes/`, les 53 migrations, les factories/seeders et les 18 fichiers de `config/`, avec lecture des tests backend associés. Elle prolonge le même commit, sans changement applicatif. Les nouveaux constats sont numérotés 18–20 pour préserver les références précédentes. Le n° 10 intègre désormais les statistiques partenaires. Les catégories de résultats et les limites expérimentales du début du rapport s’appliquent aussi à cette section.

### 18. Le validateur d’événement peut lever une exception et normaliser une heure inexistante

**Avéré, reproduit localement sans SQL — P2. Effort : 0,5–1 jour. Risque de régression : moyen.**

**Fichiers et usages :** `app/Http/Requests/StoreEventRequest.php:21–34`, `app/Http/Requests/UpdateEventRequest.php:5`, `app/Actions/CreateEvent.php:17–21`, `app/Actions/UpdateEvent.php:33–37`, création et mise à jour dans `EventController`. `UpdateEventRequest` hérite intégralement des règles de création : les deux mutations sont concernées.

La règle `date_format` est suivie d’une closure Carbon sans `bail` ni contrôle préalable des erreurs. Laravel poursuit les règles du champ après un échec de format ; une chaîne non analysable arrive donc à `createFromFormat` et lève `Carbon\Exceptions\InvalidFormatException`. Une entrée utilisateur invalide peut sortir du circuit normal d’erreur de validation et devenir une erreur serveur.

Autre conséquence du même chemin de conversion : `date_format` valide le format, pas l’existence de l’heure à Paris. Le **28 mars 2027 à 02:30 n’existe pas** dans ce fuseau. Carbon la normalise en 03:30 ; les Actions utilisent la même conversion et ne rejettent pas ce déplacement.

**Vérification exécutée :** bootstrap console Laravel puis `Validator::make(['starts_at' => $value], (new StoreEventRequest)->rules())`. Les autres champs manquants produisent leurs erreurs ordinaires ; les résultats ci-dessous portent uniquement sur `starts_at`.

| Entrée             | Résultat observé                                                           |
| ------------------ | -------------------------------------------------------------------------- |
| `not-a-date`       | Exception `Carbon\Exceptions\InvalidFormatException` pendant la validation |
| `2027-03-28T02:30` | Aucune erreur sur `starts_at` ; conversion en `2027-03-28 03:30:00 +02:00` |
| `2027-06-01T12:30` | Aucune erreur sur `starts_at` ; conversion normale en `12:30:00 +02:00`    |

Cette reproduction ne constitue pas un test HTTP authentifié. Elle établit le défaut du validateur appelé par les routes ; aucun événement n’a été créé. Les secondes sont bien à zéro dans le contrôle normal : aucun défaut supplémentaire de secondes héritées n’est retenu.

**Correction recommandée :** reprendre le mécanisme déjà présent dans `StoreTripRequest` : arrêt sur erreur de format, contrôle temporel après validation de base, analyse explicite dans `Europe/Paris`, puis comparaison aller-retour du format local pour refuser la normalisation d’une heure inexistante. Conserver les règles propres aux événements, sans importer automatiquement l’horizon ou les limites des trajets. Utiliser une traduction existante ou dédiée pour l’erreur. Une petite duplication explicite est préférable à un nouveau service de dates générique pour deux formulaires.

**Tests nécessaires :** étendre `CreateEventTest` et `UpdateEventTest` avec chaîne arbitraire, date impossible, type non chaîne, absence de date, heure du saut printanier, conversions hiver/été et frontière de date future. Vérifier réponse de validation et absence d’écriture ; caractériser aussi l’heure répétée en automne avant de changer sa convention. Les tests existants couvrent déjà date passée, capacité et conversion normale, mais pas ces entrées. Pour une requête Inertia, vérifier les erreurs du formulaire ; pour une requête JSON, le statut 422.

### 19. Une réservation push abandonnée peut faire acquitter la seule tentative de reprise

**Avéré par le chemin de contrôle — P2. Déclencheur : arrêt brutal du worker. Effort : 1–2 jours. Risque de régression : moyen.** Aucun arrêt de worker ni incident de livraison n’a été reproduit pendant cet audit.

**Preuves :** `app/Actions/DeliverWebPushNotification.php:48–57` ne reprend une ligne `sending` qu’après dix minutes ; avant ce délai, l’échec de réservation retourne normalement. `app/Jobs/SendWebPushNotification.php:23–36` ne distingue pas ce retour d’une livraison terminée et ne libère pas le job pour plus tard. Le gestionnaire Laravel installé, `vendor/laravel/framework/src/Illuminate/Queue/CallQueuedHandler.php:100–101`, supprime le job qui termine sans avoir été supprimé ou libéré explicitement. Le délai Redis versionné est de 90 secondes ; aucun rattrapage des lignes `sending` n’est planifié dans `routes/console.php`.

**Scénario établi :** réservation SQL passée à `sending` ; processus tué avant le `catch` qui remettrait l’état à `retrying` ; le job redevient disponible à l’expiration de la réservation de file, avant dix minutes ; sa reprise trouve la réservation applicative encore récente, retourne, puis est acquittée. La ligne restera `sending` sans nouveau travail automatiquement prévu. Les quatre essais et le backoff du job ne s’appliquent pas à un retour réussi. Une exception de transport ordinaire est déjà traitée : ce constat concerne l’interruption qui empêche ce traitement.

**Couverture existante :** `tests/Feature/WebPushDeliveryTest.php:235–261` vérifie bien la récupération d’une réservation vieillie artificiellement de onze minutes, puis appelle directement l’Action. Il ne vérifie pas la reprise réelle avant dix minutes ni l’acquittement du job. Ce test reste utile ; il ne prouve pas l’absence du scénario ci-dessus.

**Correction recommandée :** rendre explicite le résultat « réservation occupée » et prévoir une reprise différée jusqu’à expiration, ou un rattrapage durable équivalent. Réutiliser `release()` et les mécanismes de job Laravel avec un budget d’essais cohérent ; distinguer livraison définitivement terminée et travail temporairement occupé. Préserver l’unicité notification/abonnement et éviter deux transports concurrents. Ne pas simplement relancer une exception toutes les 60 secondes jusqu’à épuiser les essais avant la fin du bail. La marge worker/Redis du n° 5 reste une correction indépendante : elle ne supprime pas un arrêt OOM ou système.

**Tests nécessaires :** réservation laissée `sending`, reprise à 90 secondes, absence d’appel réseau et nouvelle disponibilité garantie ; reprise après dix minutes ; deux workers sur le même identifiant ; succès terminal non renvoyé ; préférence désactivée ou abonnement révoqué pendant l’attente ; exception réseau classique conservée. Au moins un test doit passer par le traitement du job et son état de file, pas seulement par l’Action.

### 20. Le push des annonces partenaires est déclaré mais contourné par le circuit de livraison

**Écart de contrat établi — P2, décision avant correction fonctionnelle. Effort : 0,5 jour de clarification puis 1–2 jours. Risque de régression : moyen.**

**Preuves et usages :** `app/Notifications/PartnerAnnouncementNotification.php:24–26` annonce `database`, `broadcast` et `WebPushChannel`. Pourtant `app/Actions/DeliverPartnerAnnouncement.php:69–71` appelle directement `DatabaseChannel::send()`, puis `:85` programme uniquement le job de diffusion. `app/Actions/BroadcastPartnerAnnouncement.php:33–44` construit un `BroadcastNotificationCreated` et exécute `BroadcastEvent` ; il n’appelle pas le canal push. La recherche des constructions de `PartnerAnnouncementNotification` dans `app/` retrouve ces deux chemins. Le `via()` n’est donc pas exécuté par ce pipeline et aucun `SendWebPushNotification` n’en résulte.

`tests/Feature/WebPushNotificationCoverageTest.php:17–29` instancie les classes par réflexion sans constructeur puis contrôle leur déclaration de canaux. Il peut passer alors que le flux réel ne parcourt jamais ces canaux. Les tests de dispatch partenaires couvrent la persistance et le broadcast, sans prouver un push réel de cette annonce.

**Contrat contradictoire :** `docs/PRD.md:352` décrit les alertes génériques pour toutes les catégories ; le code et ce test incluent explicitement les annonces partenaires. À l’inverse, `docs/technical-architecture.md:206` exclut l’envoi e-mail/push partenaire et `docs/documentation-inventory.md:126` confirme cette exclusion documentaire. Le fait établi est l’absence de push dans le circuit ; le considérer comme fonctionnalité manquante exige de trancher ces sources. Aucune exposition de contenu privé n’est déduite de ce constat.

**Correction recommandée :** si le contrat est « annonce uniquement dans l’application », aligner les déclarations et tests sur cette exception. S’il inclut le push, l’intégrer après commit avec l’identifiant stable de notification, préférences et éligibilité courantes, reprise indépendante de la persistance et du broadcast. Ne pas remplacer la livraison transactionnelle dédupliquée par un simple `notify()` qui ferait perdre ces garanties. Ne pas supprimer globalement la préférence `PartnerAnnouncements` : les notifications de décision partenaire utilisent aussi cette catégorie et leurs usages doivent être conservés.

**Tests nécessaires :** partir de la livraison d’une annonce à un destinataire éligible avec abonnement et configuration push ; vérifier le job attendu, ou son absence explicitement voulue après arbitrage. Couvrir préférence désactivée, compte devenu inéligible, retry après insertion réussie, même UUID et absence de double notification persistante. Garder le test déclaratif comme vérification de forme, pas comme preuve de couverture du pipeline.

### Architecture, responsabilités et duplication : conclusions de la seconde passe

- **Éligibilité distribuée, problème concret n° 2–4.** Les critères « actif », rôle, adresse vérifiée, profil et visibilité sont combinés différemment dans middleware, scopes et Policies. Le défaut est leur divergence sur des usages précis, pas leur présence à plusieurs couches. Réutiliser des scopes pour sélectionner des destinataires et des Policies pour les autoriser, avec des tests de matrice. Un unique booléen universel « peut tout voir » fusionnerait à tort événements, trajets, profils masqués et export.
- **Mutations correctement isolées dans beaucoup d’Actions.** Rôles, inscription/capacité, réactions, blocages, modération et suppression ont déjà des transactions et relectures. `MoveInterest` et `MoveAvatar` se ressemblent mais concernent deux petits catalogues aux dépendances différentes ; aucun gain démontré à créer un moteur générique d’ordonnancement. L’Action de publication des fiches partenaires utilise à juste titre un verrou global d’ordre.
- **Les classes de contexte ne sont pas mortes.** `EventChatAccessChanged` est un adaptateur vers le groupe partagé et reste appelé par acceptation, retrait, annulation et blocage d’inscriptions, avec tests dédiés. Ses références ont été recherchées dans l’application, les tests et le frontend. Ne pas le supprimer au motif que `GroupChatAccessChanged` existe aussi. Le seul helper mort établi reste le n° 16.
- **Verrous et rétention : complexité en partie nécessaire.** `PrepareMemberDataForDeletion`, `TripRetentionAccounts`, `LockTripAccounts`, les instantanés de départs/signalements et les FK devenues nullables protègent des données conservées après suppression. Leur longueur ne constitue pas une abstraction excessive. La suspicion n° 14 doit être testée contre ces ordres de verrouillage, sans simplifier d’abord les graphes de suppression.
- **Autre effet réseau sous verrou, même famille que le n° 6.** `BroadcastPartnerAnnouncement::handle()` appelle le broadcaster dans la transaction de livraison avant `broadcasted_at`. Un Redis/Reverb lent prolonge donc le verrou ; une diffusion acceptée suivie d’un échec de commit peut être renvoyée avec le même UUID. Le mécanisme de retry admin est réel : ne pas annoncer que toutes les erreurs partenaires sont irrécupérables. Mesurer la durée, conserver l’idempotence côté client et décider la garantie de reprise avant de déplacer l’effet externe hors transaction. Tester acceptation réseau puis échec SQL et répétition de la diffusion.
- **Style seulement, sans chantier prioritaire.** Quelques mutations restent dans des contrôleurs (`MemberProfileController`, catalogues admin) et certains tableaux/règles sont très compacts. Elles ne justifient pas automatiquement de nouvelles Actions. Découper uniquement lorsqu’une règle est réellement partagée ou que le déplacement permet de traiter un constat ; ne pas écrire des tests qui figent la répartition actuelle des méthodes.

### Matrice de couverture backend actualisée

« Approfondi » signifie lecture des chemins critiques, de leurs appelants, autorisations, modèles/migrations et tests nommés ; cela ne signifie pas exécution ni preuve pour chaque entrelacement concurrent. L’inventaire couvre tous les domaines du backend. Les petits adaptateurs et déclarations ont été parcourus avec leurs usages ; il ne s’agit pas d’une certification ligne par ligne des 443 fichiers.

| Module                                   | Parcours et structures inspectés                                                                                          | Tests backend confrontés au code, exemples                                                                                                | Résultat / approfondissement restant                                                                                                                            |
| ---------------------------------------- | ------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Routage et garde commune                 | `web.php`, `settings.php`, `channels.php`, `console.php`, bootstrap, middleware statut/rôle/social/conditions/profil/bêta | `TermsReacceptanceTest`, `BetaAccessTest`, `PartnerRoleAuthorizationTest`                                                                 | Routes cartographiées ; n° 2–4. Rejouer les matrices HTTP et sockets                                                                                            |
| Authentification                         | Fortify, création/reset, Google, inscription sociale, validation adulte, sessions, passkeys/2FA, quota e-mail             | suites `Auth/`, `Settings/SecurityTest`, `SocialAccountSchemaTest`                                                                        | Complément transversal : révocation Redis n° 26 et contrat Google/2FA n° 28 ; matrice récupération/second facteur encore à rejouer                              |
| Profil et onboarding                     | `MemberProfileRequest`, contrôleurs profil/onboarding, `AdvanceProductOnboarding`, avatars/intérêts, scopes de complétude | `MemberProfileTest`, `ProductOnboardingTransitionTest`, `ProfileSocialLinksTest`                                                          | Validations et réutilisations vérifiées ; concurrence statut/profil et budgets de lecture à mesurer                                                             |
| Catalogues et thèmes                     | CRUD/reclassement, `SyncProfileInterests`, `SetInterestStatus`, réglage unique, activation saisonnière                    | `ManageInterestCatalogTest`, `ManageAvatarCatalogTest`, tests Unit de synchronisation et thèmes                                           | Garde des usages avant suppression ; conserver règles des intérêts désactivés et sélections acquises                                                            |
| Découverte / swipe / match               | Service de classement, filtres, Policy profil, `CreateSwipe`, contraintes de paires                                       | `DiscoveryServiceTest`, `DiscoveryPreferencesTest`, `CreateSwipeConcurrencyTest`                                                          | N° 3 et 8 ; EXPLAIN et charge représentative manquants                                                                                                          |
| Messagerie privée                        | Liste/historique, envoi, lecture, réactions, blocage/déblocage, amorces                                                   | `SendMessageTest`, `MessageBroadcastTest`, `MessageReactionTest`, `ConversationTest`                                                      | N° 1, 11 et 15 ; tester révocation différée, lecture concurrente et clients déjà abonnés                                                                        |
| Événements                               | Création/mise à jour, inscriptions automatiques/manuelles, capacité, retrait, annulation, panels                          | `CreateEventTest`, `UpdateEventTest`, `EventRegistrationConcurrencyTest`, `EventVisibilityTest`                                           | N° 18 reproduit ; ne pas confondre tests de dernière place existants et preuve retrait/envoi manquante                                                          |
| Chat de groupe commun                    | Messages système, lecture, réactions, Policy, données, notifications dédiées, contexte événement/trajet                   | `GroupChatDeliveryTest`, `EventChatAuthorizationTest`, `GroupChatNotificationDeliveryTest`, `TripChatWorkspaceTest`                       | N° 2, 10, 14 ; backend de reconnexion n° 13 relié au frontend, sans nouvelle modification UI                                                                    |
| Visites                                  | Intervalles, visibilité, confirmation d’audience, requêtes d’attendees, calendrier, purge                                 | `ManageVisitTest`, `VisitsVisibilityTest`, `VisitDataControlTest`, `VisitWorkspaceTest`                                                   | N° 9 ; valider plans, lignes hydratées et budgets SQL                                                                                                           |
| Trajets                                  | Date/lieux, audience, sièges, demandes/décisions, départs, annulation, chat et révocation                                 | `TripScheduleTest`, `TripRegistrationConcurrencyTest`, `TripBlockingTest`, `UpdateTripTest`                                               | Verrou de capacité et contrôles plus complets que les événements ; tester envoi/retrait concurrent                                                              |
| Modération                               | Ban, rôles gérables, conversation admin, signalements privés/départs, clôture, audit                                      | `MemberBanTest`, `ManageMemberRolesTest`, `ConversationReportConcurrencyTest`, `TripDepartureReportConcurrencyTest`                       | Contrôles serveur, raisons et unicité des signalements inspectés ; nouvelle exécution MySQL nécessaire                                                          |
| Suppression / export / rétention         | Demande et purge différée, suppression admin, conservation ban/départs, exports, purges programmées                       | `BannedAccountRetentionTest`, `BannedRetentionConcurrencyTest`, `Console/TripRetentionConcurrencyTest`, `ModerationDataExportTest`        | N° 12 ; contrôler mémoire d’export, interruptions et concordance complète de conservation                                                                       |
| Fiches partenaires                       | Brouillon, image transformée, soumission, décision, publication, ordre et suppression                                     | `PartnerProfileTest`, `PartnerImageTest`, `Admin/PartnerProfileModerationConcurrencyTest`                                                 | Appelants admin et propriétaire approfondis ; résilience stockage/SQL et charge à reproduire                                                                    |
| Annonces partenaires                     | Cooldown, audience, livraison, rebroadcast/retry admin, finalisation, liens, lecture et métriques                         | `PartnerAnnouncementDispatchTest`, `PartnerAnnouncementEngagementTest`, `PartnerAnnouncementConcurrencyTest`, `PartnerStatisticsTest`     | N° 10 et 20 ; insertion dédupliquée, snapshots et métriques conservés ; tester worker interrompu                                                                |
| Centre et push                           | Propriété de notification, présentation des cibles, préférences, abonnements, transport, livraison                        | `NotificationIndexTest`, `WebPushDeliveryTest`, `WebPushNotificationCoverageTest`, `WebPushDataControlTest`                               | N° 19 ; les déclarations de canaux ne prouvent pas un appel réel. Mesurer aussi les lectures par notification de `MemberNotificationPresenter`                  |
| Événements / temps réel                  | 11 événements, canaux privés, Reverb sensible à l’identité, événements personnalisés, présence                            | `BannedDeferredDeliveryTest`, `BannedRealtimeAccessTest`, `MemberPresenceTest`, `EventChatAccessBroadcastTest`                            | N° 1–2 ; absence de dossier `app/Listeners`, orchestration via Actions/notifications. Tester transport réel et présence sous révocation                         |
| Jobs / e-mails / planification           | Six jobs, huit commandes, après-commit, retries, récapitulatif, purges et commande bêta                                   | `Mail/WeeklyEmailRecapTest`, `Console/DispatchDueAccountPurgesTest`, `Console/PurgeExpiredPartnerRecordsTest`, `BetaCopySanitizationTest` | N° 5–6 et 19 ; queue réelle et paramètres déployés non vérifiés                                                                                                 |
| Données / configuration / contenu public | 44 modèles, 53 migrations, factories, trois seeders, 18 configurations ; URL publiques, notes, images et liens sociaux    | tests `Database/`, `DatabaseSeederTest`, `InterestCatalogSeederTest`, `DeploymentReadinessTest`, `Rules/SafeHttpsUrlTest`                 | Contraintes et suppression parcourues ; aucune migration exécutée. Tester montée/retour isolés, schéma effectivement déployé et scans de dépendances séparément |

Les anciennes lignes de couverture qui laissaient ouverte la revue détaillée des mutations de modération, de rétention et du pipeline partenaire sont donc complétées par cette passe. Les validations MySQL, transport et charge restent ouvertes ; aucune cellule « inspecté » ne les présente comme réussies.

### Qualité des tests et protocole de vérification requis

**Points solides constatés :** les tests ne se limitent pas aux cas heureux. Des scénarios MySQL à processus et connexions distincts existent déjà pour le dernier siège d’événement et de trajet, les swipes croisés, la publication/réorganisation partenaire, les clics, les signalements et la rétention. Ils utilisent notamment `pcntl_fork`, synchronisation de processus et délai InnoDB. `TestDatabaseGuard` impose le pilote MySQL, le nom de base isolée et l’absence de configuration cachée avant l’utilisation de `Tests\TestCase`. Ne pas les remplacer par SQLite ou par une invocation de l’Action deux fois dans une même transaction.

**Limites concrètes :** les valeurs de test usuelles mettent la queue en `sync` et la diffusion à `null`. C’est utile pour de nombreuses règles, mais cela masque les intervalles entre écriture, retrait d’accès et consommation, ainsi que la réservation d’un worker arrêté. Les tests déclaratifs par réflexion des canaux (n° 20) et l’appel direct après vieillissement d’une réservation (n° 19) ne traversent pas la frontière qui fait défaut. Les tests affirmant l’accès à l’historique et la restriction de l’export (n° 11–12) ne doivent pas être inversés sans décision produit.

Ordre de validation recommandé pour les futures corrections :

1. Disposer d’un MySQL isolé et vérifier le garde avant toute suite qui migre ou remet les tables à zéro. Pendant cet audit, les deux tentatives de vérification ont échoué à se connecter ; aucune suite backend n’a donc été lancée.
2. Ajouter le test métier rouge du constat concerné ; pour les dates, commencer par les deux routes de création/mise à jour. Pour l’asynchrone, contrôler l’état après commit et consommer après mutation de l’éligibilité.
3. Pour n° 14–15, orchestrer deux connexions MySQL avec pauses déterministes au point critique. Les tests de dernière place déjà présents ne démontrent pas ces autres entrelacements.
4. Pour n° 19, inclure le mécanisme de réservation/acquittement/release du job. Une simple attente artificielle de onze minutes dans l’Action ne suffit pas.
5. Pour n° 8–10, mesurer requêtes, volume hydraté et mémoire avec plusieurs tailles synthétiques, puis vérifier les mêmes résultats métier. Aucun index supplémentaire ne doit être recommandé sur la seule forme d’une requête sans plan MySQL et cardinalité.
6. Après les tests ciblés, exécuter les contrôles PHP pertinents puis les contrats Inertia/frontend concernés. Le PHPStan et le format PHP déjà réussis sur ce commit ne prouvent pas la sémantique des livraisons différées.

### Ordre de travail backend mis à jour

| Rang                            | Lot concret                                                                            | Condition de démarrage / critère de sortie                                                                                              |
| ------------------------------- | -------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------- |
| 1                               | Révocation différée privée et lecteurs de groupe, n° 1–2                               | Décision sur livraison après blocage ; tests Policy/HTTP/worker puis websocket préexistant                                              |
| 2                               | Validation des dates d’événement, n° 18                                                | Correction locale réutilisant le mécanisme des trajets ; erreurs de validation, heures inexistantes rejetées, dates normales inchangées |
| 3                               | Réservations et reprise, n° 5 et 19                                                    | Paramètres effectifs relevés ; job interrompu repris sans perte ni envoi concurrent                                                     |
| 4                               | Éligibilité découverte, accès aux réglages/export et contrats, n° 3–4, 11–12 et 20     | Arbitrages explicités ; petits changements par domaine et scénarios de rôles cumulés                                                    |
| 5                               | Effets réseau et transactions, n° 6 et broadcast partenaire                            | Garantie de livraison décidée ; reprises en cas d’acceptation réseau incertaine et durée de verrou contrôlée                            |
| 6                               | Budgets SQL, n° 10 puis 9 puis 8                                                       | Mesures avant/après, préservation des audiences/classement et exclusion de l’annonce courante                                           |
| En parallèle des investigations | Reproduire n° 14–15 sur MySQL isolé ; n° 13 reste un parcours partagé avec le frontend | Ne pas modifier l’ordre des verrous ou la lecture sur la seule suspicion                                                                |
| Dernier                         | Nettoyage vérifié n° 16–17 et lisibilité locale                                        | Recherche d’usages refaite ; aucune généralisation de repository, moteur de workflow ou couche d’autorisation universelle               |

Le complément backend n’autorise aucune correction applicative. Le rapport reste le seul fichier créé ou modifié ; ni migration, ni seeding, ni dépendance, ni configuration n’a été changée pour obtenir ces résultats.

## Complément frontend — Vue 3 et TypeScript

Passe du 23 octobre 2026, sur le même commit. Application des principes Ponytail v5 : partir des usages, conserver les abstractions qui servent réellement, corriger localement les défauts observables, séparer les hypothèses des preuves. Aucun composant, composable, test ou fichier généré n’a été ajouté ou modifié. Les cinq nouveaux constats ci-dessous s’ajoutent au registre ; les n° 7 et 13 sont des problèmes frontend déjà enregistrés, pas deux nouveaux problèmes à compter.

### Cartographie et interactions examinées

L’inventaire couvre les 418 fichiers de `resources/js/`, dont 165 fichiers de primitives sous `components/ui/`, ainsi que `resources/css/app.css`, les catalogues FR/EN et les tests associés. Les fichiers générés Wayfinder sont des sorties d’outillage ; leurs absences locales sont traitées séparément des défauts applicatifs. Les plus gros composants ont été examinés selon leurs responsabilités, pas classés défectueux sur leur seul nombre de lignes : `SwipeCard.vue`, les catalogues admin, `MessageTimeline.vue`, `VisitWorkspace.vue`, `ProfileForm.vue` et les statistiques partenaires.

**Flux structurants :** `app.ts` → résolution des layouts → props Inertia → pages et composants de domaine. `MemberLayout` fournit le contexte temps réel aux pages, à la navigation et aux notifications ; les événements privés et personnels se rejoignent dans les mêmes chronologies. `GroupChat` réutilise `MessageTimeline` et `MessageComposer` pour événements et trajets. L’onboarding utilise les composants réels de découverte/match/composition avec un transport de démonstration. Les sorties combinent page de liste, panneau responsive, formulaires et retours vers les filtres. Les réglages push partagent des refs de module avec l’invitation du layout et l’installation de l’onboarding. Ces interactions expliquent les constats ; une lecture isolée de chaque template les manquerait.

| Domaine                           | Fichiers et interactions parcourus                                                                                                                 | Profondeur / résultat                                                                                                                                                                                                                           |
| --------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Démarrage, layouts, navigation    | `app.ts`, `layouts/resolvePageLayout.ts`, `MemberLayout.vue`, `AdminLayout.vue`, layouts auth/settings, navigation basse, liens retour, historique | Flux et cycle de vie approfondis. Les pages plein écran et les pages à défilement du document sont distinguées ; ne pas remplacer globalement `window.scrollY` par le scroll d’un conteneur                                                     |
| Authentification / réglages       | `pages/auth/`, `pages/settings/`, passkeys, 2FA, suppression, export, préférences, langue, installation et appareils                               | Formulaires/contrats et chemins d’erreur ciblés ; n° 23 et 25. Nettoyage des données 2FA présent dans `ManageTwoFactor` à la sortie                                                                                                             |
| Profil / découverte / onboarding  | Pages profil et membre, `ProfileForm`, stepper, avatar, intérêts, liens sociaux, `Discovery/Index`, `Passed`, `SwipeCard`, match, onboarding       | État du formulaire, files de décisions, verrouillage des interactions, aperçu réutilisé et usages examinés. Pas de justification établie pour découper mécaniquement la carte ou le formulaire                                                  |
| Messages privés                   | `Conversations/Index`, `Show`, composables de messages/frappe/refresh/realtime, composer, timeline, lignes et statuts                              | Approfondi, avec parcours entre liste, nouveau match, HTTP, Echo, pagination et formulaire ; n° 21–22                                                                                                                                           |
| Groupes événement / trajet        | `Conversations/Group`, `GroupChat`, données backend et composants de chronologie partagés                                                          | Approfondi ; n° 7 et 13. Rattrapage privé plus complet que le groupe ; pas de remplacement global du module de messagerie proposé                                                                                                               |
| Notifications                     | Centre, `NotificationItem`, filtrage, gestes, contexte temps réel, compteurs de navigation                                                         | Déduplication et convergence des événements examinées ; n° 24. Les contrôles masqués à l’état normal mais révélés au focus ne sont pas assimilés au `display:none` du n° 22                                                                     |
| Événements / visites              | Workspaces, panneaux adaptatifs, formulaires, inscriptions, participants et visibilité                                                             | Parcours props → formulaire → réponse partielle → panneau/retour étudiés. Confirmation explicite de partage déjà réutilisée. Vérifications visuelles et changement de largeur pendant une saisie restent ouverts                                |
| Trajets                           | Pages liste/détail, `TripListWorkspace`, `TripForm`, inscriptions, `TripPlaceInput`, cartes et chat                                                | Scripts/contrats et composants sensibles examinés ; annulation des recherches de lieux et contrôle des réponses périmées déjà présents. Ne pas ajouter un composable générique de recherche                                                     |
| Administration                    | Dashboard, membres/cartes, rôles/ban/suppression, signalements, catalogues, onboarding, thèmes                                                     | Scripts, branches mobile/desktop et formulaires parcourus ; revue ciblée des templates sensibles, pas exécution exhaustive de toutes les combinaisons de tableaux                                                                               |
| Partenaires                       | Édition/révision de fiche, aperçu image, annonces, modération et statistiques communes admin/partenaire                                            | Réutilisation existante vérifiée ; `PartnerProfileForm` révoque ses Object URLs au remplacement et au démontage. Matrice visuelle de statistiques à compléter                                                                                   |
| UI partagée / styles              | `components/ui/`, Dialog/Drawer, confirmations/panneaux, boutons/champs, CSS de thème et mouvement                                                 | Inventaire complet ; lecture approfondie des primitives réellement au centre des parcours. Pas de relecture exhaustive du code de Reka UI ni de certification d’accessibilité de ses 165 wrappers                                               |
| Utilitaires / types / traductions | `composables/`, `lib/`, `types/`, catalogues `lang/fr` et `lang/en`, analytics, consentement, navigateur externe, SW                               | Recherche transversale des listeners, timers, assertions, HTML et textes littéraux ; lecture ciblée des contrats et erreurs. Tests unitaires exécutés ; audit éditorial de chaque chaîne et scan de vulnérabilités des dépendances non réalisés |

### 21. Les indicateurs de frappe utilisent une photographie initiale des conversations

**Avéré par lecture et sonde isolée — P2.** `pages/Conversations/Index.vue:40–42` transmet le résultat d’un `filter`, une seule fois. `composables/useTypingSignals.ts:82–88` construit les canaux avec `conversations.map` au setup. Les rechargements partiels de `Index.vue:31–33,88–94,111–114` actualisent la liste affichée mais ne reconstruisent pas ces listeners. Un nouveau match peut donc créer une ligne active sans indicateur de frappe jusqu’au remontage complet de la page ; une conversation archivée reste dans le jeu initial.

**Preuve :** sonde exécutant les fonctions du fichier réel après transpilation, avec hooks et Echo doublés : après remplacement de la liste `[1]` par `[1,2]`, le seul canal demandé reste `conversation.1`. Ce n’est pas un test de transport réseau. Au démontage, `:128–132` retire les callbacks de frappe mais ne vide pas les timeouts de cinq secondes créés `:110–116`. Les canaux sont obtenus directement via `echo().private`, sans désabonnement local explicite ; retirer un callback n’équivaut pas à quitter le canal. La quantité d’abonnements réellement retenus dépend aussi des autres consommateurs Echo, donc aucun chiffre de fuite mémoire n’est avancé.

**Correction locale recommandée :** fournir une source réactive des conversations actives au composable existant, suivre les IDs ajoutés/retirés et retirer les callbacks et timers correspondants. Définir la propriété des abonnements avec les usages `useEcho` déjà présents avant de quitter un canal : un `leave` indiscriminé peut couper la conversation ouverte. Ne pas créer de gestionnaire de subscriptions général pour cette correction.

**Tests :** arrivée d’un match par props conservées, archivage/blocage, rafraîchissements identiques sans doublons, navigation liste → détail → liste, frappe en cours au démontage et absence de timer restant. **Impact fonctionnel limité à la frappe et au cycle de vie ; risque moyen ; effort 0,5–1 j.**

### 22. Sur mobile, la première réaction n’a pas de contrôle accessible au clavier

**Avéré — P2.** Dans `components/conversations/MessageTimeline.vue:454–477`, le bouton d’un message reçu avec zéro réaction utilise `hidden sm:inline-flex`. Sous 640 px, il est donc absent de l’ordre de tabulation et de l’arbre d’accessibilité. L’autre interaction est le double-clic/double-tap de l’`article` (`:434–446`), qui n’a ni focus clavier ni action clavier équivalente. L’utilisateur d’un clavier ou d’un lecteur d’écran ne dispose pas d’un bouton pour créer la première réaction. Une réaction déjà présente rend bien le bouton accessible ; le défaut concerne l’état initial.

**Usages vérifiés :** conversation privée, chats d’événement et de trajet utilisent cette timeline. `tests/Browser/ConversationTest.php:130–165` impose actuellement `display:none` puis envoie deux `TouchEvent` synthétiques : le test prouve le geste prévu, pas l’accès au clavier ou la compatibilité VoiceOver/TalkBack.

**Correction recommandée :** conserver l’apparence normale et le geste existants, mais fournir une action sémantique disponible aux technologies d’assistance et visible lorsqu’elle reçoit le focus, dans ce composant. Réutiliser le bouton et les libellés traduits existants ; ne pas rendre toute la bulle `role=button` en absorbant sa sémantique de message, ni introduire un menu générique sans besoin.

**Tests :** largeur 320/390 px, tabulation puis Entrée/Espace avant toute réaction, libellé et `aria-pressed`, désactivation pendant requête, non-régression double-tap, messages propres/système, chat de trajet en lecture seule. Contrôle réel VoiceOver/TalkBack encore nécessaire. **Risque moyen sur focus et gestes ; effort 0,5–1 j.** Aucune certification d’accessibilité globale n’est déduite de cette lecture.

### 23. Plusieurs erreurs push laissent le frontend dans un état incomplet

**Avéré, déclencheurs conditionnels reproduits avec doublures — P2.** Ces branches touchent une même responsabilité : terminer une opération push, exposer son résultat et permettre une reprise.

- `composables/useWebPush.ts:114–164` initialise l’abonnement avec `try/finally` sans `catch`. Une erreur réseau pendant la synchronisation rejette le hook, sans alimenter `error`, tout en plaçant `initialized` à `true`.
- `useWebPush.ts:206–224` place `busy` à `true`, puis écrit dans `localStorage` **avant** le `try`. Si l’écriture lève une exception, le `finally` n’est jamais atteint et les contrôles qui observent cette ref partagée restent occupés.
- `components/settings/WebPushDevices.vue:21–42` retire `removing` seulement après un `await fetch` réussi. Un rejet réseau laisse le bouton de cet appareil désactivé ; une réponse HTTP non réussie n’affiche aucune erreur.
- `components/pwa/WebPushInvitation.vue:48–55` traite tout `enable() === false` comme un refus à mémoriser et ferme l’invitation. Une panne de synchronisation entraîne donc le même résultat qu’un choix de refus, sans message d’échec dans cette invitation. Les accès de stockage du setup `:28–31` et de la mémorisation `:35–38` ne sont pas protégés non plus.

**Preuves exécutées :** une sonde charge le code TypeScript réel, en substituant les hooks Vue et APIs navigateur pour isoler les branches : `fetch` rejeté au montage → rejet `simulated offline`, `initialized=true`, `error=null` ; stockage qui lève dans `disableCurrent` → rejet et `busy=true` après l’appel. Les callbacks des composants et leurs boutons ont été confrontés au composable. Cela démontre l’absence de terminaison dans ces conditions, pas leur fréquence sur les appareils des membres. L’action « désactiver tout » de `pages/settings/Notifications.vue` a son propre `finally` et tente toujours la suppression serveur : ne pas la décrire comme totalement bloquée.

**Correction recommandée :** placer toutes les étapes risquées dans le périmètre `try/catch/finally`, remettre les états occupés à zéro et exposer les erreurs avec les traductions existantes. Traiter le stockage comme une persistance facultative, à l’image du brouillon de messagerie, tout en gardant le serveur source de vérité des appareils. Dans l’invitation, distinguer un refus utilisateur d’une panne et conserver une possibilité de réessayer ; ne pas déduire l’intention de refus d’un booléen d’échec technique. Réutiliser l’état/les composants existants plutôt qu’un nouveau client HTTP global.

**Tests :** refus de permission, annulation, rejet réseau et HTTP 419/422/500, absence/échec du service worker, stockage indisponible, reprise après erreur, suppression d’appareil en panne, connexion d’un autre compte sur le même navigateur. Monter ensemble invitation et réglages : leurs refs partagées et initialisations multiples doivent converger sans requêtes contradictoires. Les tests actuels d’invitation évaluent surtout la fonction de décision d’ouverture ; ils ne traversent pas ces opérations asynchrones. **Risque moyen**, particulièrement association appareil/compte et refus persistant ; **effort 1–2 j**. Ce constat n’établit pas de fuite de notification entre comptes.

### 24. Un changement d’accès de groupe provoque deux appels de rechargement de la liste

**Avéré statiquement — P3.** `composables/useMemberRealtimeNotifications.ts:116–119` appelle `router.reload({ only: ['groups'] })` lorsque l’URL est `/conversations`, puis `:148–149` appelle la même méthode si `page.component === 'Conversations/Index'`. Les deux conditions sont vraies pour cette page, y compris avec `?category=groups`. Il s’agit de deux visites demandées par un même événement ; le nombre de requêtes effectivement terminées dépend des annulations Inertia. Aucun double coût SQL garanti n’est affirmé.

**Correction recommandée :** rendre les branches exclusives en conservant un seul rechargement pour la liste. Garder distincts les comportements de révocation sur la page de groupe et de mise à jour du panneau événement. **Test :** injecter un unique événement `access` sur index, groupe ouvert, événement/panneau et page sans rapport ; vérifier la destination ou le seul reload attendu. `tests/Frontend/memberRealtimeGroup.test.js` couvre une notification de message, pas cette branche d’accès. **Risque faible ; effort inférieur à 0,5 j.**

### 25. Certains messages d’échec de sécurité contournent les traductions

**Avéré — P3.** `composables/useTwoFactorAuth.ts:42,55,83` ajoute trois textes anglais littéraux (`Failed to fetch…`). Ils sont affichés tels quels par `AlertError.vue` dans les composants 2FA. `components/PasskeyRegister.vue:40,93` expose directement `error` de la dépendance ; son implémentation installée `@laravel/passkeys` 0.4.0 transmet `errorInstance.message`, notamment en cas d’annulation. Le parcours de vérification utilise déjà `localizePasskeyError`, mais l’enregistrement ne le réutilise pas. Le nom proposé `PasskeyRegister.vue:34` emploie aussi `' sur '` en dur, même en anglais. Ces problèmes concernent des textes effectivement affichés, pas les `Error` internes ensuite remplacées par un message traduit.

**Correction recommandée :** représenter les échecs 2FA par des clés traduites et réutiliser `localizePasskeyError` pour l’annulation de l’enregistrement. Pour les autres erreurs de passkey, conserver les messages serveur localisés utiles et prévoir un libellé traduit pour les erreurs techniques de la bibliothèque, sans exposer leur détail brut. Traduire le format du nom proposé sans réécrire les noms déjà choisis. Ne pas ajouter une abstraction générale d’erreurs.

**Tests :** FR/EN sur échec QR/clé/codes, annulation d’enregistrement et erreur technique, nom par défaut et nom personnalisé préservé. `tests/Frontend/passkeyError.test.js` couvre l’annulation dans le helper et exige actuellement le maintien des autres fallbacks ; adapter ce contrat avec précision si la normalisation est étendue. **Risque faible ; effort 0,5–1 j.**

### Simplifications possibles, sans défaut métier établi

Ces pistes sont **P3 facultatives**, distinctes des constats numérotés et à réaliser seulement dans un changement qui les justifie :

- Le type local `Member` est dupliqué dans `pages/Admin/Members/Index.vue:22–45` et `components/admin/AdminMemberCard.vue:14–37`. Il décrit la même projection admin ; exporter un type depuis un fichier de types existant suffit. `types/member.ts` contient actuellement `PublicMember`, qui n’est pas cette projection : ne pas remplacer aveuglément l’un par l’autre. Aucun nouveau composant de table/cartes n’est nécessaire.
- Le code de réaction de `GroupChat.vue:128–145` et `:169–183` applique les mêmes champs, tandis qu’une fonction locale `updateReaction` existe déjà. Réutiliser cette fonction locale après préservation de son contrat réduit la duplication ; pas de nouveau composable de réactions.
- Les flags `deep` de fusion et les recherches linéaires dans les listes peuvent être mesurés avant simplification. Aucun profil ne démontre aujourd’hui un blocage lié à ces watchers, à `Intl` ou aux objets computed. Ne pas ajouter systématiquement cache, virtualisation ou memoization.
- La chaîne `AppLayout` / layouts sidebar du starter semble éloignée du résolveur actuel. Des références internes existent encore, ainsi que des usages réels d’utilitaires communs comme `useInitials`. Une suppression exige une recherche complète des imports, résolveurs dynamiques, vues et tests ; **aucune suppression de cette chaîne n’est recommandée comme acquise** dans ce rapport. Le seul code mort déjà démontré reste le n° 16.

### Typage, formulaires, performances et sécurité : résultats transversaux

Les props/emits et types des domaines sont généralement explicites ; les assertions JSON ne constituent pas une validation runtime. Pour les réponses internes contrôlées par Laravel, aucune bibliothèque de schéma supplémentaire n’est justifiée par ce seul constat. Les erreurs réseau et états partagés du n° 23 sont un défaut plus concret. Les assertions non nulles des avatars de configuration doivent être évaluées avec leurs gardes et l’invariant serveur ; leur simple présence n’a pas été classée comme bug.

Les `Form`/`useForm` existants gèrent déjà les erreurs serveur, `processing`, remises à zéro ciblées et confirmations. `MessageComposer` garde le brouillon après échec et n’efface pas un contenu modifié pendant l’envoi. La variante groupe remonte actuellement une erreur générique pour une réponse non réussie, là où le transport privé peut afficher `errors.content` ; harmonisation locale utile si des erreurs métier de champ supplémentaires sont exposées, sans gravité supplémentaire attribuée au seul écart. Ne pas déplacer les autorisations serveur vers des conditions Vue.

Les refresh de messages privés enlèvent leurs listeners/timers ; le viewport clavier et la récupération PWA nettoient leurs abonnements et leur sonde DOM. Les listeners de démarrage analytics/PWA ont une durée de vie applicative cohérente. La recherche de lieux annule les requêtes précédentes et ignore les retours périmés. Ces éléments ne doivent pas être remplacés par une nouvelle abstraction générique. Le n° 21 reste l’exception démontrée du parcours de frappe.

Les principaux coûts frontend plausibles concernent les historiques longs conservés et retriés, les volumes d’images/animations et les réponses de listes, mais aucun profil CPU, mémoire, taille de bundle ni mesure sur appareil faible n’a été réalisé. Le n° 24 est une simplification déterministe ; les optimisations de rendu restent à mesurer. Les coûts SQL n° 8–10 ne deviennent pas des problèmes de re-render par changement de vocabulaire.

Recherche ciblée de HTML brut, liens, stockage et scripts : les `v-html` de pagination proviennent des labels Laravel ; celui de la 2FA provient du SVG Fortify. Aucun chemin de contenu membre injecté dans ces emplacements n’a été établi. Les textes de messages restent interpolés par Vue. La politique du service worker réserve les navigations au réseau avec fallback hors ligne ; les cibles push externes sont filtrées par origine. Aucune XSS ni cache de pages privées démontré dans cette passe. Cela ne constitue pas un pentest, une preuve d’absence de vulnérabilité des dépendances ou une validation du déploiement.

### Contrôles exécutés et trous de couverture frontend

Contrôles relancés pendant cette passe, sans correction automatique :

| Contrôle                       | Résultat réel                                                                                  | Portée / limite                                                                                                                                                                |
| ------------------------------ | ---------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `bun run test:unit`            | **146 pass, 0 fail**, 31 fichiers, 232 assertions                                              | Helpers et quelques composables ; ne couvre pas tous les montages DOM ni les parcours Reverb                                                                                   |
| `bun run types:check`          | Échec : **9 TS2307**, imports Wayfinder absents localement                                     | Modules visites, signalements de départ et préférences découverte. Aucun autre diagnostic émis dans cette invocation ; pas de succès TypeScript annoncé                        |
| `bun run lint:check`           | Échec : **7 erreurs `import/order`**                                                           | Même ensemble de zones affectées par les imports générés locaux ; la résolution peut influencer l’ordre. Pas de `--fix`, pas de conclusion sur le résultat CI après génération |
| Sondes temporaires, hors dépôt | Liste de frappe figée, rejet du hook push, `busy` non réinitialisé et fusion trouée reproduits | Fonctions du code réel, hooks/APIs doublés ou helper pur. Pas un navigateur ni une connexion Reverb réelle                                                                     |
| Pest Browser / Playwright      | **Non exécutés dans cette passe**                                                              | Le garde MySQL isolé n’a pas pu établir sa connexion pendant l’audit ; aucun scénario navigateur n’est présenté comme réussi                                                   |
| Build / génération Wayfinder   | Non lancés                                                                                     | Maintien strict du rapport comme seule modification ; aucun fichier généré ou bundle écrit                                                                                     |

Les sorties courantes sont conservées hors dépôt dans `/tmp/dlp-ponytail-frontend-current-tests.log`, `-types.log` et `-lint.log` avec le même préfixe ; les sondes sont dans `/tmp/dlp-ponytail-frontend-probes.ts`. Ce sont des supports temporaires, pas des tests livrés ni une infrastructure requise pour appliquer les corrections.

**Couverture existante utile :** `ConversationTest` teste notamment envoi lent/échec, maintien du focus, pagination, clavier et gestes ; `TripChatTest` et `EventChatMembershipTest` couvrent des parcours de groupe et membres ; `NotificationSettingsTest`, `NotificationCenterTest`, `LocalizationTest`, `SettingsNavigationTest`, `DocumentScrollTest`, `AdminScrollTest`, `VisitTest`, `TripTest`, `PartnerTest` et `OnboardingTest` couvrent les grandes surfaces. Les tests unitaires de chronologie, gestes, filtre, CSRF, PWA, analytics et consentement apportent des contrats ciblés. La suite n’est donc pas dépourvue de tests frontend.

**Ce qui manque pour les défauts retenus :** reconnexion avec un arriéré supérieur à une page, réception en onglet masqué, mutation réactive des conversations écoutées, première réaction mobile au clavier, échecs réseau/stockage des hooks push, décompte des reloads sur changement d’accès, erreurs 2FA et enregistrement passkey dans les deux langues. Des tests par recherche de chaîne dans le source ou événements tactiles synthétiques ne valident pas à eux seuls le focus, le cycle de vie et les erreurs asynchrones réels. Ajouter des tests de comportement aux frontières concernées ; ne pas tester les détails internes de Reka UI.

### Plan frontend priorisé et inspections restantes

| Rang | Lot proposé                                 | Critère de sortie / protection de non-régression                                                                                                |
| ---- | ------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------- |
| 1    | Continuité et lecture de groupe, n° 7 et 13 | Tous les IDs manqués rattrapés, aucun lu en arrière-plan, aucun doublon ; événements et trajets, page/panneau, réactions et scroll conservés    |
| 2    | Résilience push, n° 23                      | Toujours sortir de l’état occupé, erreurs traduites et reprise possible, panne distincte d’un refus, bonne association appareil/compte          |
| 3    | Première réaction accessible, n° 22         | Même apparence normale et mêmes gestes ; action au clavier/lecteur d’écran, y compris à zéro réaction                                           |
| 4    | Cycle de vie de la frappe, n° 21            | Nouveaux IDs pris en compte, anciens retirés, timers nettoyés et autres consommateurs Echo préservés                                            |
| 5    | Reload unique et traductions, n° 24–25      | Un seul effet attendu par événement ; erreurs FR/EN compréhensibles sans exposer le détail technique                                            |
| 6    | Simplifications locales facultatives        | Seulement type admin commun et fonction locale de réaction si utiles au changement ; pas de découpage de gros composants sans bénéfice démontré |

Pour chaque lot : écrire d’abord le test rouge du comportement, corriger dans le module concerné, vérifier rendu/interactions/accessibilité, puis exécuter les contrôles pertinents avec les routes générées par le processus normal. Ce plan ne modifie pas le classement des P1 backend, qui restent prioritaires pour la confidentialité.

Inspections approfondies encore nécessaires, sans masquer les limites de cette passe :

1. Reproduction navigateur réelle de coupure/rattrapage et révocation avec serveur Reverb, plusieurs onglets et vraie suspension PWA ; comparer les curseurs affichés et persistés.
2. Audit visuel et assistif sur les états chargement/vide/erreur, FR/EN, mode sombre et thème saisonnier, 320 px, zoom 200 %, clavier seul et VoiceOver/TalkBack. Les styles reduced-motion et focus existent ; contrastes et lecteurs d’écran n’ont pas été validés exhaustivement.
3. Saisie en cours lors du franchissement du breakpoint 640 px : `useResponsiveModal` remplace les types Dialog/Drawer. La conservation du formulaire, du brouillon et du focus doit être testée ; aucun effacement n’est affirmé sans reproduction.
4. Volumes importants : milliers de messages, listes admin, statistiques et avatars ; profiler CPU, allocations, réseau et mémoire avant d’ajouter cache ou virtualisation. Examiner toutes les branches de templates admin/partenaire encore couvertes seulement par lecture ciblée.
5. Vérification complète des chaînes dynamiques et messages exceptionnels de toutes les surfaces, graphe d’usages du starter et primitives rarement utilisées, puis build reproductible avec Wayfinder à jour. La cartographie de tout le frontend ne remplace pas ces validations spécialisées.

Le livrable de cette passe est uniquement ce complément au rapport. Les corrections proposées préservent les composants et contrats utiles existants ; aucun refactoring applicatif n’a été effectué.

## Complément transversal final — frontières, révocations et exploitation

Cette passe confronte les protections HTTP, les données persistées, les abonnements déjà ouverts, les jobs et la configuration livrée. Même référence Git que les passes précédentes. Les quatre nouveaux constats ci-dessous ne remplacent pas les défauts déjà établis ; les extensions des n° 1 et 10 restent rattachées à leur cause existante. Aucun incident de production, test réseau Reverb réel ou comportement Coolify déployé n’est affirmé sur la seule lecture du dépôt.

Le chemin de contrôle est : session ou identité Google → middlewares → Policy et validation → Action et transaction MySQL → publication après commit → consommation de file → nouvelle sélection des destinataires → Reverb ou fournisseur push/e-mail → état Vue. Un contrôle à une étape ne prouve pas celui des étapes suivantes. Les constats prioritaires concernent précisément cette différence temporelle.

### 26. La révocation efface les sessions SQL alors que la production utilise Redis

**Suivi : correction implémentée le 23 octobre 2026, voir « Lot 1 — sessions indépendantes du driver » en fin de rapport.** Les éléments suivants décrivent le défaut initial.

**Avéré — P1.** La rotation de `remember_token` et l’effacement de la table `sessions` n’invalident pas les sessions Redis déjà authentifiées. Le middleware interdit bien l’accès pendant le bannissement ; le défaut démontré est la conservation d’une session dormante qui peut retrouver son accès après levée de la sanction.

**Preuves :** `compose.production.yaml:29–31` configure Redis pour les sessions avec chiffrement et cookie sécurisé. `app/Actions/SetMemberBan.php:30–32` change le statut et le jeton remember puis supprime les lignes SQL de sessions ; aucune session du cache n’est ciblée. `app/Actions/RequestAccountDeletion.php:44` utilise la même suppression SQL. `app/Http/Middleware/EnsureUserCanAccessSocialFeatures.php:21–26` déconnecte et invalide la session du navigateur qui fait une requête pendant le ban ; `EnsurePrivateAccountAccess.php:25–26` le réutilise. Un navigateur silencieux ne passe pas par ce contrôle. Le `SessionGuard` installé retrouve l’utilisateur par l’identifiant de la session ; le remember token intervient pour le cookie de reconnexion, pas pour invalider cet identifiant. Aucun mécanisme de génération de session ou de révocation globale des sessions cache n’a été trouvé dans `app/`, `bootstrap/`, `routes/` et `config/`.

**Scénario reproductible :** connecter un navigateur B, le laisser inactif ; un administrateur bannit puis débannit le compte avant expiration de la session ; B revient sur une route privée avec son ancien cookie. Le statut est redevenu actif et l’identifiant Redis reste authentifiant. Ce scénario ne suppose ni accès pendant le ban ni récupération d’un compte physiquement supprimé. La documentation `docs/security-privacy.md:200–204` promet pourtant la révocation des sessions. Le test `tests/Feature/Admin/MemberBanTest.php:15–32` insère et efface une ligne SQL ; il ne prouve pas la révocation du driver Redis utilisé en production.

**Vérification exécutée :** sonde hors dépôt utilisant les vrais `CacheBasedSessionHandler`, `Store` et `SessionGuard`, avec stockage array et fournisseur d’utilisateur doublé. Après changement du statut et rotation du jeton, sans requête intermédiaire, la session survit et le garde authentifie encore l’utilisateur redevenu actif. Cela valide le mécanisme du garde ; ni l’Action SQL ni un serveur Redis réel n’ont été exécutés par cette sonde.

**Correction recommandée :** rendre la révocation indépendante du driver, par exemple avec une génération d’authentification persistée sur le compte, enregistrée à la connexion et contrôlée sur les requêtes authentifiées. L’incrémenter dans la transaction de révocation et définir explicitement le traitement des sessions existantes au déploiement. Une autre solution exige un registre fiable des sessions Redis par utilisateur. Réutiliser les points d’entrée existants ; pas de nouvelle infrastructure de sessions générique sans besoin. Ne pas vider Redis, qui héberge aussi files, verrous et maintenance. Ajouter seulement `auth.session` ne suffit pas à une rotation du remember token : ce middleware repose sur le mot de passe.

**Tests requis :** deux clients indépendants avec le vrai driver Redis : connexion → ban avec B silencieux → levée → B doit se réauthentifier. Vérifier séparément requête pendant le ban, expiration, remember, mot de passe, Google et passkey, et absence d’invalidation d’autres utilisateurs. Pour la suppression, vérifier immédiatement les sessions restantes et les routes privées ; ne pas confondre compte inactif et session effectivement révoquée. **Risque élevé**, car tous les modes d’entrée doivent initialiser le mécanisme et les sessions déjà ouvertes doivent être traitées. **Effort : 1–2 j**, hors validation de déploiement.

### 27. Les journaux applicatifs de production sont locaux aux conteneurs

**Avéré dans la configuration versionnée — P2.** `compose.production.yaml:18–20` impose `LOG_CHANNEL=stack` et `LOG_STACK=single` aux services web, worker, scheduler et Reverb. `config/logging.php:55–65` écrit alors dans `storage/logs/laravel.log`, sans rotation du canal `single`. Le Compose ne déclare ni montage persistant de ce chemin ni collecte du fichier. Un collecteur ou montage ajouté dans Coolify pourrait compenser ce défaut ; sa présence réelle n’a pas été vérifiée.

**Scénario :** une exception Laravel produit une entrée dans le fichier du worker ; la sortie Docker ne la reçoit pas par ce canal. Le remplacement du conteneur détruit son fichier si aucune collecte externe n’existe. Pendant sa vie, le fichier peut croître sans rotation. Ce sont des risques de diagnostic et de stockage établis par la configuration, pas une preuve de disque plein ou de journaux actuellement perdus en production.

**Correction recommandée :** utiliser le canal `stderr` déjà défini dans `config/logging.php:105–113` pour la stack Docker, puis fixer rétention et collecte côté Docker/Coolify. Si le fichier est intentionnel, déclarer sa persistance, sa rotation et sa collecte. Conserver le niveau pertinent et les règles de confidentialité : aucune clé, contenu privé ou donnée personnelle supplémentaire pour faciliter ce test.

**Non-régression :** test de la configuration de production rendue et contrôle sur une stack isolée : émettre un identifiant technique synthétique depuis chaque service, constater sa collecte et sa disponibilité après remplacement, puis vérifier la rétention. Le scénario Docker n’a pas été exécuté pendant cet audit. **Risque moyen**, surtout doublons ou pertes pendant le basculement du collecteur ; **effort 0,5–1 j**. La documentation n° 17 doit décrire la solution retenue, sans créer un second chantier de logging.

### 28. La portée du second facteur local n’est pas définie pour Google

**Écart de contrat — P2.** Une identité Google déjà associée à un compte actif ouvre directement une session, sans challenge TOTP local, même si ce compte a activé la 2FA Fortify. Ce comportement peut constituer une décision d’authentification valable, mais sa portée doit être explicite ; l’authentification Google ne prouve pas à elle seule qu’un second facteur a été utilisé chez le fournisseur.

**Preuves :** `app/Http/Controllers/Auth/SocialAuthController.php:51–70` retrouve l’association par fournisseur et identifiant, refuse les comptes bannis/inactifs puis appelle `Auth::login` et régénère la session. Aucun contrôle de `hasEnabledTwoFactorAuthentication` ni passage par le challenge Fortify dans cette branche. `config/fortify.php:167–170` active pourtant la 2FA locale avec confirmation. `tests/Feature/Auth/SocialAuthenticationTest.php` valide le retour Google d’un compte associé, sans matrice TOTP activé/désactivé. Le refus Google d’un compte banni est également retourné avant tout challenge local, tandis que `tests/Feature/Auth/BannedAuthenticationTest.php` exige ce challenge avant révélation de la sanction pour le login par mot de passe. `docs/security-privacy.md:200–202` ne distingue pas ces méthodes dans sa promesse de vérification complète incluant le second facteur.

**Scénario à automatiser :** compte associé à Google et 2FA locale confirmée, déconnexion puis callback Google valide ; le chemin actuel ouvre la session directement. Répéter avec un compte banni : le message de sanction est rendu sans TOTP local. Cette trace de code n’est pas un essai OAuth réel ni une preuve d’usurpation d’identité ; la validité du callback est un prérequis. Les états d’un compte associé et avec secret 2FA sont à construire explicitement dans le test.

**Décision puis correction :** soit Google constitue une méthode d’authentification indépendante de la 2FA locale, et cette limite doit apparaître dans le contrat de sécurité et l’interface FR/EN ; soit le second facteur local protège toutes les connexions concernées, et le callback doit passer par le challenge existant avant de créer la session complète. Ne pas inventer une deuxième implémentation TOTP. Vérifier les passkeys séparément selon le contrat retenu, plutôt que d’assimiler automatiquement toutes les méthodes.

**Tests requis :** matrice mot de passe/Google/passkey × actif/banni × 2FA activée ou non ; code valide/invalide, session renouvelée, remember et callback avec state invalide. Conserver l’absence de rapprochement automatique par simple égalité d’e-mail. **Risque élevé**, blocage de connexion ou régression de confidentialité du statut ; **effort 0,5 j de décision puis 1–2 j**. Pas de changement métier avant arbitrage.

### 29. Le Compose ne transmet pas la clé dédiée à Resend Usage

**Avéré dans le déploiement fourni, effet conditionnel — P2.** La documentation propose une clé d’envoi restreinte et une clé Full access distincte pour consulter les quotas, mais le Compose ne transmet que la première. Ce défaut n’implique pas une panne lorsque la clé d’envoi dispose elle-même des droits Usage.

**Preuves :** `docs/operations.md:262–273` demande `RESEND_USAGE_API_KEY` et décrit le fallback. `config/services.php:21–23` lit cette variable puis revient à `RESEND_API_KEY`. `compose.production.yaml:52–53` transmet seulement `RESEND_API_KEY` ; aucune entrée `RESEND_USAGE_API_KEY` ni `env_file` dans ce Compose. Définir une variable dans l’environnement d’interpolation Compose ne la transmet pas automatiquement au conteneur lorsqu’elle n’est pas référencée. `app/Actions/CheckEmailRegistrationAvailability.php:19–34` utilise la clé sélectionnée pour `/usage`, puis renvoie false et le met en cache si la réponse n’est pas un succès (`:36–62`). Le refus fermé est intentionnel et protège le quota ; l’incohérence est dans le câblage de configuration.

**Reproduction sans service réel :** fournir au déploiement une clé d’envoi restreinte et une clé Usage dédiée, rendre la configuration Compose puis constater que seule la première est dans les variables du conteneur ; doubler la réponse `/usage` à 403 pour cette clé. L’inscription par e-mail devient indisponible malgré la clé dédiée présente chez l’opérateur. Avec une surcharge Coolify injectant explicitement la variable, le résultat peut différer : configuration active non inspectée. Aucune clé réelle ni requête Resend n’a été utilisée pour cet audit. Une sonde de l’Action réelle avec cache array et HTTP doublé a confirmé `false` sur la clé fictive recevant 403 et `true` sur la clé dédiée fictive recevant des quotas valides. Elle ne valide pas une injection Compose ou Coolify réelle.

**Correction recommandée :** transmettre explicitement la variable optionnelle dédiée aux services concernés et préserver le fallback documenté. Vérifier son chargement au démarrage/config cache, sans afficher sa valeur. Ne pas élargir les droits de la clé d’envoi pour masquer ce défaut ni ouvrir les inscriptions quand le contrôle de quota échoue.

**Tests requis :** étendre `tests/Feature/Infrastructure/ProductionDeploymentTest.php` avec les deux variables fictives distinctes et vérifier le Compose rendu ; tester la sélection de la clé dédiée, l’absence de clé dédiée, 403, quota atteint, timeout et expiration du cache. Une configuration statique seule ne valide pas les permissions réelles du fournisseur. **Risque faible ; effort 0,5–1 j.**

### Extensions vérifiées des constats existants

**N° 1 — Les événements client de frappe empruntent aussi le chemin non révoqué.** `routes/channels.php:13–16` applique la Policy `send` lors de l’autorisation initiale. Ensuite `IdentityAwarePrivateChannel::broadcast`, lignes 27–50, contrôle l’activité globale et les conditions du compte (`:54–74`), sans recontrôler la conversation. Le `ClientEvent` Reverb installé exige une souscription existante lorsque `accept_client_events_from=members` (`config/reverb.php:90`), puis relaie l’événement client. La Policy HTTP n’est pas rejouée. Les deux comptes restant actifs après un blocage, un abonnement déjà ouvert peut donc encore relayer `client-typing`. Le composable de frappe utilise ce signal ; ce n’est pas une preuve de création d’un message privé arbitraire par un whisper.

Une sonde a souscrit deux connexions doublées avec de vraies signatures à la classe de canal réelle, en doublant les lectures d’éligibilité globale : un événement de frappe a été transmis et le destinataire n’a pas été déconnecté. Elle ne crée pas de blocage SQL et n’ouvre pas de websocket réel ; elle démontre l’absence de filtre de conversation dans ce chemin. La correction n° 1 doit donc couvrir **émetteur et destinataire des événements client**, en plus des broadcasts du worker. Test réel requis : souscrire → bloquer → whisper ; aucune transmission après révocation, avec contrôles HTTP et droits d’historique conservés. Corriger seulement `MessageSent::broadcastWhen` laisserait ce chemin ouvert.

**N° 10 — Le contrôle Reverb ajoute du SQL par connexion.** Dans `IdentityAwarePrivateChannel::broadcast`, chaque destinataire appelle `active()`, donc une lecture d’éligibilité SQL ; un événement client ajoute celle de l’émetteur. Plusieurs onglets du même compte répètent ces lectures. En bêta, les rôles sont aussi chargés. `MessageSent` publie vers la conversation et les canaux personnels, ce qui peut répéter le travail pour un même compte. La croissance est établie structurellement, mais aucun seuil de saturation n’est mesuré. La limitation des événements clients est désactivée par défaut (`config/reverb.php:91–95`).

Compléter les tests de budget SQL avec plusieurs connexions et canaux et mesurer la charge de frappe. Évaluer une déduplication à durée strictement bornée à une diffusion et les limites Reverb existantes avant de créer un cache. Un TTL de permission qui réautoriserait un compte révoqué serait une régression des n° 1–2. Aucun nouvel incident de performance ni déni de service démontré n’est ajouté à la liste.

### Scénarios critiques traversés et protections à conserver

« Traversé » signifie lecture du producteur, du contrôle d’accès, du stockage, du consommateur et de leurs tests lorsqu’ils existent. Seules les exécutions explicitement indiquées plus bas ont été réalisées ; cette matrice ne présente pas des parcours navigateur réussis.

| Frontière / scénario limite                                    | Résultat établi par le code                                                                                                                                                        | Non-régression de bout en bout nécessaire                                                                                                                |
| -------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Connexion, changement de statut, session dormante              | Middlewares de ban présents ; révocation cache incomplète n° 26 ; contrat Google n° 28                                                                                             | Deux navigateurs, ban/levée, TTL et toutes méthodes de connexion ; routes de réglages incluses                                                           |
| Message privé → blocage → worker/Reverb                        | `SendMessage` verrouille les utilisateurs dans l’ordre et la conversation, puis autorise ; événements après commit. Nouvelle requête refusée, livraison différée et frappe n° 1    | Worker suspendu puis repris après blocage ; canaux déjà ouverts, réaction et reçu de lecture dans les deux sens                                          |
| Événement/trajet → acceptation → retrait/perte de rôle         | Policy et sélection de destinataires existent ; éligibilité événement n° 2 ; course groupe encore n° 14                                                                            | Message concurrent au retrait, annulation, changement de rôle/adresse ; organisateur et rôles cumulés ; deux connexions SQL                              |
| Profil masqué, pair bloqué, cible devenue inéligible           | Projections publiques et Policies limitent l’accès ; éligibilité découverte n° 3, contrat historique n° 11 et export n° 12 distincts                                               | Comparer page, drawer, découverte, swipe, export et signalement ; aucune unification aveugle des droits                                                  |
| Notification différée → lecture/désactivation/perte d’accès    | Notifications privées et de groupe recontrôlent des droits ; notification de groupe stocke l’ID du message et recharge le contexte ; marquage groupe sérialisé avec son canal DB   | Enfiler puis lire, désactiver, bloquer, bannir ou retirer l’inscription avant consommation ; vérifier base, compteur, push et lien cible                 |
| Endpoint push → changement de compte/appareil → reprise worker | Endpoint HTTPS sur hôtes permis, données d’abonnement protégées, propriétaire vérifié ; accès et préférences revérifiés ; réservation abandonnée n° 19 et erreurs Vue n° 23        | Deux comptes sur même appareil, révocation/réutilisation permise, erreurs réseau, worker interrompu, TTL ; aucun secret dans logs                        |
| SQL → fournisseur e-mail/push → accusé de réception            | Après-commit utilisé sur plusieurs flux, mais ne rend pas atomique un effet réseau ; récapitulatif n° 6 et reprise Redis n° 5                                                      | Simuler acceptation externe puis arrêt avant ACK/commit ; définir doublon acceptable, reprise et déduplication ; pas de promesse « exactement une fois » |
| Abonnement Reverb → suspension PWA → reconnexion               | Identité signée, compte actif et conditions courantes revérifiés ; rattrapage groupe n° 13, lecture masquée n° 7, liste de frappe n° 21                                            | Plus d’une page manquée, plusieurs onglets, arrière-plan réel, reprise du scroll et des curseurs ; ne pas seulement émettre un faux `connected`          |
| Suppression différée → job de purge devenu ancien              | `RequestAccountDeletion` prend un verrou utilisateur ; le job de purge revérifie statut et date de demande ; une ancienne tâche n’est pas une autorisation permanente de supprimer | Rejeu, nouvelle demande, bannissement avant purge et données conservées ; aucune migration ni purge réelle dans cet audit                                |
| Inscription → quota Resend → cache → formulaire                | Vérification limitée à 2 s de connexion/5 s au total, cache jusqu’à 60 s et refus fermé ; clé de cache dérivée par hash ; câblage n° 29                                            | Limites jour/mois, reset, erreur fournisseur, cache expiré et clé dédiée ; ne pas confondre panne de quota et invalidité du formulaire                   |
| Release → maintenance Redis → drain → migration → reprise      | Outillage de livraison contrôlant digest, identité Redis, sauvegarde attestée et répétition bêta ; pas de migration automatique aux entrypoints                                    | Échec migration, backup invalide, image incohérente, Redis divergent et retour maintenance ; validation réelle de restauration toujours nécessaire       |

Les lectures et suppressions de notifications passent par la relation du propriétaire. Les canaux personnels vérifient l’ID du compte ; les signatures Reverb lient aussi les données d’identité. `allowed_origins=['*']` n’autorise donc pas à lui seul un tiers à rejoindre un canal privé. Aucun contournement de signature, fuite par devinette d’un ID ou SSRF push établi n’a été trouvé dans ces chemins. Cela ne remplace pas des tests négatifs réseau et fournisseur.

Les notifications push contenant un aperçu et les signalements donnant un accès administratif à l’échange relèvent de contrats existants : ne pas les présenter comme une nouvelle fuite sans distinguer leurs destinataires autorisés. L’accès à un historique après blocage reste une décision n° 11 ; il ne justifie pas de transmettre de nouvelles frappes ou livraisons par défaut. L’export n° 12 doit respecter son propre contrat.

### Transactions, cache et responsabilités : conclusion transversale

Le dépôt possède des Actions transactionnelles, des verrous ordonnés sur plusieurs chemins, des contraintes d’unicité pour les relations concernées et des gardes de relecture dans les jobs. Il ne faut pas remplacer ces protections par une grande abstraction commune « accès membre ». Visibilité d’un profil, accès à un historique, capacité d’envoyer et droit à recevoir maintenant sont des questions différentes. Les critères réellement partagés peuvent être réutilisés localement dans les Policies/scopes existants.

Les tâches planifiées utilisent des protections de chevauchement et, selon le traitement, d’exécution sur un seul serveur. Ces verrous ne garantissent ni une reprise après indisponibilité Redis ni un rattrapage métier de toute exécution manquée. Les défauts concrets retenus restent n° 5, 6 et 19 ; les autres garanties demandent des tests d’arrêt/rejeu ciblés. Un simple timeout HTTP suivi d’un nouvel envoi de message peut produire deux demandes légitimes : aucune obligation d’idempotence par clé client n’étant établie ici, ce cas n’est pas ajouté comme bug avéré. Il faut fixer le contrat avant d’ajouter une table ou un mécanisme générique.

La présence a un TTL et les permissions critiques relisent la base ; ajouter des caches globaux d’autorisations pour réduire le SQL exposerait de nouveau les n° 1–3. Mesurer d’abord les n° 8–10. Redis porte plusieurs responsabilités — sessions, cache, verrous, file, maintenance — qui rendent un `flush` opérationnel dangereux. La disponibilité, la persistance AOF, les politiques d’éviction, les limites mémoire, l’authentification et les sauvegardes du service réellement déployé restent à contrôler séparément.

Les doublons utiles à traiter sont déjà identifiés : critères d’éligibilité divergents, calculs SQL répétés, double reload n° 24 et type admin local dupliqué. Les chats privés, d’événement et de trajet ont des différences de participants, d’accès et de rétention qui empêchent de recommander leur fusion en un service universel. Seul le helper n° 16 possède une absence d’usage applicatif établie ; aucune suppression supplémentaire ne découle de cet audit.

### Docker et Coolify : ce qui est prouvé et ce qui reste externe

La configuration versionnée sépare web, worker, scheduler et Reverb, exige une image publiée, désactive le debug, utilise des cookies sécurisés et des sessions chiffrées. Le Dockerfile épingle PHP 8.4.27 et Bun 1.3.14 ; les secrets d’environnement ne sont pas destinés au contexte de build. L’outillage de release vérifie effectivement la forme digest ; le message de validation d’une variable Compose n’est pas, à lui seul, cette vérification. Les entrypoints attendent la disponibilité du schéma pour les processus concernés et n’exécutent pas les migrations automatiquement.

Le healthcheck web `/up` n’est pas une preuve de disponibilité d’un vrai parcours : le middleware de readiness peut refuser les routes applicatives si les migrations attendues manquent. Les checks de processus worker/scheduler ne prouvent pas qu’une tâche arrive à son terme. La procédure doit vérifier une route applicative, le schéma attendu, une tâche synthétique et le runtime exact après déploiement. Les constats n° 27 et 29 concernent la configuration livrée ; n° 17, sa documentation.

Non vérifiés sur l’instance réelle : règles proxy/TLS et accessibilité directe au backend, injection effective des variables par Coolify, ACL et chiffrement des services externes, bucket S3 et URLs d’images, rétention des logs, politiques Redis, sauvegarde/restauration MySQL, identité exacte des images en cours, renouvellement des certificats et isolation effective bêta/production. Le `trustProxies('*')` nécessite une frontière réseau maîtrisée ; sans preuve d’exposition directe, il n’est pas classé ici comme contournement démontré. Aucun scan CVE ni examen des images exécutées n’a été réalisé.

### Vérifications exécutées dans la passe finale

| Vérification                                                       | Résultat                                                                          | Portée exacte                                                                                                             |
| ------------------------------------------------------------------ | --------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------- |
| Scénarios existants `tests/Support/operations/release_scenario.py` | **29 scénarios réussis**                                                          | Scénarios appelés par `ProductionReleaseTest`, fonctions d’exploitation exercées avec I/O Docker/SSH/MySQL/Redis simulées |
| Scénarios existants `tests/Support/operations/refresh_scenario.py` | **19 scénarios réussis**                                                          | Scénarios de `BetaRefreshTest`, services externes simulés ; pas un rafraîchissement bêta réel                             |
| Sonde session/garde dans `/tmp/dlp-ponytail-session-probe.php`     | Session cache conservée ; utilisateur authentifié après retour au statut actif    | Classes Laravel réelles, backend array et provider doublé ; aucune connexion Redis ni Action SQL                          |
| Sonde canal dans `/tmp/dlp-ponytail-whisper-probe.php`             | **1 événement de frappe transmis**, destinataire non déconnecté                   | Classe de canal et signatures réelles, connexions et éligibilité doublées ; pas de blocage SQL ni websocket réseau        |
| Sonde Resend dans `/tmp/dlp-ponytail-resend-probe.php`             | Inscription indisponible sur 403, disponible avec la clé dédiée et quotas valides | Action réelle, cache array, clés synthétiques et HTTP doublé ; aucun appel externe ni conteneur                           |
| `php tests/Support/verify-test-database.php`                       | **Échec**, `SQLSTATE[HY000] [2002] Operation not permitted`                       | Impossible d’établir la connexion MySQL isolée ; aucun contournement du garde, migration ou seed                          |
| Suites Pest Feature / Browser et concurrence réelle                | **Non exécutées dans cette passe**                                                | Le blocage MySQL interdit de déclarer les scénarios intégrés réussis                                                      |
| Docker build, restauration, fournisseurs et Coolify                | Non exécutés                                                                      | Aucune action de production ; les scénarios simulés ne prouvent ni sauvegarde restaurable ni santé courante               |

Les contrôles frontend précédents, au même commit, restent consignés dans leur section : 146 tests unitaires réussis ; échecs TypeScript et lint liés aux imports locaux signalés, sans génération ni correction. Ils ne constituent pas une validation des nouveaux scénarios d’authentification. Les supports Python ont été exécutés sans écrire de bytecode dans le dépôt. Les sondes temporaires ne sont pas des tests de non-régression livrés.

### Liste finale dédupliquée des corrections prioritaires

Chaque constat figure une seule fois dans les lots ci-dessous. Les plans précédents sont des étapes d’audit historiques ; celui-ci fixe l’ordre final. Les efforts détaillés restent attachés à chaque constat et ne sont pas additionnables sans tenir compte des tests communs.

| Ordre | Priorité / constats           | Correction ou décision concrète                                                                                                                                 | Critère de sortie                                                                                                                                                           |
| ----- | ----------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1     | **P1 — 1, 2, 26**             | Fermer les révocations : livraison privée et whispers, éligibilité événement, sessions Redis                                                                    | Souscriptions et sessions antérieures à la révocation testées réellement ; aucune autorisation perdue ne survit ; pas de suppression accidentelle des historiques autorisés |
| 2     | **P2 — 3, 4, 11, 12, 20, 28** | Valider une matrice de contrats par rôle/méthode/canal, puis petites corrections séparées : cibles, réglages, historique, export, push partenaire et 2FA Google | Décisions documentées avant changement de règle ; tests positifs/négatifs couvrant chaque entrée et destinataire                                                            |
| 3     | **P2 — 5, 6, 19**             | Marge timeout/réservation, effet e-mail hors verrou selon garantie retenue, vraie reprise de lease push                                                         | Arrêt après acceptation réseau, retry précoce/tardif et double consommation ; doublons/pertes traités selon un contrat explicite                                            |
| 4     | **P2 — 27, 29**               | Rendre les logs collectables et transmettre la clé Resend Usage dédiée                                                                                          | Compose rendu testé, message technique non privé collecté après remplacement, quota testé avec clés fictives distinctes ; vérification runtime séparée                      |
| 5     | **P2 — 7, 13, 21, 23**        | Continuité Vue/Reverb : visibilité, pagination de rattrapage, abonnements réactifs, terminaison des erreurs push                                                | Parcours plusieurs onglets et plus d’une page manquée, aucun faux lu, timers nettoyés et contrôles utilisables après échec                                                  |
| 6     | **P2 — 18, 22**               | Validation datetime locale et contrôle de première réaction accessible                                                                                          | Dates invalides/DST sans 500 ni décalage silencieux ; clavier mobile/lecteur d’écran, rendu et gestes conservés                                                             |
| 7     | **Investigation P2 — 14, 15** | Reproduire avec barrières et deux connexions les courses groupe et maximum lu privé                                                                             | Invariant et ordre des verrous établis ; ne corriger qu’après reproduction, ou classer la suspicion avec preuve contraire                                                   |
| 8     | **P2 — 8, 9, 10**             | Borner lecture découverte/calendrier/groupes/statistiques et mesurer le SQL Reverb                                                                              | Résultats et droits identiques ; requêtes, lignes hydratées et mémoire mesurées avant/après ; pas de cache de permission périmée                                            |
| 9     | **P3 — 16, 17, 24, 25**       | Helper réellement inutilisé, procédure unique, reload unique et erreurs traduites                                                                               | Références revérifiées, une seule procédure opérateur, un seul effet Inertia, erreurs FR/EN testées                                                                         |

La première étape de chaque correction reste le test rouge demandé par `AGENTS.md`, puis la correction minimale, puis les contrôles pertinents PHP/frontend et l’image si sa configuration change. Les arbitrages du lot 2 peuvent être préparés pendant les corrections techniques indépendantes. Aucun découpage générique supplémentaire ou grand renommage n’est nécessaire pour entreprendre ces lots.

### Tableau final de couverture et zones restant à auditer

Niveaux : **approfondi** = chaîne sensible et usages croisés lus ; **ciblé** = branches sélectionnées ; **non vérifié en runtime** = état opérationnel externe inconnu. Même une couverture source approfondie ne vaut pas une exécution intégrée.

| Domaine                                                          | Couverture source / interactions examinées                                                       | Preuve exécutable disponible                                       | Reste à inspecter ou exécuter                                                                                                         |
| ---------------------------------------------------------------- | ------------------------------------------------------------------------------------------------ | ------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------- |
| Authentification, statuts, données personnelles                  | Approfondi : Fortify, Google, middleware, sessions, ban, suppression, réglages et export         | Sonde garde/cache ; tests existants lus                            | Redis réel, deux clients, récupération/2FA/passkeys et migrations de sessions existantes ; n° 4, 12, 26, 28                           |
| Messages privés, blocage, signalements                           | Approfondi : routes, Policy, Action, verrous, événement, notification, canal, Vue, accès admin   | Sonde canal et helpers frontend ; tests métier lus                 | Reverb réel après blocage, latence worker, signalement selon rôle ; n° 1, 11, 15                                                      |
| Événements, inscriptions et groupes                              | Approfondi : validation, capacité, transitions, audience, DTO, livraison et lecture              | Sondes datetime et fusion des passes précédentes                   | Concurrence MySQL à deux connexions, retrait de rôle/adresse, rattrapage navigateur ; n° 2, 7, 13, 14, 18                             |
| Trajets et chat associé                                          | Approfondi sur droits/participation/retrait/rétention ; branches de rendu ciblées                | Tests existants lus, helpers de groupe testés                      | Courses envoi/retrait, annulation, purge et perte de réseau réelles ; variantes UX complètes                                          |
| Profils, découverte, matches, visites                            | Approfondi sur audience, projections et calendrier ; scoring lu structurellement                 | Tests existants lus, helpers frontend                              | Jeux volumineux, `EXPLAIN`, équivalence classement et confidentialité calendrier ; n° 3, 8, 9                                         |
| Notifications DB, push et e-mail                                 | Approfondi : destinataires, propriété, préférences, endpoints, queue, lease et Vue               | Sondes push frontend ; tests existants lus                         | Arrêts/reprises worker, vrai push multi-compte, permissions Usage et délais e-mail ; n° 5, 6, 19, 23, 29                              |
| Partenaires, annonces, administration                            | Approfondi sur autorisations, statistiques et livraison ; toutes branches UI non parcourues      | Tests existants lus                                                | Contrat push partenaire, budgets SQL, rôles cumulés ; n° 4, 10, 20                                                                    |
| Reverb et cycle de vie Vue                                       | Approfondi : signatures, autorisation initiale, diffusion, client events, listeners, reconnexion | Sonde canal et 146 tests unitaires frontend de la passe précédente | Sockets réseau, suspension PWA réelle, trafic/SQL par connexion et limites ; n° 1, 2, 10, 13, 21, 24                                  |
| Transactions, contraintes et idempotence                         | Approfondi sur chemins sensibles ; autres mutations inspectées de façon ciblée                   | Scénarios opérationnels simulés ; aucune concurrence SQL exécutée  | Interblocages, retry après réseau, ordre des verrous et effets externes ; n° 5, 6, 14, 15, 19                                         |
| Cache Redis, files et scheduler                                  | Approfondi sur configuration/consommateurs critiques ; runtime non vérifié                       | Sonde session cache et scénarios d’exploitation simulés            | Redis réel, arrêt prolongé, rattrapage métier, AOF/éviction/ACL/locks et alertes de file                                              |
| Docker, release, bêta et documentation                           | Approfondi sur Dockerfile, Compose, entrypoints, workflow et scripts de livraison                | **48 scénarios opérationnels simulés réussis**                     | Build image, restauration isolée, digest/runtime et procédure opérateur complète ; n° 17, 27, 29                                      |
| Coolify, MySQL/Redis/S3 et services externes actifs              | Ciblé sur le contrat de configuration ; **non vérifié en runtime**                               | Aucune vérification distante                                       | TLS/proxy, variables réellement injectées, ACL, sauvegardes, rétention, santé applicative, isolement bêta et permissions fournisseurs |
| Accessibilité, responsive, traductions et performance navigateur | Ciblé sur interactions et problèmes n° 21–25 ; cartographie frontend antérieure                  | Tests unitaires ; lint/types en échec consignés                    | VoiceOver/TalkBack, clavier, contrastes, vrais appareils, profils CPU/mémoire et bundle reproductible                                 |
| Tests et sécurité des dépendances                                | Suites pertinentes lues ; contrôles disponibles distingués des absents                           | Résultats ci-dessus, garde MySQL refusé                            | Suites Pest/Browser complètes en environnement isolé, build Wayfinder normal, audit des versions/CVE et analyse d’image               |

**État final du livrable :** 29 constats distincts, dont deux suspicions à reproduire et quatre décisions de contrat ; trois P1 établis par leurs chemins de code. Le rapport est la seule modification autorisée et réalisée. Aucun correctif, migration, envoi externe ou déploiement n’a été effectué.

## Suivi des correctifs — 23 octobre 2026

Les sections précédentes conservent les constats et résultats de l'audit initial.
L'implémentation progressive est désormais autorisée ; son état courant est suivi
ici, sans transformer les suspicions ou décisions produit en corrections implicites.

### Première étape : éligibilité du chat d'événement (n° 2, P1)

**Correction implémentée.** `app/Policies/GroupChatPolicy.php` exige désormais
le rôle `user` et une adresse vérifiée pour le lecteur et l'organisateur, en plus
des contrôles existants d'activité et d'inscription. La branche trajet conserve
sa délégation à `TripPolicy`. Le masquage du profil n'intervient pas dans ce droit.

Cette Policy est déjà utilisée par les requêtes HTTP, les événements de messages
et réactions, et les notifications persistantes/Push différées : aucune nouvelle
couche d'autorisation ni duplication dans ces consommateurs n'a été ajoutée.
Le changement ne retire pas les inscriptions historiques ; un compte redevenu
éligible conserve les règles d'accès liées à son inscription actuelle.

**Preuve de non-régression :** `tests/Feature/EventChatEligibilityTest.php` ajoute
huit scénarios Pest sur MySQL isolé. Les six cas négatifs échouaient avant la
correction parce que les droits ou livraisons restaient autorisés ; les deux cas
positifs préservent rôles cumulés et profils masqués. Les contrôles couvrent les
objets sérialisés avant révocation, les destinataires et auteurs, les canaux de
messages/réactions, les notifications et le refus HTTP. Avec les tests existants
d'autorisation, de diffusion et de notification des événements/trajets :
**41 tests réussis, 142 assertions**.

**Validation complète après correction :** `composer ci:check` réussit : ESLint,
Prettier, vue-tsc, Pint, PHPStan (zéro erreur), génération Wayfinder, 146 tests
unitaires frontend, build Vite/PWA et suite Pest incluant les parcours Chromium.
Pest dénombre 1 875 tests : **1 869 réussis, 6 ignorés, 33 457 assertions** en
640 secondes. Les six tests ignorés ne sont pas comptés comme validés ; cette
sortie agrégée ne fournit pas leur motif individuel. Le garde de base de test
confirme `mysql/dlp_friends_test`. Les premiers contrôles lint/types de l'audit
initial échouaient avant génération des imports Wayfinder ; ils passent après
la génération normale, sans correction du code frontend. Aucune construction
d'image Docker supplémentaire n'est nécessaire pour ce changement de Policy.

**Limites :** aucun websocket réseau ni retrait simultané sur deux connexions SQL
n'est simulé par ces tests ; les risques propres au transport Reverb (n° 1) et à
la concurrence (n° 14) restent ouverts. Les autres constats ne sont pas clos par
cette étape. Prochains correctifs de sécurité : diffusion privée et whispers
(n° 1), puis révocation des sessions Redis (n° 26).

### Deuxième étape : révocation de la diffusion privée (n° 1, P1)

**Correction implémentée.** `MessageSent`, `MessageReactionUpdated` et
`MessagesRead` rechargent la conversation et les participants, puis réutilisent
`ConversationPolicy::send`. Ce contrôle intervient également dans
`broadcastOn()`, appelé lors de la consommation du job sérialisé : un blocage
ou archivage survenu après sa mise en file arrête la publication.

`IdentityAwarePrivateChannel` recontrôle cette même Policy à l'abonnement et
avant la diffusion aux connexions déjà ouvertes, pour l'émetteur des whispers
et chaque destinataire. Les canaux personnels contrôlent aussi la conversation
du payload `message.sent` : cela ferme la fenêtre où le worker a publié avant
le blocage mais Reverb relaie après celui-ci. Un identifiant absent ou invalide
dans ce payload refuse la livraison. Les autres événements personnels gardent
leur contrôle d'activité existant. Les profils masqués restent autorisés.

**Preuves de non-régression :** onze scénarios dans
`tests/Feature/PrivateChatRevocationTest.php` couvrent les jobs réellement
sérialisés, le blocage dans les deux sens, le blocage sans archivage,
l'archivage seul, les whispers via le handler Reverb, la réutilisation des
signatures et les messages relayés sur les canaux personnels. Dix scénarios
négatifs échouaient avant le correctif ; le scénario positif restait passant.
La fuite personnelle a aussi été reproduite avant son correctif. Les fixtures
de `BannedRealtimeAccessTest` utilisent désormais de vraies conversations.
La suite ciblée de cinq fichiers réussit : **37 tests, 203 assertions**.

Une sonde temporaire indépendante lance un vrai serveur Reverb local avec
deux WebSockets Bun et des signatures réelles, sur la base MySQL isolée :
un whisper est reçu avant blocage, aucun whisper ni message personnel n'est
reçu après blocage, et les deux connexions sont fermées. Les fixtures et le
serveur de la sonde sont nettoyés ; aucun service distant n'est contacté.

**Validation :** Pint et PHPStan réussissent (zéro erreur). Les contrôles
frontend, les 146 tests unitaires et le build Vite/PWA de `composer ci:check`
réussissent. La suite Pest compte **1 880 réussites, 6 ignorés et 33 536
assertions**, mais la commande globale échoue sur **deux avertissements**
dont le résumé JSON masque le détail. Une seconde exécution Pest en sortie
native confirme les mêmes réussites et le code d'échec. Un journal d'événements
sur la seule découverte des tests identifie la cause :
`vendor/pestphp/pest-plugin-browser/src/Support/Screenshot.php:83` appelle
`rmdir` sur les sous-dossiers absents `Sliders` et `ImageDiffView` lorsqu'un
dossier de captures issu de la passe précédente existe. PHPUnit comptabilise
ces avertissements PHP malgré `@rmdir`. Le nettoyage temporaire est terminé ;
aucun code vendor ni règle `failOnWarning` n'est modifié. Une nouvelle
exécution de `composer ci:check` depuis cet état propre **réussit avec un
code de sortie 0** : **1 880 tests réussis, 6 ignorés, 33 536 assertions**, en
614 secondes pour Pest. Tous les contrôles PHP et frontend, le build et les
parcours Chromium activés réussissent. Les avertissements des premières
passes sont donc distingués d'un échec fonctionnel et conservés ici pour
les futures exécutions depuis un dossier de captures déjà présent.
Les six tests ignorés sont identifiés : une intégration Garage requiert
`GARAGE_INTEGRATION=1` et un bucket préparé ; une intégration de déploiement
requiert `DLP_RUNTIME_TEST_IMAGE` ; quatre parcours de conversation requièrent
`REALTIME_BROWSER_TESTS=true` et un serveur Reverb configuré. La sonde réseau
ci-dessus ne vaut pas exécution de ces quatre parcours navigateur.

**Risques et limites :** les contrôles frais ajoutent des lectures SQL par
connexion ; le budget à charge réelle reste à mesurer (n° 10). Une connexion
révoquée doit se reconnecter pour ses autres canaux. La distribution Redis à
plusieurs nœuds et les courses SQL à deux connexions ne sont pas validées par
la sonde réseau locale. Le contrat d'accès à l'historique (n° 11) reste ouvert.

**Avancement :** n° 1 et 2 implémentés ; 27 constats restent à traiter,
vérifier ou arbitrer. Les neuf lots ne sont pas encore terminés : le premier
nécessite toujours la révocation des sessions Redis (n° 26), puis huit lots
restent à aborder.

### Lot 1 — sessions indépendantes du driver (23 octobre 2026)

**Constat n° 26 corrigé dans la branche.** La migration ajoute
`users.session_version`, entier interne exclu de la sérialisation du modèle.
`SetMemberBan` et `RequestAccountDeletion` incrémentent cette version dans
leur transaction, sous le verrou utilisateur déjà existant. La demande de
suppression renouvelle également le remember token, y compris lorsqu’elle
est appelée directement sans contrôleur de déconnexion. Les suppressions SQL
existantes sont conservées ; aucun scan, registre supplémentaire ou vidage
Redis n’est ajouté.

L’événement Laravel `Login` enregistre la version de l’utilisateur authentifié
dans la session. Ce point commun couvre Fortify, le double facteur terminé,
Google, les passkeys et la reconnexion remember. Le contrôleur passkey installé
appelle bien `$guard->login(...)` ; une vraie cérémonie WebAuthn n’a pas été
ajoutée aux validations de ce correctif. `EnsureSessionIsCurrent` compare la
version à un compte fraîchement relu avant les contrôles privés, sur les routes
web et la route de souscription Reverb utilisant ce groupe. Une session périmée
est invalidée et exige une nouvelle authentification ; le message 403 existant
reste utilisé pendant un bannissement. Cette déconnexion utilise
`logoutCurrentDevice` pour ne pas invalider les cookies remember des connexions
récentes. Les sockets Reverb déjà ouverts restent couverts par les corrections
n° 1 et 2 et leurs contrôles propres ; le middleware HTTP ne les remplace pas.

**Déploiement :** exécuter explicitement la migration avant de servir le nouveau
code. Les comptes et sessions préexistants appartiennent à la version zéro.
Ils restent utilisables jusqu’à une révocation ; une session sans version ne
peut jamais adopter automatiquement la nouvelle génération. Les clés de
sessions périmées expirent normalement. Aucun entrypoint Docker n’a changé.

**Preuves de non-régression :**

- Cycle rouge confirmé avant correction : deux scénarios échouent pour les
  raisons attendues (accès 200 après ban/levée ; session encore authentifiée
  après demande de suppression).
- `tests/Feature/Auth/SessionRevocationTest.php` : huit scénarios, **48 assertions**,
  réussis avec le vrai stockage fichier puis avec **Redis 7.4.10** dans un
  conteneur éphémère dédié, supprimé après validation. Les clients possèdent
  des cookies indépendants et reconstruisent le guard entre requêtes.
- Couverture : deux navigateurs silencieux et compte tiers inchangé, nouvelle
  connexion après levée, suppression puis restauration opérateur simulée,
  session antérieure au mécanisme, écriture tardive d’une ancienne session,
  rollback transactionnel, cookie remember, double facteur et callback Google
  (fournisseur simulé, authentification Laravel réelle).
- Tests ciblés ban, authentification bannie, suppression et deux premiers
  scénarios de révocation : **18 réussis, 136 assertions**.
- `composer analyse` : réussi.
- `PAO_DISABLE=1 composer ci:check` : **réussi, code de sortie 0**. Lint et
  format frontend, TypeScript, Pint, PHPStan, génération Wayfinder, **146 tests
  unitaires frontend**, build Vite/PWA, garde MySQL et suites Pest/Chromium
  réussissent : **1 888 tests réussis, 6 ignorés, 33 584 assertions**, en
  618,92 secondes pour Pest. Par rapport à la passe précédente : huit tests et
  48 assertions supplémentaires ; aucun nouvel échec. Les six exclusions
  restent Garage, image runtime et quatre parcours Reverb conditionnels
  décrits dans la section précédente. Le build signale la dépréciation
  `inlineDynamicImports`, sans échec. Les deux sous-dossiers temporaires
  attendus par le nettoyage Pest Browser ont été créés avant la commande pour
  éviter les avertissements `rmdir` déjà documentés ; aucun code vendor ni
  réglage PHPUnit n’a été modifié.
- `git diff --check` : réussi.

**Limites :** le test d’écriture tardive reproduit l’ordre des opérations,
pas deux requêtes simultanées sur deux connexions SQL. Les modes fichier et
Redis utilisent la même suite ; les parcours OAuth chez Google, WebAuthn sur
appareil et le déploiement Coolify ne sont pas exécutés. Chaque requête web
authentifiée ajoute une lecture fraîche du compte ; la mesure en charge reste
dans le constat n° 10. Aucun changement de contrat 2FA Google (n° 28) n’est inclus.

**Avancement actualisé :** n° 1, 2 et 26 implémentés ; le premier lot est terminé
au niveau des corrections et validations locales. **Huit lots restent**, soit
26 constats à corriger, vérifier ou arbitrer. Le lot 2 commence par la matrice
des contrats à valider avant toute modification de règle métier.

### Lot 2 — matrice de contrats validée (23 octobre 2026)

**Statut : les trois groupes de décisions sont validés par le demandeur**
(« 1 je valide 2 je valide 3 je valide »). Les propositions retenues ci-dessous
constituent le contrat du lot 2, y compris la suppression personnelle des
administrateurs. Les corrections et la validation complète du lot sont
terminées. Ce lot clôt les n° 3, 4, 11, 12, 20 et 28.

La relecture des usages confirme aussi deux dépendances à inclure dans les
corrections : `Swipe::availablePassesFor` omettait les mêmes critères de cible que
la découverte ; `resources/js/layouts/settings/Layout.vue` et
`resources/js/components/admin/AdminAccountMenu.vue` masquaient les
réglages ordinaires aux comptes sans rôle `user`. Modifier uniquement la requête
de découverte ou uniquement les routes de réglages laisserait ces écarts.

| Constat                              | Comportement avant correction revérifié                                                                                                                                                                                                          | Choix validé                                                                                                                                                                                                                                                                                                                                                                                                                                                                                             | Alternative écartée                                                                                                                                                                |
| ------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **3 — cibles sociales**              | `DiscoveryService`, `ProfilePolicy::isPublicTarget`, `CreateSwipe::ensureTargetIsEligible` et `Swipe::availablePassesFor` ne contrôlent pas le rôle `user` ni la vérification e-mail de la cible. Le retrait du rôle conserve le profil.         | Exiger ces deux critères sur découverte, profils publics d’autrui, historique des refus et création/conversion de swipe. Conserver les autres exclusions et les filtres personnels propres à la découverte. Un compte `user+partner` ou `user+admin` reste éligible.                                                                                                                                                                                                                                     | Autoriser explicitement certaines cibles sans rôle membre ou sans vérification, avec un contrat distinct par surface.                                                              |
| **4 — réglages personnels**          | `routes/settings.php` exige rôle membre, profil et tutoriel pour compte, sécurité, export et apparence. `PartnerRoleAuthorizationTest` exige le refus compte pour partenaire seul et admin seul. Les notifications ont déjà un accès plus large. | Ouvrir compte, sécurité, export et apparence à tous les comptes actifs majeurs, authentifiés, vérifiés et ayant accepté les conditions, sans profil social ni tutoriel. Garder la découverte et ses préférences derrière les contrôles membre. Rendre les liens de réglages accessibles dans chaque espace. La suppression personnelle conserve les confirmations existantes et concerne aussi les comptes partenaires et administrateurs ; elle ne devient pas une suppression administrative d’autrui. | Garder la suppression d’un administrateur dans un parcours assisté, tout en ouvrant les autres réglages. Cette exception doit être choisie explicitement et protégée côté serveur. |
| **11 — blocage et historique privé** | `ConversationPolicy::view` et `ConversationTest::test_both_members_keep_history_access_but_cannot_send_after_a_block` conservent l’accès des deux participants à l’historique. Le PRD et le modèle de données annoncent l’inaccessibilité.       | Conserver l’historique existant en lecture seule pour les deux participants, accessible par URL directe ; le blocage interdit messages, réactions et signaux privés ultérieurs. Aligner les documents sur ce choix et conserver le parcours de signalement. Les règles distinctes des groupes restent inchangées.                                                                                                                                                                                        | Rendre aussi l’historique privé inaccessible aux deux participants pendant le blocage, en préservant explicitement le signalement et l’accès administratif autorisé.               |
| **12 — export des messages propres** | `BuildUserDataExport` réutilise `withVisibleParticipant` et le test `test_download_excludes_conversations_hidden_from_the_conversation_list` exclut les propres messages des conversations masquées ou bloquées.                                 | Exporter tous les messages encore conservés dont le demandeur est l’auteur, indépendamment de la visibilité, du statut ou du blocage du pair. Ne pas rétablir de données purgées, exporter les messages d’autrui ni ajouter des données privées sur le pair. Garder les autres protections et exclusions de l’export.                                                                                                                                                                                    | Conserver l’export limité aux conversations visibles et documenter cette restriction explicitement dans le contrat produit.                                                        |
| **20 — annonces partenaires**        | `DeliverPartnerAnnouncement` appelle directement `DatabaseChannel`, puis son job de broadcast. Le `via()` déclarant le Push n’est pas parcouru. Le PRD promet le Push générique, l’architecture l’exclut.                                        | Activer le Push générique après commit, sous réserve des préférences, de l’éligibilité et de l’autorisation appareil courantes. Réutiliser l’identifiant stable de notification et la livraison Push existante, avec reprise indépendante du stockage et du broadcast ; aucun nouvel e-mail.                                                                                                                                                                                                             | Garder les annonces uniquement dans l’application, corriger les déclarations de canaux et retirer la promesse Push des documents concernés.                                        |
| **28 — Google et second facteur**    | `SocialAuthController::callback` ouvre directement la session d’un compte lié actif. Fortify impose son challenge aux connexions par mot de passe avec 2FA confirmée. La sanction Google est renvoyée sans ce challenge local.                   | Exiger également le challenge Fortify après un callback Google valide lorsque la 2FA locale est confirmée, avant d’ouvrir la session complète ou de révéler une sanction. Conserver la connexion passkey comme méthode distincte vérifiée par WebAuthn, sans ajouter de TOTP à ce parcours dans ce lot.                                                                                                                                                                                                  | Garder Google indépendant de la 2FA locale et expliquer cette portée dans les réglages et la documentation FR/EN.                                                                  |

#### Matrices et critères de sortie après décision

**Cibles (n° 3).** Vérifier membre, membre+partenaire, membre+admin,
partenaire seul et admin seul ; puis adresse non vérifiée, mineur, inactif,
banni, profil masqué/incomplet et blocage dans les deux sens. Couvrir liste,
carte déjà chargée, URL de profil, liens sociaux, historique des refus, like,
pass et conversion pass→like. Le recontrôle transactionnel doit relire les
critères sensibles sous le verrou utilisateur commun aux mutations de rôles.
Ne pas étendre automatiquement ces critères aux historiques privés existants.
Suites : `DiscoveryPageTest`, `DiscoveryQueueTest`, `DiscoveryPreferencesTest`,
`PublicMemberProfileTest`, `ProfileSocialLinksTest`, `PassedProfilesTest`,
`CreateSwipeTest` et `CreateSwipeConcurrencyTest`.

**Réglages (n° 4).** Tester les mêmes combinaisons de rôles, avec et sans
profil/tutoriel, ainsi que visiteur, non vérifié, conditions non acceptées,
mineur, compte banni et suppression en attente. Séparer compte/sécurité/export
des préférences et fonctions sociales. Vérifier menus admin/partenaire/membre,
retour de page sans profil, liens passkey, confirmation de mot de passe,
confirmation de suppression d’un compte exclusivement social, et effets de
désactivation partenaire. Ne pas ouvrir la gestion d’un autre compte.
Suites : `PartnerRoleAuthorizationTest`, `Settings/`, tests Browser de navigation
et de sécurité. La suppression personnelle admin est validée ; son test HTTP conserve
la confirmation de mot de passe avant la mise en suppression différée.

**Historique et export (n° 11–12).** Traiter séparément l’auteur du blocage,
sa cible et un tiers ; URL directe, liste, pagination, réactions, notifications,
révocation d’une souscription et déblocage. Le masquage conserve le contrat
distinct du PRD. Vérifier le signalement pendant le blocage et les accès de
modération existants. Pour l’export, construire des messages propres et reçus,
un pair visible/masqué/bloqué/inactif/banni, puis un graphe conservé après
suppression lorsque le modèle le permet ; seuls les messages propres encore
stockés doivent suivre le choix validé. Suites : `ConversationTest`,
`PrivateChatRevocationTest`, tests de signalement et
`Settings/DirectUserDataExportTest`.

**Annonces (n° 20).** Partir d’une livraison réelle de l’Action, pas seulement
de `Notification::via`. Vérifier notification unique, UUID conservé,
préférence désactivée et abonnement révoqué avant consommation, compte devenu
inéligible, retry et broadcast déjà effectué. Un échec Push ne doit ni recréer
la notification persistante ni fausser les statistiques. Suites partenaires de
dispatch et d’engagement, `WebPushNotificationCoverageTest` et tests de
livraison Push existants. Aucun envoi vers un appareil réel pendant les tests.

**Authentification (n° 28).** Mot de passe, Google lié et passkey × compte
actif/banni × 2FA confirmée ou non ; challenge valide/invalide, code de secours,
session et remember, callback OAuth invalide et compte en attente de
suppression. Réutiliser le challenge Fortify et ses limites de tentatives ;
ne jamais lier automatiquement un compte par égalité d’e-mail. Vérifier que
la version de session du lot 1 n’est enregistrée qu’à l’authentification
complète. Suites `Auth/SocialAuthenticationTest`,
`Auth/BannedAuthenticationTest`, `Auth/SessionRevocationTest` et sécurité.
Un fournisseur Google simulé ou un test de contrôleur passkey ne constitue
pas une recette OAuth/WebAuthn sur appareil réel.

**Ordre d’implémentation retenu :** cibles → réglages → historique → export
→ annonces → Google/2FA. Chaque correction suit son propre cycle rouge/vert et
ses contrôles ciblés, puis les contrôles complets concernés. Pas de helper
d’autorisation universel : droits sociaux, réglages personnels, export et
authentification restent des contrats distincts. Les documents normatifs sont
maintenant alignés sur les choix validés.

**Validation de la préparation :** garde de base confirmée sur
`mysql/dlp_friends_test`, puis **76 tests réussis, 574 assertions** en 16,30 s
sur `PartnerRoleAuthorizationTest`, `ConversationTest`,
`Settings/DirectUserDataExportTest`, `Auth/SocialAuthenticationTest`,
`Auth/BannedAuthenticationTest`, `Partner/PartnerAnnouncementDispatchTest` et
`WebPushNotificationCoverageTest`. Ces résultats caractérisent l’existant ;
ils ne valident pas les propositions ni les scénarios encore à ajouter.
Prettier sur le rapport et `git diff --check` réussissent. Aucun code applicatif
ni test n’a été modifié dans cette étape ; pas de relance de CI complète pour
une préparation documentaire seule.

### Lot 2 — corrections implémentées (23 octobre 2026)

Les six arbitrages approuvés ont été appliqués sans créer de couche commune
entre des autorisations différentes. Les descriptions initiales des constats
ci-dessus restent la trace de l’état audité, à confronter au bilan ci-dessous.

| Constat                              | Correction et preuves                                                                                                                                                                                                                                                                                                                                                                                                                  | Risque et tests de non-régression                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                 |
| ------------------------------------ | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **3 — cibles sociales**              | `DiscoveryService::for`, `Swipe::availablePassesFor`, `ProfilePolicy::isPublicTarget` et `CreateSwipe::ensureTargetIsEligible` exigent maintenant rôle `user` et e-mail vérifié. L’Action recontrôle les critères avec le compte relu sous verrou utilisateur.                                                                                                                                                                         | Une carte chargée avant retrait de rôle peut rester affichée, mais sa soumission est refusée. `SocialTargetEligibilityTest` couvre trois cibles inéligibles sur découverte, profil, liens et swipes, deux rôles cumulés valides, et la révocation après chargement initial des rôles. Les exclusions et filtres précédents sont conservés.                                                                                                                                                                                                        |
| **4 — réglages personnels**          | `routes/settings.php` ouvre compte, apparence, sécurité et export aux comptes actifs majeurs vérifiés ayant accepté les conditions. Les seuls réglages de découverte gardent les trois middlewares sociaux. `AccountController`, la page Account, le layout Settings et `AdminAccountMenu` adaptent les données et les liens.                                                                                                          | Élargissement voulu aux comptes sans profil/tutoriel, y compris partenaires et administrateurs seuls. `PersonalSettingsAccessTest` couvre lecture, modification, export et suppression personnelle pour chaque rôle ; confirmation incorrecte refusée, confirmation correcte et déconnexion vérifiées. Les tests de rôles, d’onboarding et de navigation sont alignés, y compris le lien dans le menu admin au clavier. Aucun accès à la gestion d’autrui n’est ajouté.                                                                           |
| **11 — historique après blocage**    | Aucun changement applicatif nécessaire : la Policy autorisait déjà la lecture seule. PRD, modèle de données et sécurité décrivent maintenant le même contrat.                                                                                                                                                                                                                                                                          | `ConversationTest` vérifie par HTTP, pour chaque participant, lecture des messages conservés et refus d’envoi/réaction. `PrivateChatRevocationTest` conserve la protection des signaux différés du lot 1. Les historiques ne deviennent pas publics.                                                                                                                                                                                                                                                                                              |
| **12 — export des messages propres** | `BuildUserDataExport` ne filtre plus les conversations avec `withVisibleParticipant`. Le filtre existant sur `author_user_id` reste en place.                                                                                                                                                                                                                                                                                          | Le contenu propre conservé devient exportable même si le pair est masqué, bloqué dans un sens ou l’autre, banni ou en suppression différée. `DirectUserDataExportTest` couvre ces cas et exclut les messages reçus. Les données purgées ne sont pas reconstruites.                                                                                                                                                                                                                                                                                |
| **20 — Push partenaire**             | `PushPartnerAnnouncement` relit la livraison persistante et réutilise son UUID de notification avec `WebPushChannel`. L’Action lance Push et broadcast après commit ; une exception d’insertion Push n’empêche pas de tenter le broadcast. Le retry administratif reprend le Push même si le broadcast est déjà fait. `webPushAccessAllowed` recontrôle destinataire éligible, livraison livrée non masquée et notification conservée. | `PartnerAnnouncementDispatchTest` part de l’Action réelle, vérifie l’absence d’enqueue avant commit, UUID stable, contenu générique, reprise et déduplication, ainsi que les révocations de rôle, vérification e-mail, statut, préférences et appareil avant consommation. La livraison Push et sa réservation existantes sont réutilisées : le défaut de bail n° 19 reste au lot 3. Un retry administratif peut envoyer un Push pour une ancienne notification conservée jamais livrée à cet appareil ; un envoi déjà enregistré est dédupliqué. |
| **28 — Google avec 2FA locale**      | `SocialAuthController::callback` prépare le challenge Fortify pour un compte lié actif ou banni dont la 2FA locale est confirmée. Il n’ouvre pas la session complète et n’annonce pas la sanction avant ce challenge.                                                                                                                                                                                                                  | `GoogleTwoFactorTest` couvre challenge, code de secours invalide puis valide, compte actif/banni, absence de version de session avant authentification, version courante après succès, et refus d’un compte en suppression. Les suites existantes Google, bannissement, sécurité et révocation de session restent exécutées. Le parcours passkey ne reçoit pas de TOTP supplémentaire.                                                                                                                                                            |

**Cycle de correction et contrôles ciblés.** Les nouveaux tests ont d’abord
échoué sur les comportements visés : cibles (3 échecs), accès aux réglages
(8), export (1), Google/2FA (2) et dispatch Push (8). L’historique conservé
était déjà conforme au contrat approuvé et a reçu une protection HTTP
supplémentaire. Après corrections : 36 tests de contrats réussis (379
assertions), puis 220 tests de régression réussis (1 821 assertions, 36,69 s).
Les trois tests d’export ont également réussi séparément (45 assertions).
PHPStan, ESLint, Pint et le formatage des fichiers modifiés sont passés.

**Ajustement des attentes historiques.** La première CI complète a fini avec
1 910 succès, 6 skips et deux échecs : `ProductOnboardingTest` attendait encore
une redirection depuis les réglages et `Browser/AdminTest` attendait leur
absence dans le menu admin. Ces assertions correspondaient au contrat
antérieur ; elles sont alignées sur l’accès personnel approuvé. Le test
onboarding vérifie aussi que les préférences sociales restent bloquées.
Après cet ajustement sans changement applicatif, les deux suites réussissent
ensemble : 31 tests, 357 assertions, 16,16 s.

**Validation complète réussie.** La seconde exécution de `composer ci:check`
termine avec le code 0 : ESLint, Prettier, TypeScript, Pint, PHPStan,
génération Wayfinder, 146 tests frontend, build Vite/PWA et **1 912 tests Pest
réussis, 6 ignorés, 33 873 assertions** en 618,63 s pour Pest. La référence
avant lot 2 était de 1 888 succès et 33 584 assertions, avec les mêmes 6 skips
et 146 succès frontend : **24 tests et 289 assertions supplémentaires**.
La dernière mise à jour documentaire passe aussi Prettier et
`git diff --check`.

Les six skips restent explicites : Garage sans `GARAGE_INTEGRATION=1` ni
bucket préparé ; intégration runtime sans `DLP_RUNTIME_TEST_IMAGE` ; quatre
parcours temps réel sans `REALTIME_BROWSER_TESTS=true` et Reverb démarré.
Les autres tests navigateur Chromium sont exécutés. Les avertissements Vite
sur les assets de marque résolus au runtime et `inlineDynamicImports` sont
identiques à la référence précédente. Les tests sont isolés sur
`mysql/dlp_friends_test` ; aucun envoi Push externe ni appel OAuth Google réel
n’a été effectué. Journaux locaux : `/tmp/dlp-lot2-ci-final.log`,
`/tmp/dlp-lot2-expectations-green.log`, `/tmp/dlp-lot2-contract-green.log`
et `/tmp/dlp-lot2-regressions.log`.

**Périmètre et suite.** Ce bilan décrit le lot 2 ; ce n’est pas l’audit final
transversal demandé pour la fin des refactorings. Aucun déploiement, build
Docker de production, test OAuth/WebAuthn sur appareil réel ou recette
multi-nœuds Reverb n’est réalisé ici. Après ce lot 2 terminé, **7 lots et
20 constats** restent à corriger ou investiguer selon le plan ci-dessus.
Le lot suivant concerne les timeouts, récapitulatifs et réservations de
livraison (n° 5, 6 et 19).

### Lot 3 — fiabilité des workers et des notifications (23–24 octobre 2026)

**Constats traités localement : n° 5, 6 et 19.** Les mécanismes existants de
livraison et les modèles sont conservés. Aucune dépendance, couche générique,
interface de transport supplémentaire ou nouvelle fonctionnalité produit.

| Constat                                     | Correction                                                                                                                                                                                                                                                                                                                                                                                            | Preuves de non-régression                                                                                                                                                                                                                                                               |
| ------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **5 — timeout et réservation**              | Le timeout du worker reste à 90 s ; les valeurs par défaut de `retry_after` passent à 120 s pour Redis, SQL et Beanstalkd. `DeploymentReadiness` refuse une connexion dont la réservation est inférieure à 120 s. Les appels du récapitulatif Resend et de Web Push ont des bornes de connexion/total de 5/30 s ; SMTP passe de non borné à 30 s.                                                     | `DeploymentReadinessTest` refuse 60 et 90 s et contrôle la marge par défaut. Le worker n’est pas accéléré artificiellement : les jobs longs conservent leur budget de 90 s.                                                                                                             |
| **6 — récapitulatif et réseau sous verrou** | `SendWeeklyEmailRecap` réserve la livraison dans une courte transaction, sans verrou utilisateur pendant l’appel réseau. Une réservation de dix minutes fait patienter les consommateurs concurrents. L’envoi Resend réutilise un UUID et un payload identique chiffré ; les autres mails conservent le transport Laravel. Le consentement, le destinataire et l’éligibilité sont relus à la reprise. | `WeeklyEmailRecapTest` contrôle le niveau de transaction dans l’appel mail et dans le faux fournisseur HTTP, la concurrence, la réponse perdue après acceptation, la stabilité de la requête après changement de langue, les révocations, l’expiration et le nettoyage des instantanés. |
| **19 — Push interrompu**                    | `DeliverWebPushNotification` retourne le délai restant quand le bail est occupé. `SendWebPushNotification` remet le job en file jusqu’à l’expiration, sans acquitter définitivement la livraison. Les deux jobs autorisent les remises en file avec quatre exceptions maximales : une attente de bail ne consomme plus le budget d’erreurs ; la fenêtre hebdomadaire du récapitulatif est préservée.  | `WebPushDeliveryTest` utilise la vraie file SQL et `Worker::process`, y compris une cinquième consommation malgré `maxTries: 3`, puis récupère la livraison après expiration. Les tests existants de déduplication, préférences, accès et révocation sont conservés.                    |

**Garantie exacte du récapitulatif.** Le transport Resend de Laravel installé
appelle le SDK sans son argument d’idempotence ; ajouter un simple en-tête MIME
ne protégerait pas l’appel HTTP. Le récapitulatif seul utilise donc le client
HTTP Laravel avec `Idempotency-Key`, sans réimplémenter tous les transports.
[Resend conserve une clé pendant 24 heures](https://resend.com/changelog/idempotency-keys)
et exige une requête identique. `first_attempt_at`, `idempotency_key` et
`resend_payload` assurent cette stabilité. Le payload contient les compteurs
et liens génériques déjà présents dans le mail, l’expéditeur et l’adresse
destinataire ; il est chiffré et ne contient aucun message privé ni identité
de correspondant. Il est effacé au succès, à l’annulation ou à l’échec
définitif sans autre consommateur actif. Les champs techniques ne sont pas
ajoutés à l’export utilisateur ; les traces de livraison exportées sont
préservées et la suppression en cascade reste effective.

Après 23 heures depuis la première tentative, une reprise incertaine est
arrêtée plutôt que de risquer un nouvel envoi hors garantie fournisseur.
`skipped_at` peut donc signifier « reprises arrêtées après acceptation
incertaine », et ne prouve pas l’absence d’acceptation. Un changement d’adresse,
le retrait du compteur facultatif ou la perte d’éligibilité arrête également
une reprise figée ; aucun nouveau contenu n’est envoyé sous la même clé.
Une première tentative retardée de deux jours par une panne de file reste
éligible dans la fenêtre hebdomadaire ; elle n’est pas soumise à une échéance
arbitraire depuis la création du job. Ce scénario est vérifié avec le vrai
worker SQL.
Un ancien job qui échoue ne peut pas annuler la réservation active d’un autre.
Le dispatch hebdomadaire efface aussi les payloads de plus de 24 h dont le bail
est expiré, même pour un utilisateur ayant retiré son consentement ; cette
rétention peut donc aller jusqu’au prochain passage hebdomadaire après un arrêt
sans rappel d’échec, et dépend du fonctionnement du scheduler.

**Limites.** Les réservations et reprises sont exercées avec MySQL et le vrai
worker en processus de test ; l’arrêt brutal est représenté par une livraison
persistante abandonnée. Aucun `SIGKILL` de worker Docker, consommateur Redis
concurrent, ralentissement réseau réel, envoi Resend réel ou réception Push sur
appareil réel n’est utilisé comme preuve. La perte de réponse du fournisseur
est simulée. Web Push et SMTP ne fournissent pas la même garantie d’idempotence
que Resend : l’acceptation externe suivie d’un arrêt avant écriture SQL reste
une frontière susceptible de produire une nouvelle tentative. La durée des
jobs métier en production n’a pas été mesurée ; la correction conserve leur
timeout existant. Les jobs déjà sérialisés conservent les limites inscrites
dans leur ancien payload jusqu’à consommation ou recréation.

**Déploiement.** Nouvelle migration additive
`2026_10_23_060000_add_reservation_to_weekly_email_recap_deliveries.php` :
réservation, première tentative indexée, UUID et payload chiffré nullable.
Exécution explicite nécessaire avant les nouveaux workers ; aucune migration
n’est ajoutée aux points d’entrée Docker et aucun déploiement n’est effectué.
Les options `REDIS_QUEUE_RETRY_AFTER`, `DB_QUEUE_RETRY_AFTER` ou
`BEANSTALKD_QUEUE_RETRY_AFTER` inférieures à 120 bloquent désormais la readiness.

**Validations du lot.**

- Cycle rouge/vert : les tests reproduisent l’acquittement prématuré du Push,
  l’appel mail sous transaction, l’absence de contrôle de réservation, les
  annulations concurrentes et l’expiration prématurée d’un premier essai après
  deux jours de panne. Les corrections font passer **80 tests ciblés et
  240 assertions** (`/tmp/dlp-lot3-targeted-final.log`).
- Image finale : `docker build --target runtime --tag dlp-friends:ci .` réussit
  (`/tmp/dlp-lot3-docker-final.log`). Le smoke runtime PHP 8.4.27 a également
  instancié le transport Web Push et vérifié `retry_after=120`.
- Une première passe complète termine avec **1 923 succès, 2 échecs navigateur
  Trip et 6 tests ignorés** (`/tmp/dlp-lot3-ci-final.log`). Les deux scénarios
  Trip passent à la relance isolée, qui sort toutefois avec deux avertissements
  non détaillés par le rapporteur (`/tmp/dlp-lot3-trip-retry.log`). L’assertion de
  date du premier scénario est sensible à minuit : le formulaire utilise
  l’heure courante plus quinze minutes, tandis que le test exige la date du
  jour courant ; le second échec est un timeout dont la cause n’est pas établie.
  Ces fichiers ne sont pas modifiés dans ce lot.
- Passe complète finale : `DLP_RUNTIME_TEST_IMAGE=dlp-friends:ci composer
ci:check` termine avec le **code 0** : ESLint, format frontend, TypeScript,
  Pint, PHPStan, Wayfinder, **146 tests frontend / 232 assertions**, build
  Vite/PWA et **1 927 tests Pest réussis, 5 ignorés, 33 935 assertions**, en
  **635,99 s** pour Pest (`/tmp/dlp-lot3-ci-complete.log`). Les deux scénarios
  Trip passent dans cette suite sans avertissement Pest. Leur sensibilité à
  l’heure et le timeout précédent restent consignés ci-dessus.
- L’intégration runtime auparavant ignorée est exécutée avec l’image finale :
  isolation des namespaces de maintenance Redis et drainage des écritures
  HTTP/FPM sur des conteneurs et un réseau Docker temporaires. L’identité
  MySQL y est simulée ; ce test ne prouve pas une migration ou un déploiement
  réel. Les cinq skips restants sont Garage (bucket d’intégration non préparé)
  et les quatre parcours Reverb nécessitant `REALTIME_BROWSER_TESTS=true`
  et un serveur configuré.
- Comparaison à la référence documentée du lot 2 : **15 succès Pest et
  62 assertions supplémentaires**, un skip de moins grâce au runtime Docker,
  toujours 146 succès frontend. Le Bun local est **1.2.18** ; l’image Docker
  compile les ressources avec **1.3.14**, version attendue du projet. Les
  contrôles locaux ne constituent donc pas une preuve de lint sous Bun 1.3.14.
- Prettier des trois documents modifiés et `git diff --check` réussissent.
  Les avertissements Vite sur les assets de marque résolus au runtime et
  `inlineDynamicImports` restent présents ; aucun changement de leur domaine
  n’est inclus ici.

Après ce lot, **6 lots et 17 constats** restent à corriger ou investiguer selon
le plan final. Le lot suivant concerne la collecte des logs et la clé Resend
Usage dédiée (n° 27 et 29). Ce bilan n’est pas l’audit final de la branche.

### Lot 4 — logs Docker et clé Resend Usage (24 octobre 2026)

**Constats n° 27 et 29 : corrections versionnées réalisées ; vérifications
d'exploitation encore ouvertes.** Aucun changement des règles d'inscription,
de permission Resend ou des journaux métier. Aucune dépendance ajoutée.

- **27 — Logs collectables et bornés.** `compose.production.yaml` sélectionne
  le canal Laravel `stderr` existant pour les quatre services. Le pilote Docker
  `json-file` est explicitement borné à trois fichiers de 10 Mo par conteneur.
  Le niveau `warning` reste configurable. La configuration locale et bêta ne
  change pas. Aucun nouveau canal, processeur ou service de collecte n'est créé.
- **29 — Clé dédiée transmise.** Le même environnement partagé transmet
  `RESEND_USAGE_API_KEY` aux quatre services. Une valeur absente ou vide reprend
  `RESEND_API_KEY` lors du rendu Compose. La clé d'envoi reste distincte quand
  une clé dédiée est fournie. `CheckEmailRegistrationAvailability` et son refus
  fermé sont conservés ; ni droits supplémentaires ni contournement du quota.
- **Documentation.** `docs/operations.md` décrit la rotation, la collecte
  nécessaire avant suppression et la recréation des conteneurs après changement
  d'environnement. Un cache de configuration existant doit être régénéré dans
  les nouveaux conteneurs. Aucun secret n'est affiché par les vérifications.

**Tests et preuves.** Trois tests du Compose rendu échouent avant correction :
canal fichier au lieu de `stderr`, clé dédiée absente et repli absent
(`/tmp/dlp-lot4-red.log`). Après correction, les tests de déploiement et de quota
passent : **52 tests, 466 assertions** (`/tmp/dlp-lot4-targeted.log`). Le test
supplémentaire de quota vérifie qu'une clé dédiée valide rouvre l'inscription
sans réutiliser le refus 403 mis en cache pour la clé d'envoi. Les scénarios
existants couvrent limites jour/mois, absence de clé, timeout, 403/429/503,
expiration du cache, relecture à la soumission et indépendance de Google.

L'image `dlp-friends:lot4` est construite avec succès
(`/tmp/dlp-lot4-docker.log`). Une sonde temporaire hors dépôt
(`/tmp/dlp-lot4-runtime.py`) utilise le Compose rendu avec uniquement des valeurs
fictives dans huit conteneurs sans réseau : avant/après remplacement pour
chacun des quatre services. Elle vérifie le canal effectif et les clés
distinctes après `config:cache`, la réception d'un marqueur Laravel dans les
logs Docker et les options effectives du pilote. Pour `web`, le marqueur vient
d'une requête HTTP servie par PHP-FPM ; pour les trois autres, d'un bootstrap
Laravel CLI, sans démarrer les workers, scheduler ou Reverb. La collecte est un
export vers un fichier hôte temporaire : le marqueur antérieur reste lisible
après suppression et recréation du conteneur. Les huit conteneurs sont supprimés
à la fin (`/tmp/dlp-lot4-runtime.log`).

**Limites et sortie d'exploitation.** Le pilote Docker ne conserve pas les
logs d'un conteneur supprimé. La collecte distante, ses droits, sa durée de
rétention et la consultation après remplacement dans Coolify réel restent
explicitement à configurer et à vérifier avant déploiement ; le n° 27 n'est
donc pas déclaré clos en production. La sonde inspecte les options de rotation,
sans produire 30 Mo pour exercer leur limite. Aucun fournisseur Resend réel,
aucune permission de clé réelle, aucun déploiement ni changement du daemon Docker
n'est utilisé comme preuve. Les canaux fichier explicitement choisis et le
logger de secours restent hors du canal par défaut modifié.

**Validation complète et limite de l'outillage.**

- `DLP_RUNTIME_TEST_IMAGE=dlp-friends:ci composer ci:check` réussit ESLint,
  format frontend, TypeScript, Pint, PHPStan, Wayfinder, les **146 tests
  frontend / 232 assertions** et le build Vite/PWA. Pest exécute **1 931 tests
  réussis, 5 ignorés, 33 996 assertions**, en **640,77 s**, mais la commande
  termine avec le **code 1** pour deux avertissements
  (`/tmp/dlp-lot4-ci.log`). Elle n'est donc pas annoncée entièrement verte.
- Une seconde passe complète avec `PAO_DISABLE=1`, le même runtime Docker et
  le rapporteur Pest normal confirme **1 931 succès, 5 skips, 33 996 assertions**
  en **638,50 s**, avec le même code 1
  (`/tmp/dlp-lot4-pest-details.log`). Aucun test n'échoue.
- Le journal d'événements d'une découverte complète avec seulement les deux
  cas Resend exécutés reproduit exactement les deux avertissements :
  `vendor/pestphp/pest-plugin-browser/src/Support/Screenshot.php:83` appelle
  `rmdir` sur `tests/Browser/Screenshots/Sliders` et `ImageDiffView` absents.
  Ces avertissements PHP supprimés par `@` sont comptés par PHPUnit et
  bloquent `failOnWarning` ; le résumé JSON ne les détaille pas
  (`/tmp/dlp-lot4-discovery-events.log`). Après ce nettoyage automatique,
  la même commande ciblée termine avec **code 0**, sans avertissement et sans
  changement de code (`/tmp/dlp-lot4-clean-discovery-events.log`). La suite
  complète n'a pas été rejouée une troisième fois ; le nettoyage du plugin
  reste une limite d'outillage documentée, sans modification de `vendor/`.
- Les 52 tests ciblés passent aussi avec le rapporteur normal, **code 0**.
  Les cinq skips complets restent Garage (bucket non préparé) et les quatre
  parcours Reverb nécessitant leur serveur et `REALTIME_BROWSER_TESTS=true`.
  L'intégration runtime complète utilise l'image du lot 3, dont le code
  applicatif n'a pas changé ; les sondes décrites ci-dessus utilisent l'image
  nouvellement construite `dlp-friends:lot4`.
- Comparé à la dernière référence du lot 3 : **4 succès et 61 assertions
  supplémentaires**, 5 skips inchangés. Bun local reste **1.2.18** ; le build
  Docker utilise **1.3.14**, version attendue. Les avertissements Vite sur les
  assets résolus au runtime et `inlineDynamicImports` restent présents.
  `git diff --check` réussit.

**Avancement.** Il reste **5 lots et 15 constats** dans le plan de corrections
du dépôt, auxquels s'ajoutent les vérifications d'exploitation ci-dessus.
Le prochain lot est le n° 5 : continuité Vue/Reverb, visibilité de lecture,
rattrapage, abonnements réactifs et erreurs Push (n° 7, 13, 21 et 23).

### Lot 5 — continuité des groupes, frappe et erreurs Push (24 octobre 2026)

**Constats n° 7, 13, 21 et 23 : corrections réalisées et régressions ciblées
vérifiées.** Réutilisation de `useMessagingRefresh`, `MessageTimeline`,
`MessageComposer`, des autorisations de groupe et du message d'erreur Push
traduit existants. Aucun nouveau composable, composant, endpoint ou dépendance.

- **7 — Lecture visible et synchronisée.** `GroupChat.vue` n'acquitte plus un
  message dans un document masqué. Les messages reçus en direct ne font pas
  avancer seuls le curseur : l'acquittement attend le dernier lot de rattrapage.
  Une réponse arrivant après masquage ou démontage ne déclenche pas de lecture.
  Un échec réseau d'acquittement reste réessayable au rafraîchissement suivant.
- **13 — Rattrapage au-delà de la première page.** Les données des conversations
  événement et trajet proposent une prop Inertia optionnelle `messageUpdates` :
  messages du groupe autorisé, après le curseur, dans l'ordre croissant et par
  lots de 100. Le composant poursuit jusqu'au lot incomplet. Il réutilise le
  rafraîchissement privé existant pour montage, reprise de visibilité, retour
  réseau, reconnexion et vérification périodique. La fusion conserve les IDs
  uniques, le brouillon et les messages déjà chargés dans le snapshot Inertia
  pour la navigation arrière/avant. Le curseur de rattrapage est mémorisé
  séparément dans l'historique Inertia : quitter le groupe entre deux lots ne
  doit pas prendre le plus grand ID affiché pour une preuve de synchronisation.
  Les réactions modifiées pendant la requête
  conservent leur valeur locale récente. Les réponses 403 renvoient toujours
  à la liste des groupes. La sérialisation et son coût SQL existant restent
  rattachés au lot 8 ; aucune optimisation SQL supplémentaire n'est revendiquée.
- **21 — Liste de frappe réactive.** `Conversations/Index.vue` transmet un getter
  des conversations actives à `useConversationListTyping`. L'ajout, le retrait
  ou le remplacement de la liste actualisent les callbacks. Les conversations
  inchangées conservent leur abonnement ; retrait et démontage nettoient le
  timer et l'état de frappe. Seul le callback possédé par ce composable est
  retiré : quitter le canal Echo partagé supprimerait les autres écouteurs.
- **23 — Erreurs Push terminées et réessayables.** `useWebPush` capture aussi
  les erreurs d'initialisation et de stockage, réinitialise `busy` et expose les
  échecs de synchronisation/révocation serveur. Le UUID est conservé après un
  échec de révocation pour permettre le retry ; une réponse 404 est acceptée.
  L'initialisation ne reste plus suspendue sur `serviceWorker.ready` en absence
  d'enregistrement. L'invitation reste ouverte avec une alerte traduite après
  échec technique, sans enregistrer un refus. Un vrai refus ferme toujours
  l'invitation. `WebPushDevices` restaure ses boutons dans `finally`, affiche
  l'erreur et permet une nouvelle tentative ; une suppression en cours bloque
  les clics concurrents. L'indisponibilité du stockage de préférence ne casse
  plus le montage ou la fermeture de l'invitation.

**Tests et preuves.** Les régressions ont été observées avant correction :
lecture créée dans un document masqué (événement et trajet), prop de rattrapage
absente, callback de frappe conservé après retrait, erreurs du composable Push
non terminées et absence de retry dans les deux interfaces Push. Les tests
navigateur des anciens composants ont été exécutés avec restauration des seuls
fichiers concernés, puis les corrections rétablies et les tests repassés.

- `GroupConversationTest` ajoute deux cas backend : plus de 100 messages,
  ordre/cursor, borne, isolation entre groupes et refus 403 d'un tiers :
  **2 tests / 20 assertions** ciblés réussis.
- `GroupChatContinuityTest` vérifie les événements et trajets dans Chromium :
  page initialement masquée, 205 messages manqués, deuxième requête suspendue,
  aucun curseur de lecture avant la fin des trois lots, 206 IDs uniques au
  total, brouillon conservé et navigation arrière/avant, y compris pendant le
  rattrapage : **2 tests / 36 assertions**. Le changement de visibilité est simulé ;
  il ne constitue pas une preuve de veille physique d'un appareil mobile.
- `NotificationSettingsTest` ajoute un échec HTTP d'activation puis retry et
  une suppression interrompue par une erreur réseau puis retry. Avec les cas
  existants, ces deux fichiers navigateur passent ensemble : **10 tests /
  111 assertions**, sans erreur JavaScript ni avertissement, code 0
  (`/tmp/ponytail-lot5-browser-final.log`).
- Les tests Bun supplémentaires couvrent le remplacement de liste et la
  préservation d'un callback tiers, le démontage, l'initialisation Push rejetée,
  le stockage refusé et la révocation HTTP 503. La suite frontend entière
  repasse à **150 tests / 252 assertions**, code 0
  (`/tmp/ponytail-lot5-unit.log`). Le mock Echo d'un test existant conserve les
  exports du module pour permettre l'exécution de ces suites ensemble.

**Validation du lot.** `composer ci:check` termine avec **code 0** : ESLint,
Prettier, TypeScript, Pint, PHPStan, génération Wayfinder, tests frontend et build
Vite/PWA réussissent. Pest compte **1 936 succès, 6 skips, 34 060 assertions**
en **658,18 s** (`/tmp/ponytail-lot5-ci.log`). Les six skips sont Garage (bucket
non préparé), les quatre parcours demandant un serveur Reverb et l'intégration
Docker non activée par variable d'environnement dans cette commande. Cette
dernière est exécutée séparément avec l'image fraîche ci-dessous. Comparé au
lot 4 : six tests Pest ajoutés ; le test runtime précédemment activé est ici
isolé, ce qui explique cinq succès supplémentaires et un skip supplémentaire.
Les avertissements de nettoyage Pest du lot 4 ne se reproduisent pas dans cette
passe complète. Les avertissements Vite déjà documentés restent présents.

La relecture finale ajoute un cas rouge : navigation arrière/avant pendant la
deuxième requête de rattrapage suspendue. Avant mémorisation du curseur, les
deux domaines n'affichent que **111 messages au lieu de 206** après retour
(`/tmp/ponytail-lot5-history-red.log`). La correction finale est postérieure à
cette passe CI complète : les contrôles frontend et les parcours navigateur
concernés sont rejoués après elle ; la suite Pest entière n'est pas annoncée
comme rejouée après cet ajustement. Le renforcement du test de frappe repasse
séparément avec la suite Bun à 252 assertions. `git diff --check` réussit.

Les premières relances après les cas rouges produisent à nouveau les deux
avertissements de nettoyage de captures du plugin Pest décrits au lot 4 ; une
assertion d'URL immédiate observe aussi transitoirement l'ancienne URL pendant
le rendu Inertia. Le test attend désormais le rendu de la liste et son URL.
La dernière passe combinée termine avec code 0, sans avertissement, sur le
snapshot qui conserve à la fois les messages et leur curseur de reprise.
Les deux tests existants de chargement de l'historique événement, depuis
événements et conversations, passent également après cet ajustement :
**2 tests / 24 assertions**, code 0, avec conservation de l'ancre de scroll
(`/tmp/ponytail-lot5-history-scroll.log`). ESLint, TypeScript, Prettier et le
build sont revalidés sur le code final ; l'image et son intégration runtime
ci-dessous sont reconstruites et rejouées après ce dernier changement.

L'image `dlp-friends:lot5` est construite avec succès, avec Bun 1.3.14
(`/tmp/ponytail-lot5-docker.log`). L'intégration runtime existante passe aussi
avec cette image, code 0 : `python3
tests/Support/operations/runtime_integration.py dlp-friends:lot5`
(`/tmp/ponytail-lot5-runtime.log`). Elle utilise ses conteneurs Redis/PHP-FPM
éphémères et son identité MySQL synthétique, sans déploiement réel.

**Limites.** Aucun fournisseur Push réel, appareil iOS/Android réel, test
multi-onglets physique ou serveur Reverb réel n'est utilisé comme preuve dans
ce lot. Le rattrapage est vérifié sans émission Reverb, ce qui exerce le cas des
broadcasts manqués ; le transport réseau Reverb reste une vérification séparée.
Les réactions simultanées sont protégées par la fusion existante adaptée au
groupe, sans nouveau scénario navigateur de concurrence des réactions.

**Avancement.** Il reste **4 lots et 11 constats** dans le plan du dépôt,
auxquels s'ajoutent les vérifications d'exploitation déjà ouvertes. Le prochain
lot est le n° 6 : validation des dates locales et première réaction accessible
(n° 18 et 22). Ce bilan ne remplace pas l'audit final demandé après les lots.

**Correctif après revue du lot 5.** L'erreur Push partagée restait affichée
après une initialisation HTTP 503 suivie d'un remontage et d'une synchronisation
réussie. `useWebPush` efface désormais cette erreur après synchronisation
réussie. Un test reproduit d'abord l'erreur persistante, puis passe avec la
correction. Vérifications après correction : **151 tests frontend / 258
assertions**, **8 tests navigateur de notifications / 75 assertions**, ESLint,
Prettier, TypeScript et build Vite/PWA réussis. Les avertissements Vite déjà
documentés restent présents. La suite PHP complète et l'image Docker ne sont
pas rejouées pour cet ajustement frontend ; aucun fournisseur Push réel n'est
sollicité.

## Lot 6 — Dates locales et première réaction accessible (24 octobre 2026)

**Périmètre : constats n° 18 et 22.** Leurs causes sont corrigées dans la
Form Request commune de création/modification d’événement et la timeline
partagée entre conversations privées, événements et trajets. Aucun service,
composant, composable ou dépendance supplémentaire n’est introduit.

### Corrections et règles conservées

- **N° 18 :** `StoreEventRequest` arrête la validation du champ au premier échec
  de format, puis contrôle l’heure locale après validation de base. Comme dans
  `StoreTripRequest`, l’analyse explicite dans `Europe/Paris` et la comparaison
  aller-retour refusent une heure inexistante plutôt que de la déplacer.
  La date doit être strictement future. `UpdateEventRequest` hérite de cette
  correction ; les Actions et les règles de modification à 24 heures restent
  celles déjà implémentées. Les autres erreurs de formulaire ne suppriment pas
  le contrôle d’une date passée. L’erreur d’heure locale possède ses traductions
  FR/EN dans `lang/*/events.php`. Aucun horizon maximal de trajet n’est importé.
- **N° 22 :** `MessageTimeline.vue` conserve son bouton sémantique, son libellé
  traduit, `aria-pressed` et sa désactivation pendant la requête. À zéro réaction
  sur mobile, `sr-only` conserve l’accès aux technologies d’assistance et au
  clavier ; le focus révèle le bouton. Les espacements sont rétablis au focus
  et sur ordinateur, car `not-sr-only` réinitialise également marge et padding.
  Le double tap reste disponible. Aucun contrôle n’est ajouté aux messages
  propres ou système ; les trajets en lecture seule restent désactivés.
- **Défaut connexe reproduit :** un trajet expiré renvoyait HTTP 200 avec ses
  messages, mais sa discussion restait vide dans Chromium. `GroupChat` émet le
  motif `expired`, alors que `GroupChatInfo` et les traductions attendaient
  `archived`. Le rendu demande une traduction inexistante. Les catalogues
  `lang/{fr,en}/{events,trips}.php` et `resources/js/types/groupChat.ts` utilisent
  désormais `expired`, conformément au backend. La recherche des usages ne
  trouve aucun consommateur de l’ancienne clé complète. Ce défaut affectait
  aussi les événements expirés. Les réactions y restent autorisées selon la
  Policy existante ; les trajets expirés restent en lecture seule. Aucun délai
  d’archivage ni droit métier n’est modifié.

### Preuves et tests de non-régression

`tests/Feature/EventDateValidationTest.php` exerce les deux routes HTTP avec
réponses JSON 422 et erreurs de formulaire Inertia. Il couvre chaîne
arbitraire, date impossible, tableau, nombre, null, champ absent, saut printanier,
instant présent et passé ; chaque refus conserve les événements en base.
Les conversions hiver/été, la minute suivante et l’heure répétée en automne
sont vérifiées. Pour le 31 octobre 2027 à 02:30, la convention existante de
Carbon est conservée : 01:30 UTC. L’erreur d’heure inexistante est vérifiée en
français et anglais. Les anciens tests de création et modification restent
exécutés, notamment les restrictions à 24 heures et la capacité.

La première passe avant correction a **12 échecs sur 40 cas** : entrée arbitraire
produisant HTTP 500, heure printanière silencieusement normalisée et instant
présent accepté, en création comme en modification et en JSON comme en
Inertia (`/tmp/ponytail-lot6-dates-red.log`). Les autres formes invalides et les
conversions ordinaires passaient déjà. La première passe ciblée après correction
réussit avec les anciens tests : **48 tests / 195 assertions**
(`/tmp/ponytail-lot6-dates-green.log`). Les cas supplémentaires de champ absent
et de traduction sont inclus dans la validation complète ci-dessous.

`tests/Browser/MessageReactionAccessibilityTest.php` ajoute six parcours clavier
(320/390 px × privé/événement/trajet), quatre parcours de trajet annulé/expiré
(FR/EN) et deux parcours d’événement expiré (FR/EN). Une vraie touche Tab atteint
la première réaction puis Entrée l’ajoute et Espace la retire. Une réponse fetch
retenue vérifie la désactivation pendant la requête. Les tests contrôlent les
libellés, l’état pressé, l’absence de contrôle propre/système et le débordement
horizontal. `ConversationTest` conserve le parcours double tap et vérifie aussi
les espacements du bouton sur ordinateur.

Le premier cas Chromium échoue avant correction parce que le bouton est en
`display: none` (`/tmp/ponytail-lot6-accessibility-red.log`). Le cas du trajet
expiré échoue séparément sur une page vide, malgré HTTP 200 et les messages
présents dans les props (`/tmp/ponytail-lot6-readonly-diagnostic.log`). Après
correction, les nouveaux parcours et tout `ConversationTest` passent ensemble :
**42 succès, 4 skips Reverb, 372 assertions**
(`/tmp/ponytail-lot6-browser.log`). La commande termine toutefois avec code 1
et deux avertissements ; le résumé JSON ne fournit pas leur détail. Aucun
échec fonctionnel n’est masqué ; la passe complète ci-dessous détermine
le résultat de validation final.

### Validation et limites

`composer ci:check` a exécuté ESLint, Prettier, TypeScript, Pint, PHPStan,
Wayfinder, les **151 tests Bun / 258 assertions**, puis le build Vite/PWA avec
succès. La suite Pest complète termine avec **1 993 succès, 1 échec, 6 tests
ignorés et 34 381 assertions** ; la commande retourne **1**
(`/tmp/ponytail-lot6-ci.log`). L’unique échec est l’ancienne assertion de
`EventTest` exigeant `display: none` pour la première réaction mobile. Elle est
adaptée au masquage visuel accessible (`clip-path: inset(50%)`), comme dans
`ConversationTest`. Le scénario conserve ensuite son contrôle du double tap.

Après cette adaptation de test, les six fichiers concernés sont rejoués
ensemble : `EventDateValidationTest`, `CreateEventTest`, `UpdateEventTest`,
`MessageReactionAccessibilityTest`, `ConversationTest` et `EventTest`.
Résultat Pest : **119 succès, aucun échec, 4 tests ignorés et 824 assertions**
(`/tmp/ponytail-lot6-final.log`). Deux avertissements restent signalés sans
détail dans le résumé JSON. Ils restent à élucider ; la validation ne doit pas
être décrite comme exempte d’avertissements. Les quatre tests ignorés concernent
le transport Reverb réel. Les six skips de la passe complète incluent aussi
les intégrations Garage et Docker dont l’environnement n’est pas activé.

Pint est rejoué sur `EventTest` après adaptation. La suite complète n’est pas
relancée après cette seule modification d’assertion : le code applicatif est
identique à celui exercé par les 1 993 tests réussis. **Aucun succès global de
`composer ci:check` n’est donc revendiqué pour l’état final.** Les avertissements
Vite déjà documentés restent présents.

**Risque de régression : faible à moyen.** Les seules dates nouvellement
refusées sont les entrées invalides, inexistantes ou non strictement futures.
Le choix existant pour l’heure répétée reste inchangé. La révélation au focus
ajoute temporairement le contrôle au flux mobile pour le rendre utilisable ;
le rendu sans focus et les dimensions sur ordinateur sont vérifiés. Le motif
`expired` reste celui du serveur, sans changement de données persistées.

Le runtime local utilise Bun **1.2.18**, alors que le projet cible **1.3.14**.
Les résultats ci-dessus décrivent le runtime réellement exécuté ; aucune
validation avec Bun 1.3.14 n’est annoncée pour ce lot.

Aucun VoiceOver/TalkBack ni appareil physique n’est utilisé comme preuve.
Chromium vérifie la navigation clavier, les propriétés ARIA et le masquage
visuel, pas l’expérience réelle d’un lecteur d’écran mobile. Le transport
Reverb et les opérations de production ne sont pas exercés dans ce lot.
L’image Docker n’est pas reconstruite : aucune configuration d’infrastructure
ni dépendance n’a changé.

**Avancement :** il reste **3 lots et 9 constats** dans le plan : lot 7
(investigations de concurrence n° 14 et 15), lot 8 (budgets SQL n° 8–10), lot 9
(entretien n° 16, 17, 24 et 25). Les vérifications d’exploitation déjà ouvertes
et l’audit final restent distincts de ces lots.

## Lot 7 — concurrence des écritures et accusés de lecture

### Correctifs préalables et périmètre

Les avertissements non détaillés de la validation du lot 6 ont été réexaminés
sans modification applicative. Avec `PAO_DISABLE=1`, la sortie native de Pest
termine avec un code 0 et sans avertissement pour les 12 parcours de
`MessageReactionAccessibilityTest` et les deux tests d’heures inexistantes.
Le résumé agent fourni par Pao signalait auparavant deux avertissements sans
les décrire. Cela ne démontre pas un défaut de l’application ; les validations
de ce lot utilisent donc la sortie native, sans modifier les dépendances ni
la configuration du projet. Le mécanisme interne exact de ce signalement Pao
n’a pas été corrigé.

Les constats 14 et 15 passent de **suspicions P2** à **bugs reproduits et
corrigés**. Aucun changement de contrat produit ni de rendu n’est introduit.

### 14 — écritures de groupe et révocation

**Preuve avant correction.** Deux connexions MySQL réelles sont synchronisées
juste avant l’insertion, après l’autorisation. Le retrait du rôle membre de
l’auteur ou de l’organisateur, via `SyncManageableUserRoles`, peut alors être
validé sur la seconde connexion, puis le premier appel enregistre quand même
le message ou la réaction. Les huit combinaisons événement/trajet,
message/réaction et auteur/organisateur reproduisent ce défaut. Ce résultat
ne repose pas sur un changement séquentiel de modèle en mémoire.

**Correction.** `SendGroupChatMessage` et `SetGroupChatMessageReaction`
résolvent leur contexte avant leur transaction, puis utilisent
`LockGroupChatForWriting` : comptes de l’auteur et de l’organisateur en ordre
d’identifiant, contexte, chat, puis autorisation et écriture. Les comptes sont
verrouillés en lecture partagée : les révocations qui modifient/verrouillent
ces comptes attendent, mais les verrous de clés étrangères des annonces
d’adhésion restent compatibles. Le contexte est verrouillé avant le chat,
comme dans les transitions d’inscription. Il n’est donc pas nécessaire de
modifier les règles de retrait ou d’ajouter des verrous exclusifs sur tous les
participants. L’Action commune est utilisée par les deux écritures réellement
concernées ; aucune couche générique de transactions n’est ajoutée.

**Usages vérifiés.** Contrôleurs d’envoi et de réaction événement/trajet,
Policies groupe et trajet, annulations, retraits, exclusions, décisions
d’inscription, blocages, changement de rôle, bannissement et préparation de
suppression. Les appelants applicatifs actuels n’enveloppent pas ces écritures
dans une transaction ayant déjà établi un ancien instantané MySQL. Le helper
documente l’exigence de prendre ses verrous avant les lectures d’instantané.
Les contrôles d’accès existants restent côté serveur ; les réactions sur
événement en lecture seule conservent leur règle propre.

**Tests.** `tests/Feature/ChatWriteConcurrencyTest.php` utilise `pcntl_fork`,
deux connexions et des barrières SQL. Si l’écriture a déjà acquis ses verrous,
le retrait concurrent du rôle doit attendre (timeout MySQL 1205 volontaire),
puis réussir après l’écriture ; la tentative suivante est interdite. Si la
transition gagne, l’envoi est refusé et aucun message ordinaire n’est créé.
Ce second ordre couvre rôle, bannissement, annulation, exclusion et
désistement, pour événement et trajet. Les notifications et broadcasts sont
neutralisés dans ces scénarios pour isoler les écritures SQL ; leur livraison
réelle ne constitue pas une preuve fournie par ces tests.

**Risque : moyen**, lié à la contention. Le coût ajouté est borné aux deux
comptes et au contexte du groupe ; aucun test de charge de production n’est
revendiqué. Les scénarios MySQL déterministes ne constituent pas une preuve
d’absence de tout interblocage dans l’ensemble de l’application.

### 15 — borne de lecture privée

**Preuve avant correction.** La seconde connexion insère et valide un message
entre le calcul du maximum et l’UPDATE. Le message nouvellement arrivé est
marqué lu, alors que `MessagesRead.lastReadMessageId` désigne toujours le
message précédent. Le test échoue bien sur le `read_at` indûment renseigné.

**Correction minimale.** `MarkConversationRead` ajoute
`where('id', '<=', $lastReadMessageId)` à l’UPDATE existant. La transaction et
l’accusé conservent la même borne, sans verrou exclusif supplémentaire sur la
conversation. Les usages dans l’ouverture de conversation et l’endpoint de
lecture ont été vérifiés.

**Test.** Le scénario final utilise l’Action réelle `SendMessage` sur la seconde
connexion. Le message initial et sa notification persistante sont acquittés ;
le message arrivé pendant la lecture reste non lu ; l’accusé conserve le
premier ID ; une seconde lecture traite ensuite le nouveau message. L’envoi
asynchrone des nouvelles notifications est neutralisé dans ce test concurrent.
Les tests existants de notifications et de conversation complètent ce cas.

**Risque : faible.** Un message concurrent reste désormais non lu jusqu’à la
lecture suivante ; aucun historique n’est supprimé et aucun schéma ne change.

### Validation et avancement

Les neuf reproductions initiales échouent avant correction pour les raisons
attendues (`/tmp/ponytail-lot7-red.log`), puis passent après correction.
L’extension couvre 19 scénarios de concurrence. PHPStan passe sans erreur ;
son lancement initial dans le sandbox ne pouvait pas ouvrir son socket local
(`EPERM`), puis la commande autorisée hors sandbox réussit.

La validation complète `PAO_DISABLE=1 COMPOSER_PROCESS_TIMEOUT=0 composer ci:check`
réussit (code de sortie 0) : lint et format PHP/frontend, typage TypeScript,
PHPStan, génération Wayfinder, **151 tests frontend**, build Vite et suite Pest
complète, navigateur Chromium compris : **2 013 réussis, 6 ignorés,
34 476 assertions**, en 715,40 secondes. Le journal est conservé dans
`/tmp/ponytail-lot7-ci.log`.

Les six exclusions concernent les intégrations réelles Reverb (4), Garage (1)
et le runtime Docker de déploiement (1), non activées dans cet environnement.
Le build signale encore la dépréciation `inlineDynamicImports` et des logos
résolus à l’exécution ; ces avertissements ne font pas échouer la commande.
Le runtime Bun local est 1.2.18, contre 1.3.14 ciblé par le projet. Aucun build
d’image Docker, test de charge en production ou essai sur appareil physique
n’est revendiqué pour ce lot.

**Avancement :** après ce lot, il reste **2 lots et 7 constats** : lot 8,
budgets SQL (8–10), puis lot 9, entretien (16, 17, 24 et 25). Les vérifications
d’exploitation déjà ouvertes et l’audit final restent distincts de ces lots.

## Lot 8 — budgets SQL et volumes chargés (constats 8, 9 et 10)

### 8 — découverte : sélectionner avant d’hydrater

**Reproduction.** Un jeu de 205 candidats à égalité, avec un départage injecté
favorisant les derniers identifiants, renvoyait les 205 profils depuis le
service. Le test exige désormais cinq résultats, dont les candidats situés
après le premier lot SQL, et au plus six modèles `Profile` hydratés, profil du
lecteur compris. Il échoue avant correction puis passe après celle-ci.

**Correction.** `app/Services/DiscoveryService.php` applique en SQL les critères
d’admissibilité, le nombre d’intérêts actifs communs, la priorité admin, la
position dans la file reçue et le bonus de fréquence. Il parcourt des lignes
scalaires par lots de 200, conserve cinq candidats et hydrate uniquement leurs
relations. Le départage reste appliqué à toute la tranche d’ex æquo à la
cinquième position : un `LIMIT 5` prématuré aurait modifié le classement.
`app/Http/Controllers/DiscoveryController.php` transmet la file validée au
service ; le second classement et la matérialisation de l’audience dans le
contrôleur disparaissent.

`app/Services/RandomDiscoveryTieBreaker.php` dérive un rang stable par profil
d’une graine aléatoire propre à l’instance. Il n’accumule plus un tableau de
rangs pour toute l’audience ; un identifiant départage une éventuelle collision.
Ce rang est un ordre de présentation, pas un mécanisme cryptographique de
sécurité. Aucun consommateur applicatif autre que le contrôleur ne dépendait
du retour exhaustif du service ; les usages et les tests ont été recherchés.

**Garanties vérifiées.** Priorité admin, conservation des cartes admissibles
déjà affichées, ordre intérêts/fréquence, intérêts inactifs, préférences d’âge,
blocages, swipes et absence de doublons. Les tests existants de découverte,
file et préférences complètent le nouveau scénario de plus de 200 ex æquo.
Le test unitaire du départage vérifie sa stabilité après le classement de
205 autres profils.

**Risque : moyen**, concentré sur le classement. **Limite explicite :** le
serveur SQL doit toujours évaluer les candidats admissibles. Une grande
tranche d’ex æquo impose plusieurs pages scalaires et des tris SQL ; le nombre
de requêtes n’est donc pas constant pour toute taille d’audience. Ce lot borne
l’hydratation et la mémoire de sélection, sans démontrer un gain de latence
sur la base de production ni mesurer la mémoire totale du processus.

### 9 — calendrier : calcul mensuel et aperçus bornés

**Reproduction.** Sur un mois futur de 31 jours peuplé de cinq visiteurs ayant
chacun deux visites superposées, la reproduction initiale relève 158 requêtes.
Le test final précharge le profil et les rôles du lecteur pour isoler le
calcul et impose un budget d’au plus dix requêtes. Il vérifie les 31 comptes
distincts, les trois premiers noms dans le bon ordre et l’hydratation de
seulement trois profils de visiteurs dans ce jeu de données. Le chiffre
initial, obtenu sans ce préchargement, n’est pas un benchmark strictement
identique à celui du budget final.

**Correction.** `app/Data/VisitWorkspaceData.php` construit les dates du mois
comme une petite table dérivée de constantes, puis calcule les comptes et
les trois premiers visiteurs par jour avec les fonctions de fenêtre MySQL 8.4.
Les sous-requêtes de visibilité et les filtres existants sont réutilisés. Seuls
les identifiants des aperçus sont ensuite hydratés, au maximum 93 visiteurs
distincts pour 31 jours ; toutes les visites de l’audience ne sont pas
chargées en PHP. Le calcul conserve le comptage de personnes distinctes malgré
des visites superposées. Le détail d’une journée et les profils conservent
leur chemin de consultation dédié et les mêmes filtres partagés.

**Garanties vérifiées.** Tests du calendrier et des visites : période passée,
comptes distincts, recherche littérale, audience des contacts, confidentialité,
blocages et aperçus. Les compteurs et le classement restent calculés en SQL,
sans cache de permissions.

**Risque : moyen**, lié à la réécriture SQL et aux filtres de visibilité.
Les requêtes utilisent les fonctions de fenêtre déjà disponibles dans la
stack MySQL cible. Aucun index n’est ajouté sans mesure ; un `EXPLAIN` et un
profilage sur une volumétrie représentative restent à effectuer avant de
conclure sur la latence ou le plan optimal en production.

### 10 — groupes et historiques partenaires

**Groupes : preuves et correction.** Le rattrapage de 20 messages effectuait
23 requêtes ; il respecte désormais un budget d’au plus quatre, avec les
mêmes compteurs et indicateurs de réaction. Le passage d’un à dix groupes
ajoutait 60 requêtes dans la reproduction ; le test impose désormais une
croissance d’au plus quatre. L’inspection des usages de `unreadCount` a aussi
révélé les listes d’événements : neuf événements supplémentaires ajoutaient
27 requêtes. Leur test impose maintenant une croissance d’au plus deux.

- `app/Models/GroupChatMessage.php` fournit le scope de réaction du lecteur,
  utilisé par `app/Data/GroupChatData.php`, `app/Data/EventWorkspaceData.php`
  et `app/Data/TripWorkspaceData.php` pour leurs collections de messages.
- `app/Models/GroupChat.php` fournit les scopes de visibilité et de comptage
  des non-lus. `app/Policies/GroupChatPolicy.php` et
  `app/Http/Controllers/ConversationIndexController.php` partagent ainsi la
  sélection des groupes autorisés ; le contrôleur n’effectue plus une
  autorisation et deux lectures de compteur par ligne.
- Les listes d’événements préchargent le chat et son compteur pour les
  résumés. Le DTO de groupe réutilise le contexte préchargé et calcule une
  seule raison de lecture seule. Les appels isolés gardent leurs chemins de
  secours ; les mutations continuent de lire le contexte courant et de
  vérifier leurs droits côté serveur.

Les contrôles du lecteur, de l’organisateur et de l’inscription sont conservés,
y compris le scope de visibilité des trajets. Les tests d’éligibilité et les
19 scénarios MySQL de concurrence du lot 7 ont été réexécutés avec succès
après cette modification. Aucun cache durable d’autorisation n’est introduit.

**Partenaires : preuves et correction.** La reproduction d’une à dix annonces
du même profil ajoutait 17 requêtes de statistiques. Le nouveau test borne
cette croissance à deux. Trois scénarios de 21 annonces démontraient aussi
que chaque historique renvoyait toute la collection ; ils exigent maintenant
20 lignes sur la première page et la dernière ligne sur la suivante.

Les contrôleurs `Admin/PartnerStatisticsController.php`,
`Partner/StatisticsController.php` et `Partner/AnnouncementController.php`
utilisent `simplePaginate(20)` et conservent les restrictions d’appartenance.
`app/Support/PartnerAnnouncementCooldown.php` fournit une projection de
lecture : réglage lu une fois et deux derniers départs d’envoi par profil.
Deux suffisent pour exclure l’annonce courante sans ignorer le précédent
départ, même situé sur une autre page. La méthode transactionnelle et son
ordre de verrouillage restent inchangés.

Les trois pages Vue correspondantes utilisent
`resources/js/components/partners/AnnouncementPagination.vue`, petit composant
propre à ces trois historiques qui réutilise `Button` et le lien Inertia.
Il ne crée pas de primitive générique. Les liens précédent/suivant et leur
libellé accessible sont traduits en français et anglais ; le type est déclaré
dans `resources/js/types/partner.ts`. La liste des annonces garde sa forme de
tableau ; une prop de pagination distincte contient les liens.

**Tests et risque : moyen.** `tests/Feature/SqlReadBudgetsTest.php` couvre les
budgets de groupes et d’événements ;
`tests/Feature/Partner/PartnerStatisticsTest.php` couvre le budget, les trois
paginators, l’isolement entre partenaires, l’exclusion de l’annonce courante,
l’absence d’envoi et la frontière exacte du délai.
`tests/Browser/PartnerTest.php` ajoute les trois parcours suivant/précédent
sur un écran de 320 px, avec contrôle de débordement, erreurs JavaScript et
accessibilité. Le risque principal reste une divergence d’autorisation ou de
compteur lors d’une évolution future : le scope partagé et ces tests doivent
être conservés ensemble. La pagination change la présentation des historiques
longs, tout en gardant chaque annonce accessible.

### Validation et avancement du lot 8

Les reproductions rouges sont conservées dans
`/tmp/ponytail-lot8-red.log`, `/tmp/ponytail-lot8-groups-red.log`,
`/tmp/ponytail-lot8-discovery-red.log`,
`/tmp/ponytail-lot8-partners-red.log` et
`/tmp/ponytail-lot8-events-red.log`. Après correction, les suites ciblées
passent : 26 tests calendrier/groupes, 49 tests d’accès et de concurrence,
51 tests de découverte et 11 tests partenaires. Ces suites se recoupent ;
leurs nombres ne s’additionnent pas en un total de scénarios distincts.

Le premier lancement complet de
`PAO_DISABLE=1 COMPOSER_PROCESS_TIMEOUT=0 composer ci:check` termine tous les
tests : **2 027 réussis, 6 ignorés, 34 717 assertions**, en 713,09 secondes,
mais retourne le **code 1**, sans test rouge ni détail d’erreur affiché. Il
ne constitue donc pas une validation complète réussie. Le journal est
`/tmp/ponytail-lot8-ci.log`. Une première tentative, antérieure, avait été
arrêtée par l’ordre des imports de trois pages Vue, corrigé depuis.

Le diagnostic ciblé reproduit une sortie 1 avec 31 tests verts, puis les
relances directes Pest et Artisan des mêmes 31 tests retournent 0, sans
changement de code. Le journal d’événements PHPUnit
`/tmp/ponytail-lot8-events.log` confirme `Shell Exit Code: 0` sur la dernière
relance. La cause de cette anomalie intermittente de sortie n’est pas établie ;
aucun contournement du mode strict PHPUnit n’est ajouté.

La seconde validation complète **réussit avec le code de sortie 0**, sans
modification applicative entre les deux passages : lint et format PHP/frontend,
PHPStan, TypeScript, génération Wayfinder, **151 tests frontend**, build Vite,
puis **2 027 tests Pest réussis, 6 ignorés, 34 717 assertions**, navigateur
Chromium compris, en 707,11 secondes pour la suite Pest. Le journal final est
`/tmp/ponytail-lot8-ci-retry.log`. Cela confirme la validation du code courant,
sans expliquer rétrospectivement les sorties intermittentes précédentes.

Les six exclusions concernent quatre intégrations réelles Reverb, une
intégration Garage et le runtime Docker de déploiement, non activés pour ces
tests. Les avertissements Vite déjà présents (`inlineDynamicImports` et
logos résolus à l’exécution) subsistent. Bun local est 1.2.18, contre 1.3.14
ciblé par le projet ; PHP CLI est 8.4.1. Aucun build d’image Docker, essai sur
appareil physique, `EXPLAIN` de production ou test de charge n’a été effectué
pour ce lot. Les budgets de requêtes et d’hydratation des fixtures ne prouvent
pas à eux seuls une amélioration de la latence en production.

**Avancement après validation :** il reste **1 lot et 4 constats** :
lot 9, entretien (16, 17, 24 et 25). Les vérifications d’exploitation et
l’audit final restent distincts.

## Lot 9 — entretien ciblé (constats 16, 17, 24 et 25)

### Corrections réalisées

- **N° 16 :** suppression de `groupChatUnreadIncrement` et de ses seules
  assertions isolées. Une nouvelle recherche, fichiers cachés inclus hors
  dépendances, stockage et build, confirme l'absence d'appel applicatif. Les
  références du plan historique restent comme trace, sans être présentées
  comme une intégration actuelle. `mergeGroupChatMessages` et
  `resolveGroupChatDraft`, réellement appelés, sont conservés. Le calcul
  effectif des non-lus reste couvert par `GroupConversationTest`, notamment
  la liste, la lecture et les compteurs, plutôt que par ce helper inutilisé.
- **N° 17 :** le résumé production du README décrit les quatre services
  applicatifs et les ressources MySQL, Redis et Garage indépendantes.
  `docs/operations.md` distingue une nouvelle installation de la migration
  d'un ancien MinIO et d'une livraison sur une base existante. Le premier
  déploiement précise que la Release PR publie une image et son manifeste,
  sans livrer la production. La liste des variables obligatoires inclut
  aussi les trois variables VAPID exigées par le Compose. Vérification croisée
  avec `compose.production.yaml`, `.github/workflows/release-please.yml`,
  le refus de livraison de `deploy-release.sh` et la procédure opérateur
  contrôlée. Aucun état Coolify réel ni déploiement n'est inféré de ces lectures.
- **N° 24 :** retrait du rechargement fondé sur l'URL, doublon de la branche
  `Conversations/Index`. Un événement d'accès demande un seul rechargement de
  `groups`, y compris avec une query string. Le test du composable réel
  vérifie aussi le groupe concerné, un autre groupe, le nettoyage des deux
  brouillons après révocation et la redirection, le panneau événement et
  une page sans rapport. Il ne prétend pas mesurer le nombre de requêtes
  réellement terminées par Inertia ou le coût SQL d'une diffusion réelle.
- **N° 25 :** les trois erreurs HTTP 2FA utilisent les catalogues FR/EN et
  continuent d'effacer les secrets/codes devenus inutilisables.
  `PasskeyRegister` réutilise `localizePasskeyError`, déjà consommé par la
  vérification. Annulation, absence de support, appareil déjà enregistré et
  domaine invalide ont un libellé traduit ; les autres erreurs ont un message
  traduit invitant à réessayer. Le format du nom proposé est traduit, sans
  réécrire les noms saisis. Aucun client HTTP ou système général d'erreurs
  supplémentaire n'est introduit.

### Choix et risques

La dépendance `@laravel/passkeys` convertit aussi bien les erreurs HTTP que
les erreurs navigateur non classées en `PasskeyError(message)`, sans statut
ni origine conservés. Aucun catalogue applicatif de messages serveur passkey
localisés à préserver n'a été trouvé dans les parcours examinés. En conséquence,
le fallback brut n'est plus affiché : les détails techniques et les messages
serveur non classés partagent le libellé générique traduit. Cela évite leur
exposition mais réduit leur précision. Si un message serveur métier particulier
devient nécessaire, lui donner un contrat identifié et traduit avant de
l'afficher ; ne pas détecter sa langue heuristiquement. L'authentification,
les validations serveur et les permissions ne sont pas modifiées.

Les deux risques principaux de ce lot sont une mauvaise branche de navigation
après révocation et une erreur de sécurité masquée ou affichée dans la mauvaise
langue. Les tests ciblent ces sorties observables. Les noms personnalisés
restent modifiables et conservés après échec ; aucune clé existante n'est renommée.
La documentation reste une description du dépôt, sans preuve d'activation serveur.

### Reproductions et validations

Les tests ont reproduit avant correction les deux appels `reload` pour un
seul événement, le fallback technique brut et les erreurs 2FA anglaises.
Journaux : `/tmp/ponytail-lot9-red.log` et
`/tmp/ponytail-lot9-2fa-red.log`. Le test 2FA utilise les vrais catalogues PHP
et le composable, avec une panne à la frontière HTTP ; ses attentes FR/EN sont
explicites. Les six branches du helper passkey sont exercées sans émuler le
fonctionnement de WebAuthn lui-même.

Le parcours navigateur a d'abord échoué sur le nom anglais (« Chrome sur Mac »)
et les messages d'échec, puis les quatre variantes FR/EN ont passé après
rebuild Vite : `/tmp/ponytail-lot9-passkey-red.log` et
`/tmp/ponytail-lot9-security-green.log`. Le premier essai de la sonde navigateur
avait échoué dans son installation JavaScript (fonction retournée évaluée par
Playwright) ; il a été corrigé avant d'utiliser la reproduction comme preuve.
Les échecs navigateur sont simulés à `navigator.credentials.create` ; options,
page, composant et requêtes Laravel sont réels. Ce n'est pas la preuve d'une
cérémonie WebAuthn avec un authentificateur physique.

La commande ciblée affiche **9 tests réussis, 57 assertions**, mais retourne
le **code 1**, sans test rouge, comme l'anomalie intermittente relevée au lot 8.
Elle n'est donc pas comptée comme commande de validation réussie. Le parcours
a ensuite été renforcé pour vérifier aussi la conservation d'un nom personnalisé
après échec. **158 tests frontend** passent, contre 151 avant ce lot : retrait
d'un test du helper mort et ajout de huit tests utiles.

La validation complète **`PAO_DISABLE=1 COMPOSER_PROCESS_TIMEOUT=0 composer ci:check`
réussit avec le code de sortie 0** : ESLint, Prettier, TypeScript, Pint,
PHPStan, génération Wayfinder, **158 tests frontend**, build Vite/PWA, garde
MySQL puis **2 031 tests Pest réussis, 6 ignorés, 34 909 assertions**,
Chromium compris, en **715,64 secondes** pour Pest. Les quatre variantes du
nouveau parcours passkey passent dans cette suite avec le nom personnalisé.
Journal : `/tmp/ponytail-lot9-ci.log`. Par rapport au lot 8 : quatre tests Pest
supplémentaires et 192 assertions supplémentaires, sans nouvel échec.
Cette sortie 0 ne résout pas la cause de l'anomalie de sortie ciblée décrite
ci-dessus ; aucun contournement PHPUnit n'est ajouté.

Les six exclusions restent quatre parcours Reverb conditionnels
(`REALTIME_BROWSER_TESTS` non activé), Garage (`GARAGE_INTEGRATION` et bucket
préparé absents) et l'image runtime (`DLP_RUNTIME_TEST_IMAGE` absent).
Les avertissements Vite déjà documentés sur les logos résolus à l'exécution
et `inlineDynamicImports` restent présents. Aucun build Docker, fournisseur
externe ou essai sur appareil physique n'est ajouté à ce lot.

**Avancement : les neuf lots prévus sont implémentés ; il ne reste aucun lot
de corrections dans ce plan.** Les vérifications d'exploitation déjà ouvertes,
la revue du dernier lot et l'audit final après refactoring restent distincts.
Ce bilan ne certifie ni un déploiement ni la préparation finale de la branche
pour une pull request.

## Validation complémentaire après la revue du lot 9 — Docker runtime

Les neuf lots de corrections sont terminés. Cette étape complète les preuves
d'infrastructure ; elle ne constitue pas un dixième lot ni l'audit final de la
branche. Aucun code applicatif, test ou configuration n'est modifié.

- Le moteur Docker local est disponible en version **28.1.1**. La première
  lecture du socket était interdite dans le sandbox ; une exécution autorisée
  a confirmé sa disponibilité. Les conteneurs existants appartiennent notamment
  à l'environnement `issue305`, dont l'image ne prouve pas l'état de cette branche.
- **`docker build --target runtime --tag dlp-friends:ci .` : code 0.**
  Journal : `/tmp/ponytail-final-docker-build.log`. Cette construction locale
  valide la cible runtime, pas une publication GHCR ni une livraison Coolify.
- **`PAO_DISABLE=1 DLP_RUNTIME_TEST_IMAGE=dlp-friends:ci php artisan test
tests/Feature/Infrastructure/ReleaseRuntimeIntegrationTest.php` : code 0,
  1 test, 1 assertion Pest, 11,40 secondes.** Le script appelé vérifie aussi
  ses assertions Python : séparation des espaces de maintenance Redis,
  refus de l'ancien espace, maintenance persistante et fin effective d'une
  requête HTTP et de sa fonction de terminaison avant la frontière d'export.
  Journal : `/tmp/ponytail-final-runtime.log`.
- Le test a créé ses propres conteneurs et réseau Docker temporaires, puis
  les a supprimés. La recherche des conteneurs `dlp-release-test-*` après
  exécution ne renvoie aucun résultat. Les conteneurs préexistants sont conservés.

**Limites :** l'identité MySQL et les attestations d'image sont simulées dans
ce test runtime existant ; il ne prouve pas la procédure opérateur entière.
Les quatre tests navigateur Reverb et le test Garage restent non exécutés :
aucun environnement Reverb de cette branche avec les assets Vite correspondants
n'a été préparé ici, et aucun bucket Garage de test avec sa configuration dédiée
n'est disponible. Le Reverb `issue305` n'est pas utilisé comme substitut.
Le test runtime ignoré lors de la suite complète du lot 9 dispose désormais
d'une preuve séparée réussie ; le résultat historique de cette suite reste
2 031 réussis et 6 ignorés. Il ne faut pas le réécrire comme une suite complète
avec seulement 5 exclusions.

L'audit final reste à effectuer : rapprochement de chaque constat avec son
correctif, revue transversale de la branche et bilan des limites avant PR.

## Refactoring structurel — fil de groupe événement/trajet

Ce lot applique une simplification du code applicatif après les neuf lots de
corrections. Il ne constitue pas l'audit final de la branche.

- `EventWorkspaceData::conversation()` et `TripWorkspaceData::conversation()`
  contenaient les mêmes propriétés Inertia du fil : métadonnées du chat,
  rattrapage optionnel et historique paginé avec réactions du lecteur.
- Ces propriétés sont maintenant produites une seule fois par
  `GroupChatData::conversation()`, dans la classe déjà partagée par les deux
  modules. Les deux imports devenus inutiles sont supprimés. Aucune nouvelle
  classe ni dépendance ; bilan applicatif du lot : 24 lignes ajoutées,
  30 supprimées, soit 6 lignes de moins.
- Les autorisations Gate restent dans chacun des deux espaces. Les données
  événement/trajet, panneaux et destinations de navigation restent locales.
  Le chargement différé, le rattrapage optionnel, l'ordre chronologique et
  la pagination de dix messages conservent leur comportement.
- Un test de caractérisation paramétré sur les deux modules vérifie les
  réactions du lecteur, l'isolation des messages et les pages suivantes ou
  vides. Il passe avant puis après le déplacement du code.

### Validations de ce lot

- `composer lint:check` : code 0.
- `composer analyse` : code 0, aucune erreur PHPStan.
- Neuf fichiers fonctionnels ciblés : code 0, **54 tests réussis,
  740 assertions** ; historique, autorisations, panneaux, réactions et budgets
  SQL. Journal : `/tmp/ponytail-refactor-group-feature.log`.
- Trois fichiers Pest Browser : code 0, **8 tests réussis, 84 assertions** ;
  continuité du fil, conservation du brouillon, réactions, lecture seule et
  perte d'accès après exclusion.
- `git diff --check` : code 0.

La première commande regroupant ces douze fichiers affichait 62 succès mais
renvoyait le code 1, sans diagnostic d'échec dans sa sortie. Les deux groupes
ont donc été rejoués séparément et terminent chacun avec le code 0 ; la cause
du premier code de sortie reste inexpliquée. Les premières tentatives de Pint
et PHPStan étaient bloquées par l'ouverture d'un port local dans le sandbox ;
leurs nouvelles exécutions autorisées réussissent.

**Limites :** la CI complète, les contrôles frontend et l'image Docker n'ont
pas été relancés pour ce lot exclusivement PHP. Le risque propre à cette
mutualisation est de toucher les deux fils lors d'un futur changement ; les
tests communs couvrent désormais explicitement ces deux contextes.

## Réaudit Ponytail et refactoring demandé le 24 octobre 2026

**What this repo does:** DLP Friends relie des fans majeurs pour des rencontres
amicales, messages privés, événements, visites et trajets. L'examen suppose
plusieurs membres et workers concurrents, avec des historiques qui grandissent ;
aucune mesure de charge de production n'a été fournie.

Cette section porte sur l'état de travail après les neuf lots précédents. Elle
ne présente pas leurs modifications comme de nouvelles régressions. La demande
explicite autorise ici le refactoring après le diagnostic du skill d'audit.

### Should fix — constats vérifiés avant les changements

1. **Filtres de conversations répétés (P2).** Dans
   `app/Models/Conversation.php`, `withVisibleParticipant` recopie les comptes
   actifs et les blocages de `withUnblockedParticipant`. La liste et le
   récapitulatif ajoutent la visibilité du pair ; les notifications et la présence
   doivent conserver les profils masqués. Réutiliser le scope d'accès commun et
   garder la visibilité comme condition supplémentaire. Risque : inverser le
   participant low/high ou filtrer les notifications d'un profil masqué. Effort
   faible ; vérifier les deux positions, blocages et notifications/récapitulatifs.

2. **Fusion et réactions des messages répétées (P2).**
   `useConversationMessages.ts` reproduit l'algorithme de
   `groupChatState.ts`, déjà utilisé par le fil privé ; `GroupChat.vue`
   reproduit `applyMessageReaction`. Les mises à jour absolues doivent rester
   idempotentes et conserver les champs propres aux deux types de messages.
   Déplacer la fusion dans `conversationState.ts` et réutiliser les traitements
   existants, sans nouveau composable. Effort faible ; risque moyen sur le
   rattrapage temps réel, les réactions et l'ordre des messages.

3. **Normalisation et couleurs d'avatars dupliquées (P2).**
   `StoreAvatarRequest.php` et `UpdateAvatarRequest.php` recopient la préparation
   du nom/couleurs et les règles de couleurs. Faire hériter la mise à jour de la
   requête existante et remplacer seulement les règles différentes. Conserver
   son autorisation propre, l'unicité qui ignore le modèle lié à la route et
   l'image facultative. Effort faible ; risque faible couvert par le catalogue
   et ses tests d'autorisations.

4. **Helper de capacité sans consommateur (P3).**
   `resources/js/lib/eventState.ts::capacityLabel` n'est appelé que par son
   propre test. Recherche dans app/resources/tests/docs : aucun usage produit.
   Supprimer l'export et l'assertion dédiée, garder le test du format horaire.
   Effort et risque faibles ; compilation et recherche d'usages obligatoires.

5. **Comptages de places répétés dans les listes de trajets (P2).**
   `Trip::remainingPlaces` exécute COUNT à chaque appel ; la sérialisation et
   `TripPolicy::request` l'appellent pour chaque trajet. Précharger le nombre
   d'inscriptions acceptées comme le fait déjà Event, et le réutiliser sur les
   modèles de lecture. Les actions doivent continuer à relire un modèle verrouillé
   sans agrégat préchargé. Effort faible ; risque moyen sur les dernières places.
   Test : capacités selon le transport/statut et absence de COUNT séparé par
   trajet ; rejouer les tests de concurrence. Ceci ne résout pas les autres
   requêtes d'autorisations par trajet, qui demandent un examen séparé avant
   toute simplification susceptible de modifier les accès privés.

6. **Recherche quadratique pendant le rattrapage (P2).**
   `Conversations/Show.vue::preserveNewerState` et
   `groups/GroupChat.vue::preserveNewerReaction` parcourent tout le fil avec
   `find` pour chaque message reçu. Avec N messages chargés et M reçus, cela
   fait jusqu'à N×M comparaisons par rechargement. Construire un index Map
   une seule fois à réception de la réponse, puis effectuer M accès directs.
   Conserver l'instantané antérieur à la requête : il protège les réactions
   reçues entre-temps. Effort faible ; risque moyen sur les réponses lentes,
   couvert par les tests navigateur de continuité.

7. **Identité de participant trajet recopiée dans le contrôleur (P2).**
   `TripParticipantController::member` et `TripWorkspaceData::member` produisent
   exactement le même tableau, avec les mêmes URL et valeurs nulles. Le
   contrôleur reçoit déjà TripWorkspaceData : utiliser sa méthode et supprimer
   la copie privée. Aucune nouvelle dépendance ni conversion des autres
   présentations d'identité, dont les contrats diffèrent. Effort et risque faibles ;
   vérifier les panneaux participants et leur confidentialité.

### Couverture du passage statique

| Domaine | Couverture actuelle | Limite |
| --- | --- | --- |
| Entrées, dépendances, routes et déploiement | Cartographie et configuration lues | Pas de déploiement réel ni de mesure de production |
| Authentification, sessions, blocages, suppression | Chemins sensibles relus ciblés | Les preuves des lots antérieurs restent distinctes |
| Conversations privées et groupes | Lecture des scopes, policies, actions, données et consommateurs Vue | Reverb réel à rejouer avec un environnement dédié |
| Événements et trajets | Requêtes, demandes, décisions, transactions et espaces de lecture ciblés | Liste trajets : autorisations par élément encore à approfondir |
| Jobs, récapitulatifs, push | Réservations, idempotence et contrôles d'accès relus ciblés | Aucun appel réel à Resend/push |
| Catalogue, composables et helpers frontend | Usages croisés des doublons examinés | Tous les composants et styles n'ont pas été relus ligne par ligne |
| Tests et infrastructure | Scripts et tests pertinents examinés, tests ciblés et build Docker exécutés | Bilan de validation ci-dessous ; intégrations externes séparées |

**Verdict :** traiter les sept constats ci-dessus ; aucun nouveau défaut critique
n'est démontré par ce passage. Ce constat ne garantit pas l'absence de défauts
ailleurs. **Not checked :** charge réelle, services externes et relecture
exhaustive de chaque fichier ; ne pas annoncer un audit intégral approfondi sur
la seule base de cette cartographie.

### Refactorings appliqués et preuves du présent passage

| Constat | État | Preuve / protection |
| --- | --- | --- |
| 1 | Corrigé | `Conversation.php:39` compose les deux scopes ; matrice des positions low/high, pair actif/masqué/inactif et blocages dans les deux sens |
| 2 | Corrigé | `conversationState.ts:10` porte la fusion, `:35` les réactions ; consommateurs privés/groupes réunis ; conservation des champs de groupe et immutabilité testées |
| 3 | Corrigé | UpdateAvatarRequest réutilise la préparation et les règles communes ; autorisation update distincte et image facultative conservées |
| 4 | Supprimé | Recherche globale, y compris fichiers cachés hors dépendances et artefacts : il ne reste que la mention historique dans ce rapport |
| 5 | Corrigé pour les comptages | Deux tests rouges avant correction : 3 COUNT séparés au lieu de 0 pour 3 trajets, sur `/trips` et `/trips/mine` ; verts après préchargement |
| 6 | Corrigé | Index construit dans `onBeforeUpdate`, donc après réception et avant fusion ; comparaison des références préserve les réactions arrivées pendant la requête |
| 7 | Corrigé | TripParticipantController utilise `$data->member()` ; copie privée supprimée et Gates inchangées |

Les décisions de participation continuent à recharger Trip sous verrou avant
validation de capacité. Aucun agrégat de liste n'est transféré dans ces actions.
Le test réel à deux connexions MySQL sur la dernière place passe dans le groupe
ciblé de **116 tests / 753 assertions**. Le test de budget SQL a d'abord utilisé
un motif ignorant les guillemets de l'alias MySQL : il passait à tort. Le motif a
été corrigé avant toute modification applicative, puis les deux échecs attendus
ont été observés ; journal `/tmp/ponytail-current-red.log`.

Pas de nouvelle classe, dépendance, composant ou composable. Les helpers existants
portent les traitements réellement communs. Les demandes événement/trajet et
les validations d'intérêts ne sont pas fusionnées mécaniquement : dates,
capacités, autorisations et messages d'erreur présentent des différences.
Les différentes représentations d'avatars ne sont pas toutes interchangeables
(URL relative/absolue, visibilité et champs supplémentaires).

Les tests ajoutés couvrent dix combinaisons de confidentialité des scopes, deux
budgets SQL et la conservation des champs lors d'une réaction de groupe.
L'historique des validations des lots précédents reste conservé plus haut ;
les sorties du présent passage sont séparées sous `/tmp/ponytail-current-*`.

### Validation et limites du réaudit

Les modifications applicatives de ce passage représentent **115 lignes ajoutées,
149 supprimées (−34 net)** par rapport aux copies prises avant ce réaudit, hors
rapport et tests. Ce chiffre ne mélange pas les neuf lots déjà présents sur la
branche. L'objectif mesurable est la suppression des traitements répétés et des
requêtes superflues ; aucune promesse de temps de réponse en production n'est
faite sans mesure.

Les dix nouveaux cas de confidentialité ont initialement révélé une erreur dans
leurs données : `update(['status' => ...])` ne modifie pas ce champ protégé contre
l'assignation de masse. Les deux cas « inactif » laissaient donc un compte actif.
La préparation utilise maintenant `forceFill()->save()`. Les tests ciblés
ConversationIndex + TripWorkspace passent ensuite : **23 tests / 258 assertions**.
Cette erreur de fixture n'est pas présentée comme une régression applicative.

La première exécution complète s'arrêtait sur l'ordre des imports d'un test
frontend modifié ; cet ordre a été corrigé. L'exécution interrompue après les
échecs des fixtures reste conservée dans
`/tmp/ponytail-current-ci-interrupted.log` et ne constitue pas une validation
réussie. Une exécution complète neuve a été lancée après ces corrections.

Résultats de la nouvelle exécution sur les modifications finales :

| Validation | Résultat récent |
| --- | --- |
| `PAO_DISABLE=1 COMPOSER_PROCESS_TIMEOUT=0 composer ci:check` | Réussi, code 0 ; 2 045 tests Pest passent, 6 ignorés, 35 043 assertions, 717,72 s |
| ESLint, Prettier, TypeScript | Réussis dans cette exécution via `lint:check`, `format:check`, `types:check` |
| Pint et PHPStan/Larastan | Réussis dans cette exécution ; `composer analyse` également rejoué séparément sans erreur |
| Wayfinder et build Vite | Réussis dans cette exécution |
| Tests unitaires frontend Bun | 159 réussis, 278 assertions, 34 fichiers |
| Tests navigateur Pest/Playwright | Exécutés dans Chromium au sein des 2 045 tests réussis ; les quatre cas Reverb conditionnels restent exclus |
| `docker build --target runtime --tag dlp-friends:ci .` | Réussi, code 0, avec les dernières modifications applicatives |
| `git diff --check` | Réussi |

La précédente validation complète enregistrée plus haut comptait 2 031 tests
réussis et 6 ignorés : cette exécution conserve les 6 exclusions et ajoute 14
cas Pest réussis. Il s'agit d'une comparaison de résultats, pas d'une mesure de
performance comparable entre machines ou charges.

Les six exclusions de la suite complète sont explicites : quatre cas dans
`tests/Browser/ConversationTest.php` exigent `REALTIME_BROWSER_TESTS=true` et un
serveur Reverb dédié ; le test Garage exige `GARAGE_INTEGRATION=1` et un bucket
préparé ; le test ReleaseRuntime exige `DLP_RUNTIME_TEST_IMAGE`. Ce dernier est
rejoué séparément sur l'image locale construite. Aucun test de charge de
production, envoi Resend/push réel ou déploiement Coolify n'a été effectué.
Le build signale aussi la dépréciation de l'option `inlineDynamicImports` ; il
réussit et ce passage ne modifie pas les dépendances pour supprimer cet avertissement.

Test Docker complémentaire :
`DLP_RUNTIME_TEST_IMAGE=dlp-friends:ci PAO_DISABLE=1 php artisan test tests/Feature/Infrastructure/ReleaseRuntimeIntegrationTest.php`
**réussi, code 0 : 1 test / 1 assertion, 7,67 s**. Le scénario utilise deux
conteneurs applicatifs et Redis sur un réseau Docker privé, vérifie les espaces
de maintenance et la fin des écritures HTTP en cours. L'identité MySQL y est
synthétique : ce test ne prouve pas une migration ou un déploiement réel.
Il reste donc **cinq scénarios conditionnels non exécutés** (Reverb ×4, Garage ×1).

Les sept constats de ce réaudit ont reçu une correction. Les contrôles disponibles
passent ; la branche reste soumise à la revue globale de ses nombreux changements
antérieurs et aux vérifications externes mentionnées. Les requêtes d'autorisation
par trajet et les domaines non relus intégralement figurent dans la couverture,
sans être déclarés résolus par ces sept refactorings.

## Réaudit frontend et mutualisations du 24 octobre 2026

Ce passage répond à la demande de refactoring effectif, notamment des lignes de
membres et des profils ouverts dans un panneau. Il complète les lots précédents ;
il ne présente pas leur diff cumulé comme le résultat de ce seul passage.
Les copies avant intervention sont conservées sous
`/tmp/ponytail-frontend-before/`. Aucun changement de règle métier, d'autorisation,
de route ou de charge utile serveur n'est recherché.

### Duplications vérifiées et corrections appliquées

Les priorités ci-dessous concernent la maintenabilité (P2), sans assimiler une
répétition de présentation à une faille ou une régression avérée.

| Constat et preuve avant intervention | Correction et fichiers concernés | Risque de régression et vérification nécessaire |
| --- | --- | --- |
| Les boutons des visiteurs dans `VisitDayDetail.vue` et `VisitWorkspace.vue` répétaient le même avatar, nom tronqué, badge de match et bouton ; seule la graisse du nom différait | `components/visits/VisitMemberButton.vue` partagé entre aperçu et liste complète. Le parent conserve la date et la navigation ; un événement transmet seulement l'identifiant | Faible à modéré : perte de date, de sélecteur ou de contexte au retour. Parcours agenda/calendrier, aperçu/liste, mobile/ordinateur dans `VisitTest.php` |
| `EventParticipantProfile.vue` était déjà consommé par événements, trajets et visites, malgré son rattachement au module événements ; le type partagé vivait aussi dans `types/event.ts` | Déplacement vers `components/members/EmbeddedMemberProfile.vue` et `types/member.ts`, mise à jour des trois consommateurs et des types événement/visite/trajet. Pas de composant supplémentaire par domaine | Modéré : fuite d'identité pour un compte bloqué ou perte d'action. Union discriminée conservée : branche bloquée avec identifiant seulement ; contrôles `canLike`, `canBlock`, `canUnblock`, liens de discussion et de modération inchangés. Tests existants événements/trajets/visites et blocage |
| Quatre consommateurs de `ProfilePresentation.vue` traduisaient les mêmes titres et la même fréquence ; le composant demandait des libellés déjà connus | Le composant reçoit l'âge et la fréquence bruts et traduit une seule fois. Mise à jour des pages profil personnel, membre, profils passés et du profil embarqué. Le slot des badges reçoit la fréquence formatée | Modéré : fréquence nulle, traduction, badge divergent des détails. Dix cas navigateur : quatre fréquences et null, français et anglais, badge et corps du profil |
| `AdminMemberCard.vue` et `pages/Admin/Members/Index.vue` répétaient les cinq actions de modération/contact et leurs conditions | `AdminMemberActions.vue` partagé, sans enveloppe DOM. Taille compacte conservée sur ordinateur, événement de conversation traité par le parent. Types membres/onboarding répétés déplacés dans `types/admin.ts` avec leurs consommateurs | Modéré : garde d'action oubliée ou double création de conversation. Conditions serveur `can_*` conservées ; test de conversation et dialogue de match sur mobile et ordinateur, assertions en base |
| `BottomNavigationGroup.vue` et `WorkspaceSwitcher.vue` répétaient le lien de panneau, son icône, libellé, état actif et fermeture | `NavigationSheetLink.vue` partagé ; calcul identique des routes actives déplacé dans `useCurrentUrl.ts`, utilisé par la barre et les groupes | Modéré : focus, fermeture du panneau ou route active. `SheetClose as-child`, `aria-current`, sélecteurs et styles conservés ; tests existants de navigation selon rôle et largeur |
| `AdaptiveEventPanel.vue` avait un seul consommateur et répétait quinze propriétés avant de les transmettre à `ResponsivePanel` | Suppression du relais et utilisation directe dans `EventWorkspacePanel.vue`, avec le même identifiant de test et gestionnaire de restauration du défilement | Modéré : retour de focus/défilement, panneau plein cadre ou chargement partiel depuis les conversations. Tous les paramètres et événements ont été comparés ; parcours événements existants |
| `TripInformation.vue` reconstruisait un formatteur de date alors que `useTranslations` expose déjà `formatDate` | Réutilisation du formatteur existant, mêmes options medium/long, heure short et fuseau Europe/Paris ; durée inchangée | Faible : date/heure ou locale divergentes. Contrôles de types et parcours trajets existants |

Aucune dépendance ajoutée. Le déplacement du profil conserve volontairement son
ancien `data-test="event-participant-profile"` pour ne pas casser les sélecteurs
existants. Les traductions `events.participants.*` qui servent déjà les trois
contextes restent inchangées : leur renommage n'est pas nécessaire pour partager
le composant. Les valeurs de repli des biographies (`||` ou `??`) restent propres
aux appelants, et le watcher du profil sélectionné dans les profils passés est
conservé.

### Couverture réelle de ce passage

| Domaine | Niveau et interactions examinées | Limites / suites éventuelles |
| --- | --- | --- |
| Inventaire frontend | Recherche des pages, composants hors primitives UI, layouts, composables, utilitaires et types ; recherche des usages des éléments déplacés/supprimés | Inventorier et rechercher n'est pas une lecture exhaustive de chaque fichier. Ce passage n'affirme pas avoir relu intégralement tout `resources/js/` |
| Affichage public des membres | Lecture approfondie de `ProfilePresentation`, profil embarqué, pages membre/personnel/passés et de leurs appelants | Les actions métier restent côté serveur ; pas de nouvelle évaluation des politiques backend dans ce passage |
| Lignes compactes et listes | Aperçu/liste complète des visites, `EventParticipantList`, `EventParticipantStack`, `TripMemberLink`, `TripParticipantList`, `OrganizerRegistrations` | Les listes événement/trajet conservent leurs différences : comptes bloqués, organisateur/soi-même, statuts d'inscription et destinations. Ne pas les fusionner par simple ressemblance visuelle |
| Drawers et panneaux | `ResponsivePanel`, son usage événements/trajets/visites, profil imbriqué, retour à la liste, panneau des profils passés | Le panneau des profils passés garde sa logique de chargement et de focus. `useResponsiveModal` et `ResponsivePanel` fournissent déjà la primitive partagée ; pas de nouveau drawer générique |
| Administration | Cartes/table membres et actions, types et affichage de progression onboarding | Les formulaires de rôles, bannissement et suppression restent spécialisés ; leurs autorisations serveur ne sont pas déplacées dans le composant commun |
| Navigation | Barre inférieure, groupes, sélecteur d'espace, résolution d'URL active | Les règles d'espaces/rôles restent dans les appelants ; aucun remplacement général des menus |
| Dates | Format de programme d'un trajet comparé au formatteur de traduction existant | Les conversions de fuseau pour formulaires/calendriers et le regroupement des messages ne sont pas équivalents à un simple format d'affichage |
| Formulaires et pagination | Comparaison des usages principaux événements, trajets, visites et administration | Paramètres Inertia, états, libellés et règles métier différents ; pas de composable de formulaire ou de pagination universel ajouté |
| Conversations et temps réel | Contrôle ciblé des frontières avec les helpers du lot précédent et des liens vers profils | Pas de nouvelle lecture exhaustive du moteur de messagerie ; couverture du précédent réaudit conservée séparément |
| Styles et traductions | Comparaison des classes, libellés, branches responsive et textes des composants déplacés | Pas d'audit visuel pixel par pixel de toutes les pages ni de validation sur téléphone physique |

Des éléments du starter (`AuthSimpleLayout`, `AuthSplitLayout`, `AppHeaderLayout`,
`PlaceholderPattern`, `NavFooter`) n'ont pas de consommateur applicatif identifié
par les recherches de ce passage. Ils ne sont pas supprimés : confirmer les
points d'entrée, conventions de résolution dynamique et usages hors application
avant une éventuelle suppression. Il s'agit de candidats à vérifier, sans impact
fonctionnel démontré, et non de corrections prioritaires de sécurité.

### Tests et validation du passage frontend

Les tests de comportement existants ont été élargis plutôt que de tester la
structure interne des nouveaux composants : conversation administrateur sur deux
largeurs ; retour visite → profil → même liste dans six variantes ; dix cas de
fréquence traduite, y compris null. Les parcours événement/trajet, blocage,
modération et navigation sont également exercés par la suite complète.

Avant les modifications applicatives, le groupe de caractérisation admin/profil
produisait **12 tests réussis / 42 assertions**. Le processus a cependant retourné
un code 1 sans échec de test affiché ; cette sortie n'est pas présentée comme une
validation complète réussie. La validation finale ci-dessous fait foi.

Validation finale sur l'ensemble des modifications applicatives de ce passage :

| Contrôle | Résultat |
| --- | --- |
| `PAO_DISABLE=1 COMPOSER_PROCESS_TIMEOUT=0 composer ci:check` | Réussi, code 0 ; **2 059 tests Pest réussis, 6 ignorés, 35 110 assertions**, 726,80 s ; journal `/tmp/ponytail-frontend-ci.log` |
| ESLint / Prettier / TypeScript | Réussis via `lint:check`, `format:check`, `types:check` dans cette exécution |
| Pint / PHPStan-Larastan | Réussis dans cette exécution |
| Tests unitaires frontend | **159 réussis, 278 assertions, 34 fichiers** |
| Wayfinder / build Vite | Réussis dans cette exécution |
| Pest Browser / Playwright | Exécutés dans Chromium dans la suite complète, dont les nouvelles variantes admin, profil et visiteurs ; tests événement de blocage/focus et trajets de retour au profil réussis |
| `git diff --check` | Réussi après les modifications |

Par rapport à la validation complète immédiatement précédente (2 045 tests,
35 043 assertions), les 14 variantes supplémentaires passent et les mêmes six
exclusions subsistent : Reverb réel ×4, Garage ×1 et ReleaseRuntime ×1.
Le test ReleaseRuntime avait été exécuté séparément lors du lot précédent, mais
ni ce test Docker ni le build de l'image ne sont rejoués pour ce passage frontend.
La suite ne vérifie pas un téléphone physique, un service externe de production
ou un déploiement Coolify. L'avertissement Vite sur `inlineDynamicImports` demeure
sans empêcher le build. Ces limites ne permettent pas de garantir l'absence de
tout bug ; elles sont séparées des scénarios réellement exécutés.

## Correction des quatre dettes confirmées — 24 octobre 2026

Ce passage corrige les quatre constats reproduits lors du contrôle indépendant
précédent. Il ne constitue pas un nouvel audit exhaustif du dépôt.

| Constat | Correction réalisée | Preuve et non-régression |
| --- | --- | --- |
| Une inscription à un événement pouvait utiliser un membre devenu banni après son chargement | `RegisterForEvent` recharge et verrouille les comptes dans l'ordre des identifiants, puis l'événement, et appelle `EventPolicy::register` dans la transaction. Éligibilité du membre et disponibilité de l'organisateur revérifiées ; trois tentatives en cas d'interblocage | `EventRegistrationEligibilityTest` : bannissement via l'action réelle, rôle retiré, email non vérifié, majorité perdue et profil supprimé après chargement de l'objet ; aucune inscription créée |
| Les listes de trajets répétaient les lectures et autorisations pour chaque ligne | `TripWorkspaceData::summaries` charge les relations et droits par page ; `Trip::requestableBy` porte la même règle pour les listes et `TripPolicy`. Les contrôleurs conservent pagination, filtres et contrat des résumés | `SqlReadBudgetsTest` compare 1 et 10 trajets sur les deux listes, avec organisateur, demande en attente et participant accepté. `TripWorkspaceTest` vérifie les cinq statuts, l'absence du rendez-vous dans les listes et l'accès au chat |
| Les notifications rechargeaient individuellement leurs destinations | `MemberNotificationPresenter::presentMany` résout les destinations par type et par page avec les scopes existants. `Event::visibleTo` est commun à cette résolution et à la policy. Aucun cache d'autorisation entre requêtes | Budget SQL sur 1 puis 20 événements distincts ; `NotificationTargetBatchTest` vérifie événements, trajets, chats et révocation après blocage. Le chat événement conserve intentionnellement son contrat de membres acceptés, différent du chat trajet |
| Cinq composants/layouts du starter n'avaient plus de consommateurs | Suppression de `AuthSimpleLayout`, `AuthSplitLayout`, `AppHeaderLayout`, `PlaceholderPattern`, `NavFooter` : 218 lignes | Nouvelle recherche des imports et références, contrôle de la résolution des layouts ; les mentions historiques dans les documents ne sont pas des consommateurs. Types, build et parcours d'authentification couverts par la validation complète |

Les tests ont d'abord reproduit les défauts : cinq inscriptions non refusées,
six variantes de listes dont la hausse allait de 51 à 132 requêtes, et 54
requêtes supplémentaires pour les notifications. Après correction, le groupe
ciblé incluant les contrôles de confidentialité passe : **30 tests, 551 assertions**.
PHPStan/Larastan passe sans erreur. La validation complète est consignée ci-dessous
après son exécution ; ces résultats ciblés ne la remplacent pas.

### Validation finale des quatre corrections

`PAO_DISABLE=1 COMPOSER_PROCESS_TIMEOUT=0 composer ci:check` a terminé avec le
**code 0** : **2 130 tests Pest réussis, 6 ignorés, 35 777 assertions**, en
795,28 s. Journal : `/tmp/ponytail-debt-ci.log`.

- ESLint, Prettier, TypeScript, Pint et PHPStan/Larastan : réussis.
- Tests unitaires frontend : 159 réussis, 278 assertions, 34 fichiers.
- Génération Wayfinder et build Vite : réussis.
- Pest Browser/Playwright dans Chromium : exécutés, notamment authentification,
  notifications, événements et trajets, listes et chats, blocage, capacité,
  profils et navigation mobile/ordinateur.
- `git diff --check` : réussi.

La référence complète enregistrée avant ces corrections comptait 2 112 tests
réussis : les 18 nouveaux cas passent. Les six exclusions restent les mêmes :
quatre scénarios Reverb réel (`REALTIME_BROWSER_TESTS` non activé), une intégration
Garage (`GARAGE_INTEGRATION` non activé et bucket préparé requis), et une intégration
du runtime de déploiement (`DLP_RUNTIME_TEST_IMAGE` non fourni). Ces intégrations,
le build Docker, Coolify et un téléphone physique n'ont pas été revalidés dans ce
passage. L'avertissement Vite existant concernant `inlineDynamicImports` persiste,
sans échec du build. Les quatre dettes confirmées de ce passage sont traitées ;
ce résultat ne garantit pas l'absence de tout bug dans les domaines non inspectés.
