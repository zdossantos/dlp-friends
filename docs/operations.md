# Exploitation et fiabilité

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

- Le scheduler traite les suppressions de compte arrivées à échéance, les nettoyages de fichiers orphelins et les opérations de maintenance déclarées par le produit.
- Le worker est supervisé : un job en échec est journalisé et rejoué selon une politique explicite; après le dernier essai, il rejoint la table des jobs échoués.
- Après un déploiement, redémarrer proprement les workers pour qu'ils consomment le nouveau code.

## Procédure d'incident minimale

1. Vérifier la route de santé, les logs Coolify et les logs Laravel.
2. Stopper le déploiement si une migration ou la santé échoue.
3. Revenir à l'image applicative précédente uniquement après vérification de compatibilité de schéma.
4. Restaurer une sauvegarde seulement si le problème est une perte/corruption de données; journaliser l'opération et prévenir les utilisateurs concernés si nécessaire.
