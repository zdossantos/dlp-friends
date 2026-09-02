# Exploitation et fiabilité

Ce document porte les exigences et procédures opérateur. Les objectifs produit
et l’état des capacités applicatives sont définis dans le
[`PRD.md`](PRD.md).

## Prérequis de publication légale

- Définir `LEGAL_CONTACT_EMAIL` dans les secrets Coolify avant la production.
- Faire relire professionnellement les CGU et la politique de confidentialité.
- Confirmer la sauvegarde quotidienne chiffrée MySQL et MinIO, hors serveur,
  avec une rétention de 30 jours.
- Le VPS est hébergé chez IONOS. Mailpit reste local uniquement ; Resend est le
  transport d’e-mails transactionnels de production.

## Premier déploiement sur Coolify

1. Créer une application Docker Compose depuis le dépôt GitHub, suivre la
   branche `main` et sélectionner `compose.production.yaml`.
2. Associer le domaine HTTPS public au service `web` sur son port interne `80`,
   puis le domaine WebSocket au service `reverb` sur son port interne `8080`.
3. Renseigner les variables obligatoires détectées par Coolify :
   `APP_KEY`, `APP_URL`, `LEGAL_CONTACT_EMAIL`, `DB_DATABASE`, `DB_USERNAME`,
   `DB_PASSWORD`, `MYSQL_ROOT_PASSWORD`, `MINIO_ROOT_USER`,
   `MINIO_ROOT_PASSWORD`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`,
   `AWS_BUCKET`, `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET`,
   `VITE_REVERB_HOST`, `RESEND_API_KEY` et `MAIL_FROM_ADDRESS`.
4. Vérifier que `VITE_REVERB_HOST` contient uniquement le nom d’hôte public de
   Reverb, sans protocole. Les valeurs par défaut utilisent le port `443` et le
   schéma `https` ; les surcharger avec `VITE_REVERB_PORT` et
   `VITE_REVERB_SCHEME` uniquement si l’infrastructure l’exige.
5. Déployer la stack et attendre que `web`, `worker`, `scheduler`, `reverb`,
   `mysql`, `redis` et `minio` soient sains.
6. Créer le bucket privé nommé par `AWS_BUCKET` dans MinIO avant d’activer un
   parcours qui écrit des objets. Utiliser pour l’application les identifiants
   `AWS_ACCESS_KEY_ID` et `AWS_SECRET_ACCESS_KEY`, distincts des identifiants
   racine MinIO lorsque la gestion des utilisateurs MinIO est disponible.
7. Exécuter explicitement la migration dans le conteneur `web` :

   ```sh
   php artisan migrate --force
   ```

8. Redémarrer proprement les workers, puis contrôler la santé publique :

   ```sh
   php artisan queue:restart
   curl --fail --silent https://<domaine-application>/up
   ```

Les migrations ne font partie ni de l’entrypoint ni de la commande de démarrage
d’un service. Une modification de `VITE_REVERB_*` exige une nouvelle
construction de l’image, car ces valeurs sont intégrées aux assets frontend.

## Déploiements suivants

Coolify surveille uniquement `main`. Après le merge d’une pull request et la
réussite du déploiement automatique :

1. lire les notes de migration de la version ;
2. activer une fenêtre de maintenance si la migration l’exige ;
3. exécuter `php artisan migrate --force` dans `web` ;
4. exécuter `php artisan queue:restart` ;
5. vérifier `/up`, la connexion WebSocket, les files et les journaux des sept
   services.

## Sauvegardes

- Sauvegarde chiffrée quotidienne de MySQL, avec conservation de 30 jours et test mensuel de restauration.
- Sauvegarde quotidienne du bucket MinIO des images, avec la même politique de conservation.
- Les sauvegardes sont stockées hors du serveur de production. Une copie sur le même volume Docker ne constitue pas une sauvegarde.
- La restauration s’effectue sur une stack isolée : restaurer MySQL, restaurer
  le bucket MinIO, déployer l’image applicative de la version compatible,
  exécuter les migrations nécessaires, puis vérifier `/up`, Reverb, les files
  et un objet privé avant de rediriger le trafic.

## Santé et alertes

- La route `/up` vérifie que l'application répond; elle ne divulgue ni version sensible ni configuration.
- Docker vérifie les services longs (`web`, `worker`, `scheduler`, `reverb`,
  `mysql`, `redis`, `minio`) avec un healthcheck adapté en production. Mailpit
  possède son propre healthcheck uniquement dans la stack locale.
- Configurer Coolify pour notifier les échecs de déploiement, conteneurs arrêtés, sauvegardes en erreur et manque d'espace disque.
- Les journaux applicatifs sont structurés, sans mot de passe, jeton OAuth, contenu de message privé ou données personnelles inutiles.

## Délivrabilité des e-mails

- Mailpit est le seul service SMTP fourni par la stack locale et ne doit jamais recevoir de trafic public.
- Resend est le transport de production via le mailer Laravel `resend`. La clé
  `RESEND_API_KEY` reste uniquement dans Coolify.
- Vérifier le domaine d’envoi dans Resend, définir `MAIL_FROM_ADDRESS` sur ce
  domaine et configurer SPF, DKIM et DMARC avant tout envoi réel.
- Après le premier déploiement, envoyer un e-mail transactionnel de contrôle et
  vérifier sa remise sans afficher la clé API ni le contenu du message dans les
  journaux.

## Tâches récurrentes

- La cible opérationnelle prévoit que le scheduler traite les suppressions de
  compte arrivées à échéance, les nettoyages de fichiers orphelins et les
  opérations de maintenance déclarées par le produit. La purge différée des
  comptes n’est pas encore implémentée.
- Le worker est supervisé : un job en échec est journalisé et rejoué selon une politique explicite; après le dernier essai, il rejoint la table des jobs échoués.
- Après un déploiement, redémarrer proprement les workers pour qu'ils consomment le nouveau code.

## Déploiement de la migration des conversations

La migration qui crée `conversations` reprend tous les matches existants. Pour
éviter qu'une ancienne instance crée un match entre cette reprise et le
déploiement du nouveau code, ce déploiement exige une courte fenêtre de
maintenance :

1. placer toutes les instances HTTP en maintenance et arrêter les workers ;
2. exécuter `php artisan migrate --force` ;
3. déployer la nouvelle image applicative et redémarrer les workers ;
4. exécuter la requête suivante et vérifier qu'elle retourne `0`, puis rouvrir
   le service :

```sql
SELECT COUNT(*)
FROM matches
LEFT JOIN conversations ON conversations.match_id = matches.id
WHERE conversations.id IS NULL;
```

La migration reste additive et compatible avec l'image précédente pour
permettre un retour arrière avant la réouverture. Aucun trafic social ne doit
cependant être réactivé tant que la nouvelle image n'est pas en service.

## Procédure d'incident minimale

1. Vérifier la route de santé, les logs Coolify et les logs Laravel.
2. Stopper le déploiement si une migration ou la santé échoue.
3. Revenir à l'image applicative précédente uniquement après vérification de compatibilité de schéma.
4. Restaurer une sauvegarde seulement si le problème est une perte/corruption de données; journaliser l'opération et prévenir les utilisateurs concernés si nécessaire.
