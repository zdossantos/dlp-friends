# Maquettes — Visites et Voyager ensemble

Direction revue avec le porteur du projet le 20 octobre 2026. Ces fichiers documentent deux évolutions futures ; ils n’implémentent aucune fonctionnalité dans DLP Friends.

Télécharger `prototype.html` et l’ouvrir dans un navigateur. Utiliser les onglets Visites / Trajets, la recherche, le sélecteur Agenda / Calendrier et les réglages de visibilité. Les données sont fictives et restent locales. Le prototype s’ouvre sur le 24 octobre avec 24 autres personnes présentes. Les captures sont réalisées à 390 px de large.

Le prototype illustre les parcours, mais n’est pas un contrat d’implémentation exhaustif : le calendrier est limité à octobre 2026, certains écrans de gestion ne sont pas simulés, et « Aperçu après acceptation » est uniquement un raccourci de démonstration. Les règles côté serveur et le périmètre à livrer sont décrits dans les issues liées. L’agenda demeure la vue proposée par défaut dans le produit ; le prototype s’ouvre sur le calendrier pour montrer l’affluence.

Les ressources de présentation du fichier exporté peuvent nécessiter Internet. Aucun compte réel, aucune donnée privée, aucun actif Disney n’est embarqué.

## Captures

- `visites-calendrier.png` : compteur de membres, repère personnel et aperçu d’un jour à forte affluence.
- `visites-agenda.png` : bloc « Ta visite » distinct des visites partagées.
- `visites-confirmation.png` : confirmation explicite du partage des dates avec tous les membres.
- `trajets-liste.png` : propositions en train et en voiture.
- `trajets-detail.png` : demande de participation avant acceptation, rendez-vous précis privé.
- `trajets-discussion.png` : discussion du groupe après acceptation.

Vérification du prototype dans Chromium : compteur de 24 membres, aperçu de trois profils, liste complète, filtre de 11 matchs, recherche par nom, distinction des visites personnelles, absence d’erreur JavaScript et de débordement horizontal à 320, 390 et 736 px.
