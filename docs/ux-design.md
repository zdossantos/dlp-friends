# Direction artistique et expérience utilisateur

## Identité

L'interface doit évoquer une magie contemporaine et chaleureuse, sans reprendre l'identité visuelle, les logos ou les personnages officiels de Disney.

- Approche mobile first.
- Fond clair blanc par défaut et thème sombre complet.
- Palette principale violet et rose ; doré réservé à quelques éléments de mise en valeur (match, badges, états importants).
- Contraste suffisant dans les deux thèmes ; les couleurs ne sont jamais le seul indicateur d'état.

## Parcours principaux

1. Inscription avec e-mail, date de naissance et mot de passe, sans nom public demandé à ce stade.
2. Vérification obligatoire de l'adresse e-mail.
3. Création du profil sur une page dédiée : nom d'affichage non unique, bio, fréquence de visite et visibilité. Le prénom n'est pas demandé.
4. Arrivée sur la page de profil personnel. Un administrateur peut ensuite ouvrir son dashboard réservé.
5. Découverte : carte, affinités explicables, like ou refus.
6. Match réciproque : confirmation claire puis accès à la conversation.
7. Réglages : séparation entre profil public, e-mail de compte, sécurité et apparence.

## Composants

- Utiliser les composants du starter kit Vue Laravel et shadcn-vue en premier.
- Utiliser Reka UI lorsqu'un primitive accessible manque.
- Construire les composants métier (carte de swipe, résumé d'affinités, sélecteur de passions, conversation) au-dessus de ces primitives.

## Avatar et photo

- La photo personnelle est facultative.
- Le membre peut choisir un avatar dans un catalogue. Les assets de ce catalogue doivent disposer de droits d'usage adéquats avant publication.
- Aucun téléversement d'image ne doit être obligatoire pour utiliser l'application.
