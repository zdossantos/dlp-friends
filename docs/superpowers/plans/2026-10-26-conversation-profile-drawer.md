# Profils dans les conversations — issue 307

Objectif : ouvrir le profil autorisé depuis l’en-tête privé ou un nom d’auteur,
sans navigation ni démontage du chat. Le chat partagé couvre événements et trajets.

Approche : un `MemberProfileDrawer` partagé réutilise `EmbeddedMemberProfile` et
les primitives Drawer. La route `members.show` fournit une réponse JSON après
les mêmes contrôles de profil public et de liens sociaux. Un blocage dans un
sens ou l’autre donne un état indisponible sans contenu de profil. La fermeture
annule le chargement et restitue le focus au déclencheur sans défiler.

La page de profil existante continue à fonctionner. Aucun groupe supplémentaire
ni dépendance n’est ajouté. Le drawer refuse explicitement les paires bloquées.

- [x] Écrire et observer les tests rouges : réponse JSON autorisée, restrictions,
  ouverture privée et groupe, conservation du brouillon et du contexte.
- [x] Ajouter la réponse JSON à `PublicMemberProfileController`, après Policy.
- [x] Ajouter `MemberProfileDrawer.vue`, les déclencheurs dans
  `ConversationHeader.vue` et `MessageTimeline.vue`, puis les raccorder dans
  `Conversations/Show.vue` et `groups/GroupChat.vue`.
- [x] Traduire les états et libellés FR/EN ; documenter le parcours livré.
- [x] Vérifier tests ciblés puis contrôles complets concernés, build Docker,
  relire le diff avant la PR vers main, sans fusion ni déploiement.

La revue indépendante a identifié un snapshot périmé après une action du profil.
Les succès de like/blocage ferment maintenant le drawer ; les erreurs restent
dans le dialogue. Les tests navigateur couvrent les actions et leur annulation.

La suite complète locale a passé 2264 tests et détecté trois attentes historiques
de navigation dans `MemberBlockingTest`, adaptées puis revérifiées séparément.
Six tests sont ignorés localement, dont les parcours Reverb dédiés à la CI.
Lint, types, analyse statique, 167 tests frontend et build Docker passent.

Points de revue : réponse tardive après fermeture, profil masqué entre deux
ouvertures, blocage entrant/sortant, erreurs réseau, clavier/focus et drawer
imbriqué dans un panneau, historique paginé et position de lecture.
