# Récapitulatif hebdomadaire par e-mail — issue 180

## Résultat attendu et décisions produit

Envoyer un rappel utile aux membres ayant des échanges privés oubliés, sans
révéler le contenu de ces échanges. L’utilisateur demande une activation par
défaut avec désactivation possible et choisit le dimanche à 15 h, heure de Paris.

La commande planifiée s’exécute chaque dimanche à 15 h dans `Europe/Paris`,
avec protection contre les exécutions concurrentes. La période couvre les sept
jours précédant cette échéance ; ses bornes sont calculées dans ce fuseau puis
converties pour les requêtes en base. Le changement d’heure ne décale pas l’heure
locale d’envoi. Aucun e-mail ne part uniquement à cause de nouveaux matches.

## Éligibilité et confidentialité

Un destinataire doit être un membre actif, hors suppression, avec une adresse
e-mail valide et vérifiée. Il doit avoir au moins un message reçu non lu,
strictement antérieur à l’échéance moins trois jours, dans une conversation
visible. Les messages qu’il a lui-même envoyés ne comptent pas.

Réutiliser les scopes existants de Conversation pour exclure les comptes
indisponibles, les autres profils masqués et les blocages dans les deux sens.
Un profil personnel masqué n’empêche pas son propriétaire de recevoir un rappel
sur ses conversations encore visibles. Les échanges archivés sont exclus.

Le corps indique le nombre de conversations contenant des messages reçus non
lus et, si le membre le souhaite, le nombre de nouveaux univers croisés de la
période. Les mêmes exclusions de disponibilité et de blocage s’appliquent aux
univers croisés comptés. Aucun nom, extrait de message, titre d’événement ou
identifiant de conversation ne figure dans l’e-mail.

## Réglages et interface

Ajouter dans la page Notifications une section dédiée aux e-mails, distincte
visuellement des notifications Web Push. Réutiliser les composants Switch,
Form, InputError et les catalogues Laravel français/anglais.

Deux préférences indépendantes répondent à la dépendance 182 :

- Récapitulatif des messages : active ou désactive l’envoi hebdomadaire.
- Nouveaux univers croisés dans le récapitulatif : active ou désactive ce compteur.

Les deux sont activées par défaut, y compris pour les comptes existants sans
choix enregistré. Désactiver les messages supprime tout envoi ; désactiver les
univers croisés conserve le rappel des messages sans cette information.
L’interface explique cette conséquence et l’horaire du dimanche à 15 h.
Le bouton existant « Tout désactiver » désactive aussi les deux préférences.
Aucune permission du navigateur n’est nécessaire pour l’e-mail.

Stocker les choix côté serveur, séparément des catégories Web Push, sans
introduire de dépendance ni modifier les autorisations des notifications
immédiates. Intégrer ces préférences à l’export et à la suppression du compte.

## Livraison, reprise et unicité

Une commande prépare par lots les membres éligibles et met chaque livraison
en file. Une trace minimale par membre et échéance hebdomadaire, protégée par
une contrainte unique en base, empêche de créer plusieurs livraisons lors de
relances ou d’exécutions concurrentes. Elle ne stocke aucun contenu privé.

Le job recharge le membre et revérifie son statut, l’adresse, les préférences,
les blocages, la visibilité et les non-lus immédiatement avant l’envoi. Un
membre devenu inéligible est ignoré. Les jobs d’une période expirée ne sont pas
envoyés lors d’une semaine suivante.

Réutiliser les conventions de file du projet : quatre tentatives maximum,
délais de 60, 300 et 900 secondes pour les échecs temporaires. Une livraison
réussie n’est pas rejouée. Les tentatives sont sérialisées par livraison.
Le SMTP ne permet pas de garantir exactement une réception si le transport
accepte le message puis interrompt la connexion avant l’accusé de réception ;
les tests doivent garantir l’unicité applicative, sans prétendre supprimer
cette ambiguïté du transport.

Le Mailable utilise la langue FR/EN du membre, le transport Laravel configuré
et les conventions des e-mails existants. Un lien vers l’application et un
lien vers les réglages nécessitent l’authentification habituelle et ne
contiennent ni donnée personnelle ni jeton de connexion. Mailpit reste le
transport local ; aucun fournisseur SMTP de production n’est choisi ici.

## Vérification et documentation

Tests Pest : préférence active par défaut pour anciens et nouveaux comptes,
désactivation et réactivation, validation et isolation entre comptes ; seuil
strict de trois jours, messages envoyés/lus exclus, compte non vérifié ou
inactif, suppression, adresse invalide, profil masqué, archivage, blocages dans
les deux sens ; comptage des univers croisés et choix du compteur ; unicité
par période, relance et reprise, revalidation avant envoi, expiration ;
contenu FR/EN sans identité ni texte privé ; planification dimanche 15 h et
changement d’heure. Tests navigateur : réglages visibles, sauvegarde durable et
« Tout désactiver ». Vérifier l’export et la suppression des données ajoutées.

Mettre à jour PRD, modèle de données et opérations pour distinguer ce qui sera
livré du périmètre différé. Documenter commande, scheduler, queue, contrôle
Mailpit et migration explicite. Exécuter les contrôles PHP/frontend concernés
et la suite Pest avant de déclarer le travail terminé.

## Hors périmètre

Messages d’événements, récapitulatif sans message privé ancien non lu,
fréquences personnalisables, campagne marketing et contenu privé dans l’e-mail.
