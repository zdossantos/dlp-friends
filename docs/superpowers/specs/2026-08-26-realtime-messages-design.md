# Envoi et diffusion des messages en temps réel

## Objectif

Permettre aux deux membres d'une conversation active issue d'un match de
persister un message textuel puis de le recevoir en temps réel via Reverb. Ce
lot fournit le contrat backend nécessaire à la future interface de messagerie,
sans ajouter cette interface.

## Périmètre

Le lot ajoute la table `messages`, le modèle `Message`, l'action métier
`SendMessage`, une requête HTTP validée, un contrôleur d'envoi, l'événement
broadcast `MessageSent` et l'autorisation du canal privé de conversation.

Les pièces jointes, GIF, réactions, accusés de lecture, édition, suppression,
pagination et interface Vue restent hors périmètre. Le blocage n'est pas
implémenté ici : l'état `archived_at` déjà porté par la conversation constitue
le contrat utilisé par ce lot, et l'issue de blocage sera responsable de
l'activer.

## Stockage et modèle

`messages` possède un identifiant séquentiel, `conversation_id`,
`author_user_id`, `content` et les timestamps. Les deux clés étrangères sont
indexées et supprimées en cascade avec leur conversation ou leur auteur,
conformément au cycle de suppression de compte documenté. `content` est un
champ texte et ne contient que la valeur brute validée ; aucun HTML dérivé ou
pré-rendu n'est stocké.

`Conversation` expose une relation `messages` ordonnée explicitement par
l'identifiant lorsque l'ordre est nécessaire. `Message` expose ses relations
`conversation` et `author`. Aucune donnée de lecture ou de modification n'est
ajoutée.

## Envoi HTTP et action métier

`POST /conversations/{conversation}/messages` reste dans le groupe social
existant : authentification, e-mail vérifié, compte socialement éligible et
profil complet sont donc requis. Le contrôleur délègue l'autorisation à
`ConversationPolicy::send`, puis transmet l'utilisateur, la conversation et le
contenu validé à `SendMessage`.

La `FormRequest` accepte uniquement `content` comme chaîne requise, non vide et
limitée à 2 000 caractères Unicode. Les espaces saisis sont conservés dans le
contenu, mais une valeur constituée uniquement d'espaces est refusée. Le
contrôleur renvoie le message créé sous forme JSON avec un statut HTTP 201 ; le
contrat contient uniquement l'identifiant du message, l'identifiant de la
conversation, celui de l'auteur, le contenu brut et les timestamps nécessaires
au consommateur.

`SendMessage` revérifie l'autorisation d'envoi au moment du cas d'usage afin de
ne pas dépendre exclusivement du contrôleur. Il persiste le message dans une
transaction courte. Après le commit, il déclenche `MessageSent` et retourne le
message persisté. Une conversation archivée ou un utilisateur extérieur reçoit
un refus d'autorisation et aucun message n'est créé.

## Diffusion temps réel

`MessageSent` implémente le broadcast différé après commit et publie sur le
canal privé `conversation.{conversationId}`. Sa charge utile reprend le contrat
JSON minimal du message, sans modèle Eloquent sérialisé implicitement. Le nom
d'événement public est stable et explicite : `message.sent`.

La diffusion est mise en file après la réussite de la transaction. Une panne
du diffuseur ou du worker ne peut donc pas annuler la ligne déjà persistée ; le
message pourra toujours être retrouvé par une future lecture HTTP. À l'inverse,
une transaction annulée ne publie aucun événement.

`routes/channels.php` autorise `conversation.{conversation}` uniquement lorsque
l'utilisateur appartient au match et que la conversation n'est pas archivée.
Cette règle appelle la Policy Laravel plutôt que de dupliquer les conditions.
Un utilisateur extérieur, y compris administrateur, est refusé.

## Sécurité et confidentialité

Le contenu membre reste du texte brut de bout en bout. Le backend ne le marque
jamais comme HTML et la future interface devra l'insérer par interpolation Vue,
jamais avec `v-html`. Les tests de contrat incluent une charge HTML hostile et
confirment qu'elle est renvoyée comme texte inchangé, sans créer de fragment
HTML serveur.

Ni l'action, ni l'événement, ni le contrôleur ne journalisent le contenu. Les
exceptions et messages de validation ne recopient pas la valeur rejetée. Aucun
listener de journalisation n'est ajouté.

## Gestion des erreurs

- Une validation invalide renvoie HTTP 422 sans persistance ni événement.
- Un non-membre ou un membre d'une conversation archivée reçoit HTTP 403 sans
  persistance ni événement.
- Un échec de base annule la transaction et ne planifie aucune diffusion.
- Un échec de diffusion intervient après la persistance et ne supprime pas le
  message.

## Tests

Les tests Pest suivent un cycle rouge, vert, refactorisation et couvrent :

- le schéma, les clés étrangères et les relations Eloquent ;
- l'envoi valide par chacun des deux membres ;
- le refus d'un extérieur, d'un administrateur extérieur et d'une conversation
  archivée ;
- le refus d'un contenu absent, vide, uniquement composé d'espaces ou supérieur
  à 2 000 caractères, ainsi que l'acceptation exacte de 2 000 caractères ;
- la persistance du contenu brut, y compris une charge ressemblant à du HTML ;
- le contrat et le canal privé de `MessageSent` ;
- le refus du canal pour tout extérieur et pour une conversation archivée ;
- la planification du broadcast après persistance et l'indépendance de la ligne
  stockée vis-à-vis de l'exécution de la diffusion.

Après les tests ciblés, les contrôles PHP, la génération Wayfinder, les
contrôles frontend et le build sont exécutés selon `AGENTS.md`.
