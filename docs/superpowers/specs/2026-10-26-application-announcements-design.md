# Annonces de l’application — issue 317

Périmètre validé le 26 octobre 2026 : envoi manuel par un administrateur,
à tous les comptes actifs et vérifiés sans demande de suppression, tous rôles
confondus, ou à une sélection nominative de ces comptes. La catégorie
« Annonces de l’application » est activée par défaut et désactivable dans les
réglages ; elle concerne la notification persistante et son éventuel Push.

L’administrateur saisit un titre et un texte brut FR/EN, un lien HTTPS
facultatif, ou choisit une annonce de mise à jour dont le lien vers le changelog
est résolu dans la langue du destinataire. Un aperçu affiche le contenu,
l’audience préparée et les canaux avant confirmation. La case Push est
facultative, décochée par défaut, et respecte consentement et préférences.
Les livraisons revérifient éligibilité et préférences. Le contenu envoyé est
figé ; lecture et suppression individuelles restent disponibles. Une reprise
ne recrée jamais une notification déjà livrée, même supprimée ensuite.

Approche retenue : modèles dédiés pour l’annonce et les livraisons, avec les
primitives existantes de notifications, panneau responsive, SafeHttpsUrl et
transport Push. Étendre les annonces partenaires appliquerait des règles de
rôle, modération et statistiques injustifiées. Aucun moteur générique de
campagnes, automatisation de release, e-mail ou rechargement PWA.

Les erreurs et compteurs de livraison sont visibles de l’administration sans
contenu privé ni détail technique sensible. Les données de destinataires sont
réservées aux administrateurs, supprimées avec le compte et incluses dans
l’export individuel sans exposer l’audience.
