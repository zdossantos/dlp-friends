# Retours privés des membres — issue 328

État : proposition validée le 29 octobre 2026, y compris le cycle des données.

## Périmètre validé par l’issue

Une ligne compacte « Proposer une idée », avec ampoule, fond violet discret et
chevron, apparaît dans Mon profil sous les actions et avant À propos. Elle
ouvre le Drawer existant, sans titre visible séparé, avec un nom accessible.
Le formulaire contient les deux sélecteurs et le texte libre définis dans
l’issue. Il explique la distinction avec les signalements de comportement.
Une confirmation locale suit l’envoi ; une erreur préserve la saisie.

La liste administrative paginée filtre nature, rubrique et statut. Le détail
présente le texte, l’auteur et la date, puis permet de modifier le statut et une
note interne. Les cinq statuts sont ceux de l’issue ; le statut initial est
À traiter. Les accès passent par les autorisations Laravel. Aucun membre
n’accède aux listes, détails, statuts ou notes, même à ses propres retours.

Aucun historique membre, vote, pièce jointe, catalogue public, outil de réponse
ou communication automatique n’est ajouté. Tous les textes sont traduits FR/EN.

## Approche proposée

Réutiliser les composants du profil, Drawer, champs et pages administratives,
avec un modèle dédié aux retours. Les retours ne sont pas des signalements de
conversation : ils ne donnent accès à aucun échange privé et ne déclenchent
aucune sanction. Détourner le modèle de modération ajouterait des permissions et
un cycle de traitement sans rapport avec ce besoin.

Propositions d’implémentation :

- Texte et note interne : au plus 5 000 caractères chacun ; texte obligatoire.
- Limitation serveur : cinq tentatives d’envoi par heure et par compte.
- Navigation : lien Retours membres dans le menu administratif existant,
  sans nouvel onglet inférieur.
- Identité minimale : nom affiché et lien vers la fiche administrative existante,
  où un contact manuel reste possible, sans recopier de coordonnées.

## Cycle des données validé

Proposition : expiration deux ans après l’envoi, ou suppression avec le compte
si sa purge intervient avant. Le texte et les notes sont supprimés ensemble.
Le gel des purges automatiques des comptes bannis continue de s’appliquer ;
une suppression administrative explicite conserve son comportement existant.

L’export personnel inclurait nature, rubrique, texte envoyé et date, en excluant
statut et note interne. Cette présence dans l’export ne crée pas de suivi membre.
L’option de conservation uniquement jusqu’à la purge du compte n’a pas été retenue.

## Vérifications prévues

Pest couvrira les valeurs hors catalogue, les limites, le statut initial,
l’auteur déterminé par la session, les restrictions d’accès, les filtres et le
traitement administratif, l’absence de notifications et d’e-mails, l’export
et la purge selon la décision retenue. Le test navigateur couvrira l’ouverture,
l’envoi et sa confirmation, la conservation après erreur, le clavier et le
mobile. Les contrôles PHP, frontend et build concernés suivront.

Le PRD et les documents de confidentialité et d’interface seront mis à jour
avec le comportement effectivement livré. Aucune migration ne sera exécutée
en production dans cette tâche.
