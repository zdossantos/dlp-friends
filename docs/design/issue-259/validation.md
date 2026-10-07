# Revue produit et approbation

**Décision actuelle : première proposition refusée ; nouvelle direction en revue.** Les préférences « mobile first » et « bibliothèque d’animations optimisée si utile » ont été reçues. Elles ne constituent pas une approbation des maquettes.

## Parcours de revue

| Parcours          | Écrans / gestes                                                                                                                                            | Vérifier                                                                                                                                                |
| ----------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Arrivée et compte | Accueil → inscription → case de consentement → profil en quatre étapes → Passer → Découvrir → Univers croisés → premier message → installation facultative | Majorité ; aucun consentement précoché ; installation non obligatoire ; vocabulaire amical                                                              |
| Découverte        | Explorer → détail ; Passer ; Profils passés → profil ; Découvrir → Univers croisés → échange                                                               | Résumé lisible ; distinction des actions ; nom long ; accès au détail ; aucun historique de likes introduit                                             |
| Échange           | Échanges → conversation ; état vide → amorce → envoyer ; like ; signaler ; bloquer                                                                         | Amorce préremplit sans envoyer ; limite 2 000 ; motif et blocage séparément compréhensibles                                                             |
| Événement         | Liste → détail privé → aperçu participant accepté → participants → profil → retour ; vue organisateur → demandes ; discussion en lecture seule             | Lieu précis privé avant acceptation ; chaîne de retour ; demande ≠ acceptation ; capacité et verrous explicables                                        |
| Données et accès  | Profil → réglages ; visibilité ; export ; suppression ; sécurité ; préférences push                                                                        | Suppression : accès immédiatement coupé et purge après 30 jours ; compte social et compte avec mot de passe sont deux variantes ; contrôle par appareil |
| Partenaire        | Publié / brouillon → soumettre ; annonces → révision ; statistiques                                                                                        | Soumission ≠ publication ; FR/EN ; approbation d’annonce déclenche envoi ; statistiques agrégées                                                        |
| Administrateur    | Membres → rôles / assistance / bannir ; Catalogues ; Partenaires ; Signalements → motif → clore ou bannir                                                  | Pas de modification UI des autorisations ; admin protégé ; absence de seconde confirmation pour bannir depuis un signalement ; conséquences visibles    |

Refaire ces parcours à 320 px puis 390 px et 1440 px. Sur les écrans représentatifs, comparer FR/EN, clair/sombre, standard/Halloween/Noël. Examiner un cas normal et un cas dégradé. Les contrôles du bandeau et les boutons « aperçu de rôle » sont des outils de revue, pas des commandes à intégrer dans le produit.

## Décisions attendues

- [ ] Direction révisée : imaginaire magique présent dans l’application, avec commandes compactes et lisibles.
- [ ] Navigation basse avec libellés courts, limitée aux destinations autorisées.
- [ ] Carte de découverte, listes mobiles et comparaison partenaire.
- [ ] Panneaux et chaîne de retour des événements.
- [ ] Confirmations, états dégradés et retours de succès.
- [ ] Traitement des saisons et variantes FR/EN.
- [ ] Mouvement discret, option de bibliothèque conditionnée à un gain mesuré.

Réponse possible : « Je valide la direction et les maquettes de cette version pour l’intégration », ou liste des corrections par écran. Consigner la date, le lien du commit approuvé et les éventuelles réserves ici après une réponse explicite. Si seule une partie est approuvée, limiter les lots d’intégration à cette partie ; ne pas présumer l’approbation du reste.

## Arbitrages distincts à approuver

Les libellés visibles « Agenda » et « Alertes » sont proposés pour la navigation membre à six entrées sur petit écran. Les destinations, titres « Événements » / « Notifications » et noms accessibles restent inchangés. C’est un écart au vocabulaire de navigation actuel : le valider séparément ou conserver les icônes avec les libellés canoniques accessibles. Les valeurs saisonnières et la présentation en étapes de l’édition du profil sont également des propositions de présentation, à confirmer sans changer la validation serveur ou les champs disponibles.

## Retour produit reçu le 7 octobre 2026

La première proposition est jugée trop massive et impersonnelle. Le porteur du produit demande :

- Un sentiment d’être dans un endroit magique, avec une place importante pour l’imaginaire.
- Une densité agréable pour une application mobile, dans le navigateur et en PWA installée.
- Les pages dont le contenu est borné doivent tenir dans la hauteur disponible, sans scroll de page. Les listes ou contenus variables peuvent défiler.
- Des ambiances Halloween et Noël nettement visibles et intéressantes, faciles à ajouter au système.

Ce retour définit le brief ; il n’approuve aucune maquette. La version révisée est d’abord appliquée à Explorer comme écran témoin. Les autres vues restent des références de couverture de la première proposition, pas la nouvelle DA. Le logo, les familles de couleur et les polices sont conservés provisoirement, faute de demande de remplacement.

La stratégie de hauteur doit vérifier le contenu réel, les actions et leur visibilité, pas simplement couper les dépassements avec `overflow: hidden`. Sur une page bornée, utiliser des étapes ou ouvrir le détail si nécessaire. Sur une liste/conversation, réserver une zone de défilement adaptée. Tester la hauteur utile avec les barres du navigateur ; le clavier ouvert et les safe areas requièrent aussi une vérification sur appareil.

Les fonds figuratifs lisibles ont ensuite été refusés : une éventuelle image doit être très discrète et fortement floutée. La personnalité doit être définie avec le porteur du produit avant de propager la DA. Trois questions complémentaires portent sur les détails graphiques, l’ambiance claire/sombre et la force des thèmes saisonniers.
