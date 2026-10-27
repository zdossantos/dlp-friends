# Statistiques d’utilisation

Le tableau de bord `/dashboard` est réservé au rôle `admin`. Il lit les données
métier existantes ; il ne collecte aucun événement supplémentaire et ne restitue
aucun contenu de message dans son rapport d’usage.

## Période et comparaison

Le filtre couvre 30 jours par défaut, journée courante comprise, en UTC.
Les deux dates sont incluses ; la journée courante est partielle. Une période
personnalisée couvre de 1 à 366 jours, sans date future. La comparaison porte sur
les jours immédiatement précédents, pour une durée identique. Une base précédente
nulle est indiquée sans pourcentage d’évolution.

Un calendrier de plage de dates permet de sélectionner le début puis la fin.
La vue d’ensemble compare les 14 séries quotidiennes sur une échelle commune, sans
empilement ; la légende permet de les masquer et les tableaux restent accessibles
au clavier. ECharts fournit les infobulles au survol ou au toucher.
Les inscriptions et créations de sorties sont des barres groupées ; les anneaux
présentent uniquement des parts exclusives d’un même total (likes/refus et matchs
avec/sans message). Les étapes imbriquées d’activation et les intérêts à choix
multiples restent des barres, car un camembert serait trompeur. Les thèmes et traductions FR/EN
suivent les préférences de l’administration.

## Population et définitions

Les indicateurs d’usage portent sur les comptes ayant actuellement le rôle `user`,
hors comptes administrateurs. Les partenaires ayant aussi le rôle membre restent
inclus. Les partenaires sans rôle membre sont exclus. Les cartes historiques
« Comptes de toute la plateforme » et les inscriptions récentes couvrent tous
les rôles et ne dépendent pas du filtre.

| Indicateur | Définition |
| --- | --- |
| Nouveaux membres | Comptes de la population créés pendant la période. |
| Membres contributeurs | Auteurs distincts d’une décision de découverte, d’un message privé ou de groupe, ou de la création d’un événement, d’une visite ou d’un trajet. Dédupliqués par jour dans la courbe et sur toute la période dans le total. |
| Likes et refus | Décisions conservées, selon leur `created_at`. Convertir un refus en like modifie la répartition historique ; aucun deuxième geste n’est inventé. Les deux comptes doivent être membres hors administration. |
| Matchs amicaux | Matchs créés entre deux membres hors administration. |
| Messages privés | Messages créés par ces membres dans leurs conversations de match. Les échanges d’assistance avec un administrateur sont exclus. |
| Messages de groupe | Messages de membre hors annonces système. |
| Événements, visites et trajets | Créations de membres pendant la période, indépendamment de la date prévue et de l’état actuel. |
| Inscriptions aux événements et demandes de trajet | Enregistrements créés par les membres pendant la période, quel que soit leur état actuel. Ce ne sont pas des présences physiques. |
| Blocages et signalements | Enregistrements créés par les membres pendant la période. Leur hausse n’est pas, à elle seule, une mesure de dégradation de la sécurité. |

## Cohortes et instantanés

L’activation mesure l’état **actuel** des inscrits de la période. Chaque étape
inclut les conditions précédentes : compte, e-mail vérifié, profil complété avec
avatar actif, tutoriel terminé. Une conversion peut être postérieure à la période ;
ce n’est pas un journal chronologique de conversion.

Le taux de premier message porte sur les matchs créés pendant la période et
l’existence d’au moins un message à ce jour. Il ne garantit ni réponse réciproque
ni rencontre effective.

La communauté et les huit intérêts actifs les plus sélectionnés sont des
instantanés actuels, indépendants du filtre. Un profil peut sélectionner plusieurs
intérêts. Le statut actif désigne la disponibilité du compte, pas sa fréquentation.

## Limites et exploitation

Les suppressions, changements de rôle et modifications peuvent faire évoluer les
résultats historiques. Les lectures seules, sessions, connexions, appareils,
durées et rétention ne sont pas mesurés. Une période vide affiche des zéros.

Les agrégations s’exécutent côté MySQL sur une fenêtre bornée ; seuls les points
quotidiens, les totaux et huit intérêts au maximum sont transmis. Aucune migration,
donnée de suivi ni intervention de production n’est requise. ECharts est chargé
avec les modules ligne, barre et anneau et un rendu SVG ; le calendrier réutilise
Reka UI et @internationalized/date.
Les performances sur une volumétrie de production restent à mesurer avant toute
optimisation ou ajout de cache.
