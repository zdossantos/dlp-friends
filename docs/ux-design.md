# Direction artistique et expérience utilisateur

## Identité

L'interface doit évoquer une magie contemporaine et chaleureuse, sans reprendre l'identité visuelle, les logos ou les personnages officiels de Disney.

- Application Mobile first
- Fond clair blanc par défaut et thème sombre complet.
- Palette principale violet et rose ; doré réservé à quelques éléments de mise en valeur (match, badges, états importants).
- Contraste suffisant dans les deux thèmes ; les couleurs ne sont jamais le seul indicateur d'état.

## Parcours principaux

1. Inscription, confirmation de majorité et vérification du compte.
2. Création du profil : informations de base, image facultative, fréquence de visite et passions.
3. Découverte : carte, affinités explicables, like ou refus.
4. Match réciproque : confirmation claire puis accès à la conversation.
5. Réglages : modification, masquage, blocage et suppression du compte.

## Composants

- Utiliser les composants du starter kit Vue Laravel et shadcn-vue en premier.
- Utiliser Reka UI lorsqu'un primitive accessible manque.
- Construire les composants métier (carte de swipe, résumé d'affinités, sélecteur de passions, conversation) au-dessus de ces primitives.

## Avatar et photo

- La photo personnelle est facultative.
- Le membre peut choisir un avatar dans un catalogue. Les assets de ce catalogue doivent disposer de droits d'usage adéquats avant publication.
- Aucun téléversement d'image ne doit être obligatoire pour utiliser l'application.
