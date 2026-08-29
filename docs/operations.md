# Exploitation et fiabilité

Ce document porte les exigences et procédures opérateur. Les objectifs produit
et l’état des capacités applicatives sont définis dans le
[`PRD.md`](PRD.md).

## Sauvegardes

- Sauvegarde chiffrée quotidienne de MySQL, avec conservation de 30 jours et test mensuel de restauration.
- Sauvegarde quotidienne du bucket MinIO des images, avec la même politique de conservation.
- Les sauvegardes sont stockées hors du serveur de production. Une copie sur le même volume Docker ne constitue pas une sauvegarde.
- Documenter une procédure de restauration : base MySQL, objets MinIO, déploiement de la version applicative compatible et vérification de santé.

## Santé et alertes

- La route `/up` vérifie que l'application répond; elle ne divulgue ni version sensible ni configuration.
- Docker vérifie les services longs (`web`, `worker`, `scheduler`, `reverb`, `mysql`, `redis`, `minio`, `mailpit`) avec un healthcheck adapté.
- Configurer Coolify pour notifier les échecs de déploiement, conteneurs arrêtés, sauvegardes en erreur et manque d'espace disque.
- Les journaux applicatifs sont structurés, sans mot de passe, jeton OAuth, contenu de message privé ou données personnelles inutiles.

## Délivrabilité des e-mails

- Mailpit est le seul service SMTP fourni par la stack locale et ne doit jamais recevoir de trafic public.
- Le transport de production est différé. Avant sa mise en place, choisir le fournisseur ou le serveur, documenter ses sauvegardes et sa supervision, puis configurer SPF, DKIM et DMARC.
- Conserver tous les identifiants SMTP de production uniquement dans Coolify et les référencer par variables d'environnement Laravel.

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
