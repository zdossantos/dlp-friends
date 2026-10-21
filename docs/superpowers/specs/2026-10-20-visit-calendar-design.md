# Agenda et calendrier des visites — issue 305

Statut : règles de durée et de conservation validées le 20 octobre 2026 ;
conception proposée à la revue avant implémentation. Aucune fonctionnalité
n’est livrée par ce document.

Référence : https://github.com/zdossantos/dlp-friends/issues/305.

## Résultat attendu et périmètre

Un membre éligible renseigne ses prochains jours à Disneyland Paris, seul ou
sous forme de séjour inclusif. Il choisit une audience globale et retrouve les
prochaines visites accessibles de ses matchs ou des autres membres par nom
d’affichage. L’interface privilégie le téléphone et distingue toujours ses
propres visites de celles des autres.

Le contrat de l’issue et ses maquettes font référence. Leur mois fixe et leurs
raccourcis de démonstration ne deviennent pas des contraintes produit. Aucun
trajet, message, invitation, inscription ou nouveau contact n’est créé.

## Décisions validées

- Un séjour dure de 1 à 30 jours inclus, bornes comprises.
- La création concerne aujourd’hui ou une date future ; le dernier jour ne
  dépasse pas aujourd’hui plus 12 mois calendaires, dans le fuseau applicatif.
- Les séjours qui se chevauchent sont autorisés. Une personne compte une seule
  fois pour chaque jour, quel que soit son nombre de séjours.
- Un séjour terminé disparaît des surfaces de consultation. Il est supprimé
  automatiquement après 30 jours, sans historique public ni historique
  personnel supplémentaire. L’export personnel inclut les lignes encore
  conservées. La suspension existante des purges des comptes bannis demeure.
- Cliquer sur le nom d’un membre filtre ses prochaines dates accessibles ;
  cela ne crée aucun nouveau mode de contact.

Un séjour en cours reste visible pour ses jours restants. Sa modification
conserve son début passé possible, sans permettre d’introduire une autre date
passée ; ses nouvelles bornes respectent la durée et l’horizon ci-dessus.
La modification et la suppression portent toujours sur le séjour entier.

## Approche retenue et alternatives

Conserver un intervalle de dates par séjour et une préférence par compte :
c’est la solution recommandée, qui permet une édition atomique du séjour et
un changement d’audience immédiatement applicable aux dates existantes.

Créer une ligne par journée compliquerait les modifications et les
chevauchements. Réutiliser les événements ajouterait à tort organisateur,
capacité et participants. Ces deux approches ne sont pas retenues.

## Stockage et responsabilités Laravel

Ajouter `visits` avec `id`, `user_id`, `starts_on`, `ends_on`, `created_at` et
`updated_at`. Les bornes sont des DATE et non des instants UTC. Ajouter les
index nécessaires aux recherches de chevauchement et de propriétaire. La clé
étrangère supprime les visites en cascade à la suppression effective du compte.

Ajouter `users.visits_visibility`, enum dédié `hidden`, `matches`, `members`,
avec `hidden` par défaut pour les comptes existants et nouveaux. Cette
préférence reste indépendante des liens sociaux et des futurs trajets.

Un modèle Visit porte les scopes de sélection par période et de consultation
autorisée. Une VisitPolicy contrôle les opérations du propriétaire et les
accès. Les Form Requests valident dates, paramètres et changement d’audience.
L’action de changement d’audience verrouille le compte et recontrôle l’état
persisté avant d’accepter une transition vers `members`.

Les contrôleurs restent minces. Une classe de données prépare agenda,
calendrier, aperçu et pagination à partir des mêmes règles de consultation.
Aucune API séparée ni dépendance supplémentaire n’est nécessaire.

## Autorisations et confirmation

Toutes les routes reprennent les prérequis des événements : authentification,
majorité, compte actif, vérification de l’e-mail, rôle membre, profil complet
et tutoriel terminé. Le propriétaire conserve ses propres visites même avec
un profil masqué, tant qu’il satisfait les conditions d’accès.

Pour les autres membres, le propriétaire doit être actif, majeur, vérifié,
avec le rôle membre et un profil complet visible. Un blocage dans l’un ou
l’autre sens exclut la paire. L’audience `members` autorise alors la
consultation ; `matches` exige en plus un match encore présent ; `hidden`
ne donne jamais accès. Les requêtes appliquent ces conditions avant de
compter, paginer ou sérialiser. Les accès directs les revérifient également.

Le filtre Mes matchs restreint les visites déjà autorisées ; Tous les membres
n’élargit aucune audience. Une perte de match retire immédiatement l’accès
lors de la requête suivante. Aucune liste privée n’est envoyée pour être
ensuite masquée dans Vue.

Le choix de `members` ouvre une confirmation indiquant que les visites
existantes et futures seront accessibles aux membres connectés. Le réglage
persisté ne change qu’après validation explicite. La requête doit contenir
une confirmation acceptée lorsque la valeur actuelle diffère de `members` ;
une requête sans cet accord est refusée côté serveur. Annuler, fermer,
Échap ou Retour conserve le réglage précédent. Réduire l’audience est
immédiat. Modifier seulement un séjour ne modifie pas la préférence et ne
redemande pas l’accord.

Les réponses privées ne sont pas mises en cache publiquement et les données
personnelles ne sont pas ajoutées aux journaux ou à la mesure d’audience.

## Consultation, recherche et volume

La page Visites conserve dans ses paramètres le mois, le jour, la vue,
l’audience de filtre, la recherche et, le cas échéant, le membre sélectionné.
Sans paramètre, afficher le calendrier du mois courant, filtré sur les matchs.
Une nouvelle visite commence aujourd’hui, indépendamment du mois consulté. L’agenda et le
calendrier naviguent entre les mois, y compris au changement d’année.

Chaque requête charge une période bornée. Les compteurs quotidiens sont
calculés côté MySQL avec déduplication des membres, après autorisations,
filtre et recherche. Les propres séjours sont chargés séparément et restent
visibles malgré une recherche ou un filtre sur les autres membres. Les
identifiants de visites d’autrui ne sont pas nécessaires aux lignes publiques.

La recherche porte sur le nom d’affichage, sans distinction de casse ni
d’accents, avec une collation MySQL adaptée et des caractères de recherche
traités littéralement. `textSearch.ts` reste la référence de normalisation
frontend ; aucune liste de tous les membres n’est chargée dans le navigateur.
Un nom non unique peut correspondre à plusieurs personnes. Le clic sur un membre ouvre son profil public dans le drawer/dialog partagé
avec les événements, depuis les aperçus agenda/calendrier comme depuis la liste
complète. Le retour conserve les filtres et la pagination, sans ajouter de filtre
membre. Les autorisations sont revérifiées à chaque ouverture.

La liste complète d’un jour contient les membres distincts, triés par nom puis
identifiant, avec pagination serveur de 30 personnes et navigation explicite.
Le total exact suit la recherche et les filtres. Chaque chargement revérifie
les accès ; les changements concurrents peuvent donc réduire ce total.

## Interface et réutilisation

L’entrée membre Événements devient Sorties et conserve les URL et parcours
événements existants. Une navigation commune donne accès à Événements et
Visites. L’emplacement Trajets est réservé à l’issue 304 et n’est pas présenté
comme une fonctionnalité disponible avant sa livraison. Le shell sera
réutilisable par cette issue sans recréer la navigation.

L’agenda regroupe les journées par ordre chronologique. Un bloc « Ta visite »
utilise un repère graphique, un titre, la visibilité et l’action Modifier.
En cas de chevauchement, chaque séjour personnel reste modifiable séparément.
La section « Également présents · N » exclut toujours le propriétaire.

Le calendrier utilise une grille mensuelle réelle de sept colonnes. Une case
présente seulement le jour, « Toi » si nécessaire et le nombre des autres
membres accessibles ; au-delà de 99, le repère compact peut afficher 99+.
Le nom accessible conserve la date et le total exact. Les jours passés ne
révèlent aucun historique.

Choisir un jour affiche son détail : séjours personnels, aperçu de trois
membres maximum, puis « Voir les N personnes » pour la liste complète.
La fermeture et Retour restituent vue, mois, jour, filtres et défilement.
Les écrans sans visite et sans résultat proposent des textes distincts.

Réutiliser UserAvatar, les champs et boutons existants, useResponsiveModal
et les primitives Drawer/Dialog. Extraire seulement l’enveloppe et la
confirmation réellement partagées depuis les composants événements, en
conservant leur comportement de focus et de scroll dans les événements.
Ne pas coupler la nouvelle page aux helpers de scroll propres aux événements.

Les dates sont localisées FR/EN. Tous les textes, erreurs, confirmations,
placeholders et noms accessibles appartiennent aux catalogues Laravel.
Respecter 320 px, clair/sombre, clavier, lecteur d’écran, cibles de 44 px,
focus visible et retour du focus après fermeture. Les marqueurs personnels
ne dépendent jamais de la seule couleur.

## Export, suppression et exploitation

BuildUserDataExport inclut la préférence globale et les séjours propres au
membre encore stockés, jamais ceux d’autrui. La demande de suppression rend
immédiatement ces données inaccessibles à autrui via le statut du compte ;
la purge de compte existante supprime les lignes en cascade après son délai.
La suppression administrative reste effective immédiatement.

Une commande planifiée quotidiennement retire les séjours dont la fin est
antérieure ou égale à aujourd’hui moins 30 jours, hors propriétaires bannis.
Elle travaille par lots et recontrôle le statut sous verrou pour respecter
la politique existante de conservation. La tâche est idempotente. Les
sauvegardes conservent leur rotation existante de 30 jours.

Mettre à jour PRD, data-model, security-privacy, operations et les règles
partagées de navigation du design system au fil de la livraison. Décrire
explicitement cette évolution hors MVP plutôt que l’annoncer déjà présente.
Aucune migration automatique au démarrage Docker n’est introduite.

## Vérification attendue

Pest, avec échec confirmé avant implémentation pour les changements métier :

- CRUD propriétaire, accès tiers interdit, validation des bornes et des limites,
  séjour inclusif, chevauchement, fin de mois, année et séjour en cours.
- Trois audiences, confirmation absente/refusée/acceptée, réduction immédiate,
  modification ordinaire et transition concurrente de préférence.
- Absence de fuite dans props, détails, recherches, pagination et compteurs ;
  match perdu, deux sens du blocage et chaque état de profil indisponible.
- Déduplication : 24 autres membres dont 11 matchs, propriétaire exclu,
  chevauchements, total supérieur à 99, liste complète sur plusieurs pages.
- Export personnel, suppression de compte, rétention à la frontière des
  30 jours, suspension pour compte banni et reprise idempotente de la purge.

Pest Browser dans Chromium : création, édition et suppression d’un séjour,
recherche accentuée, clic sur un nom, agenda/calendrier, navigation mensuelle,
jour chargé, pagination complète, conservation du contexte au retour,
confirmation annulée/fermée/acceptée et navigation événements préservée.
Vérifier FR/EN, 320 px et bureau, clair/sombre, focus et accessibilité.

Après les tests ciblés, exécuter les contrôles PHP et frontend concernés,
Wayfinder, build Vite, suites Pest pertinentes et build Docker de production.
Ne déclarer aucun contrôle réussi sans sortie récente.
