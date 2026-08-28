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
3. Création du profil sur une page dédiée : avatar actif obligatoire, nom d'affichage non unique, bio, fréquence de visite, visibilité et sélection d’intérêts actifs. Le prénom n'est pas demandé.
4. Tutoriel produit linéaire intégré à l’inscription : passer la première carte, liker la deuxième, ouvrir le match puis envoyer un premier message. Les actions opposées sont désactivées et le geste est contraint dans la direction attendue.
5. Arrivée sur la page de profil personnel. Un administrateur peut ensuite ouvrir son dashboard réservé.
6. Découverte : carte, affinités explicables, like ou refus.
7. Match réciproque : confirmation claire puis accès à la conversation.
8. Réglages : séparation entre profil public, e-mail de compte, sécurité, apparence et relance du tutoriel.

Le tutoriel masque la navigation membre principale et inférieure afin de garder un parcours guidé. Il reste utilisable au clavier et reprend automatiquement à l’étape enregistrée. Un stepper prolonge les quatre étapes du profil jusqu’aux quatre étapes de prise en main.

## Composants

- Utiliser les composants du starter kit Vue Laravel et shadcn-vue en premier.
- Utiliser Reka UI lorsqu'un primitive accessible manque.
- Construire les composants métier (carte de swipe, résumé d'affinités, sélecteur d’intérêts, conversation) au-dessus de ces primitives.
- Le sélecteur n’affiche que les intérêts actifs et indique la limite configurée, fixée à cinq par défaut. Les intérêts archivés et l’historique de sélections suspendues ne sont pas exposés au membre.

## Avatar et photo

- La photo personnelle est facultative, mais ne remplace pas l'avatar obligatoire.
- Le membre choisit un avatar actif dans un catalogue administrable. Son image est affichée sur un fond dégradé entre les deux couleurs configurées.
- Aucun asset Disney ou non autorisé n'est livré avec l'application ; la validation des assets ajoutés reste une responsabilité administrative hors application.
- Aucun téléversement d'image ne doit être obligatoire pour utiliser l'application.
