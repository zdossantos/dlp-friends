# Séparation de Redis en production

Redis héberge le cache, les sessions et les files Laravel. En production, il est
exploité comme une ressource Coolify indépendante afin que son cycle de vie ne
dépende pas des redéploiements de l'application. La stack locale conserve son
service Redis autonome pour le développement et les tests.

## Configuration Coolify

La ressource observée porte l'identifiant Coolify
`tvgg71qeadvxtzxez2o2agy5`, utilise Redis 7.2 et rejoint le réseau Docker privé
`coolify`. Elle ne publie aucun port sur l'hôte. Dans les paramètres avancés de
l'application Docker Compose, activer **Connect to Predefined Network** afin que
Coolify la raccorde à sa destination. L'application utilise l'identifiant Redis
comme hôte réseau, le port interne `6379` et le mot de passe déjà géré par
Coolify.

Définir dans l'application :

| Variable | Valeur attendue |
| --- | --- |
| `REDIS_HOST` | Identifiant ou alias unique de la ressource Redis |
| `REDIS_PORT` | Port interne, `6379` par défaut |
| `REDIS_PASSWORD` | Secret Redis conservé uniquement dans Coolify |

Le Compose ne déclare aucun réseau : Coolify crée le réseau propre au stack et
le raccorde au réseau prédéfini. Il ne crée plus Redis et ne déclare plus de
dépendance vers un service Redis local.

## Bascule et vérification

1. Vérifier que la ressource indépendante est saine et répond à un `PING`
   authentifié depuis un conteneur temporaire du réseau privé.
2. Mettre l'application en maintenance, arrêter worker et scheduler, puis
   contrôler les travaux en attente. Le Redis intégré n'utilise aucune
   persistance ; la bascule invalide donc les sessions et caches présents et les
   files doivent être vides ou explicitement abandonnées.
3. Activer **Connect to Predefined Network**, renseigner les trois variables
   Redis dans Coolify et déployer le Compose qui
   exclut Redis.
4. Dans chacun des quatre processus Laravel, vérifier la configuration effective
   puis exécuter un `PING` via la connexion Redis de Laravel.
5. Redémarrer les workers, lever la maintenance et vérifier `/up`, la connexion,
   une session persistante, une tâche de file et les journaux.
6. Redéployer une seconde fois et confirmer que la ressource Redis conserve son
   conteneur et reste saine.

Conserver l'ancien conteneur applicatif jusqu'à la validation. Pour revenir en
arrière, remettre l'application en maintenance, arrêter worker et scheduler,
restaurer les anciennes variables et le Compose précédent, puis vérifier Redis
avant de rouvrir le trafic. Ne consigner aucun mot de passe dans le dépôt, les
commandes de diagnostic ou le compte rendu.
