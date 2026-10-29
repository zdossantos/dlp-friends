# Livraison de l’issue 328

Le cycle des données et la proposition de conception ont été validés le
29 octobre 2026. Exécution dans la branche dédiée, en réutilisant les conventions
Laravel/Inertia/Vue du dépôt et sans dépendance supplémentaire.

1. Écrire les tests Pest de soumission, validation, autorisation, traitement,
   filtres, absence de communications, export et purge ; constater leur échec.
2. Ajouter `MemberFeedback`, ses catalogues enum, sa migration, sa Policy et les
   Form Requests. Ajouter le POST membre et les vues index/show/update admin.
   Protéger le POST à cinq tentatives par heure et le statut initial côté serveur.
3. Étendre `BuildUserDataExport` sans note ni statut ; supprimer en cascade avec
   le compte et ajouter une purge quotidienne après deux ans, avec verrouillage
   des propriétaires et gel des comptes bannis comme pour les visites.
4. Tester puis ajouter `MemberFeedbackDrawer` dans les actions du profil, avec
   champs traduits, erreurs conservant le brouillon et confirmation accessible.
   Ajouter les pages admin filtrées/paginées et le lien dans le menu existant.
5. Vérifier Chromium mobile/clavier et les contrôles PHP/frontend/build ; mettre
   à jour PRD, confidentialité et design system, puis préparer la PR vers main.

Points de revue : valeurs falsifiées, propriété déterminée par la session,
accès direct aux retours d’autrui ou aux siens, note absente de l’export,
expiration à la frontière des deux ans, bannissement concurrent et suppression
du compte. Aucun retour ne déclenche de notification, e-mail ou Push.
