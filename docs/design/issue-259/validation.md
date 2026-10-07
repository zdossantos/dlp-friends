# Revue produit et approbation

**Décision actuelle : en attente.** Les préférences « mobile first » et « bibliothèque d’animations optimisée si utile » ont été reçues. Elles ne constituent pas une approbation des maquettes.

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

- [ ] Direction : marque expressive sur le public, application calme dans les tâches.
- [ ] Navigation basse avec libellés courts, limitée aux destinations autorisées.
- [ ] Carte de découverte, listes mobiles et comparaison partenaire.
- [ ] Panneaux et chaîne de retour des événements.
- [ ] Confirmations, états dégradés et retours de succès.
- [ ] Traitement des saisons et variantes FR/EN.
- [ ] Mouvement discret, option de bibliothèque conditionnée à un gain mesuré.

Réponse possible : « Je valide la direction et les maquettes de cette version pour l’intégration », ou liste des corrections par écran. Consigner la date, le lien du commit approuvé et les éventuelles réserves ici après une réponse explicite. Si seule une partie est approuvée, limiter les lots d’intégration à cette partie ; ne pas présumer l’approbation du reste.

## Arbitrages distincts à approuver

Les libellés visibles « Agenda » et « Alertes » sont proposés pour la navigation membre à six entrées sur petit écran. Les destinations, titres « Événements » / « Notifications » et noms accessibles restent inchangés. C’est un écart au vocabulaire de navigation actuel : le valider séparément ou conserver les icônes avec les libellés canoniques accessibles. Les valeurs saisonnières et la présentation en étapes de l’édition du profil sont également des propositions de présentation, à confirmer sans changer la validation serveur ou les champs disponibles.
