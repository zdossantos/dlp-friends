# Environnement bêta privé

État : code préparé, activation bloquée par la capacité observée du serveur ;
aucune ressource bêta créée ni donnée de production copiée.
La cible approuvée est décrite dans la [spécification](superpowers/specs/2026-10-17-isolated-release-beta-design.md).

## Capacité avant activation

Relevé en lecture seule le 17 octobre 2026 à 06:49 UTC sur le serveur Coolify
prévu : 7 894 Mio de RAM totale, 1 120 Mio disponibles, aucun swap, 6 CPU,
charge moyenne 8,49 / 5,13 / 4,73 et disque racine à 88 % (30 Gio libres).
Les 43 conteneurs présents restent inchangés.

À la demande de l'opérateur (usage seul, lenteur acceptable), les limites
applicatives bêta sont réduites à **480 Mio et 0,80 CPU cumulés** : web
192 Mio, worker 128 Mio (recyclage à 96 Mio), scheduler et Reverb 80 Mio chacun.
Ce sont des plafonds, pas une mesure de consommation ni une preuve de tenue
en charge. Vérifier les limites Docker réellement appliquées et l'absence
d'OOM pendant les parcours avant activation ; ne pas relever les plafonds
automatiquement après un échec.

MySQL 8.4, Redis 7.4, Mailpit, les imports et une réserve pour les services
existants doivent encore tenir en mémoire. Moins de 500 Mio pour l'ensemble,
base incluse, n'est pas démontré. Libérer de la capacité ou redimensionner
l'hébergement, puis refaire les mesures en période de charge avant tout
provisionnement. Aucun achat ni changement des services existants n'a été effectué.

Consommations instantanées observées (Mio, arrondies) :

| Service | RAM |
| --- | ---: |
| Quatre MySQL, dont celui de DLP Friends | 1 784 |
| Coolify, hors proxy/base/Redis | 395 |
| Jellyfin | 351 |
| RomM et sa base | 443 |
| Streamystats, sa base et son worker | 499 |
| Sonarr, Radarr, Bazarr, Prowlarr | 695 |
| DLP Friends : web, worker, scheduler, Reverb | 241 |
| DLP Friends : MySQL / Redis | 551 / 5 |

Les deux premières mesures MySQL ne sont pas simultanées (544 puis 551 Mio
pour DLP Friends). Les lignes regroupées se recoupent et ne doivent pas être
additionnées. Les conteneurs utilisent environ 5 459 Mio au total ; l'hôte et
ses autres processus utilisent aussi de la mémoire. Les services médias sont
des pistes à examiner avec l'opérateur, sans arrêt ou réduction implicite.
Même limitée, la bêta partage CPU, disque et noyau avec la production : les
plafonds réduisent le risque sans garantir une absence totale d'impact.

Restent à vérifier sur l'infrastructure : ressources et secrets dédiés,
restriction IP HTTP/médias/WebSocket/Mailpit depuis deux origines, DNS/TLS,
configuration GitHub/Coolify, désactivation des anciens déclencheurs de
livraison et transition de maintenance de production. La première copie,
les migrations et les parcours réels restent à exécuter après ces contrôles.
Les tests locaux ne constituent pas une validation de ces opérations.

La bêta utilise `beta.dlp-friends.fr`, `ws-beta.dlp-friends.fr`, une instance
MySQL et son volume, Redis et son volume, un bucket Garage privé et Mailpit
dédiés. Ne pas réutiliser les clés Laravel, VAPID, Reverb, OAuth ou Resend de
production. Les variables obligatoires sont dans `.env.beta.example` et le
Compose dans `compose.beta.yaml`. Les limites de processus sont provisoires
jusqu'à leur mesure sur le serveur. Les migrations restent explicites.

## Préparer l'opération serveur

Prérequis Linux : Bash, Python 3.9 ou ultérieur (bibliothèque standard), Docker, clients MySQL
8.4, rclone et curl. Le script ne s'exécute pas dans GitHub Actions.
Copier `scripts/operations/config.example.json` hors du dépôt ; renseigner les
identités réellement observées. Protéger le répertoire des rapports en `0700`
et **tous** les fichiers de configuration/clients en `0600`, appartenant au
compte opérateur. Ne jamais publier le contenu des fichiers ou leurs erreurs.

Les fichiers MySQL utilisent une section `[client]` avec hôte, port,
utilisateur et mot de passe. La source utilise un compte d'export en lecture
seule. La cible a des droits uniquement sur sa base. Le fichier rclone utilise
deux remotes S3 avec clés distinctes ; la clé bêta n'a accès qu'au bucket bêta.
Le fichier curl Garage contient `header = "Authorization: Bearer …"` ; son
jeton est limité aux lectures `GetBucketInfo` et `GetKeyInfo`. L'API attendue
est l'[API administrative Garage v2](https://garagehq.deuxfleurs.fr/api/garage-admin-v2.html).
Un format inconnu échoue avant toute suppression.

Installer et vérifier en premier la restriction IP Traefik couvrant HTTP,
médias, WebSocket et Mailpit. Vérifier le refus depuis un client hors liste,
y compris avec un `X-Forwarded-For` forgé, **avant toute donnée réelle**.
Fermer ensuite la bêta avec sa maintenance Redis persistante, arrêter ses
trois processus de fond et attendre leur sortie propre. Le script refuse un
processus encore actif ou tué brutalement. Ne pas arrêter la production pour
rafraîchir la bêta.

## Rafraîchir explicitement

**Cette commande remplace les essais bêta.** Annoncer cet effet avant chaque
exécution réelle et confirmer que les essais à conserver ont été exportés.

```sh
bash scripts/operations/beta-refresh.sh --config /srv/dlp-operations/private/config.json --replace-beta-tests
```

Le script compare serveur MySQL/base, conteneurs, volumes, UUID et identités
Garage avant d'effacer la cible. Il refuse les tables non InnoDB et les
triggers/routines/événements nécessitant une revue spécifique. L'export
transactionnel utilise une instruction INSERT par ligne : les comptages
proviennent du même instantané que les données, même si la production écrit
pendant l'export. Le schéma doit rester stable pendant cette opération.
La copie des objets est contrôlée par téléchargement et inventaires avant et
après ; une suppression concurrente invalide le résultat. Il ne s'agit pas
d'une transaction distribuée MySQL/Garage : toute incohérence observée exige
une nouvelle copie, sans annoncer une sauvegarde complète.

Le dump et `snapshot.json` restent privés sur le serveur. En cas d'erreur,
aucun rapport réussi n'est conservé et la bêta reste fermée. Le script ne
migre et ne rouvre jamais l'application. Prévoir une conservation chiffrée et
une suppression opérateur des anciens dumps selon la politique de sauvegarde.

La neutralisation supprime sessions SQL, réinitialisations, passkeys, liens
OAuth, abonnements/livraisons Push et tâches SQL restaurés. Elle retire les
secrets 2FA et les jetons « se souvenir de moi » avec le Query Builder, sans
déchiffrement. Redis n'est jamais copié. Profils, messages, références de
fichiers, mots de passe et préférences de notification restent représentatifs.
Les comptes uniquement OAuth nécessitent une réinitialisation reçue dans
Mailpit ou un compte de test créé dans la bêta. Les URL de profils/partenaires
restent des liens externes : un clic peut contacter leur destination ; aucune
intégration sortante automatique additionnelle n'a été trouvée dans le code.


Les fichiers `source-redis.password` et `beta-redis.password` contiennent chacun
le seul mot de passe Redis, en `0600`. Le script compare les `run_id` des
serveurs dédiés avec les connexions réelles de l'application. Il purge les
anciennes sessions/files et le cache bêta en conservant atomiquement la clé
de maintenance. Il n'efface jamais Redis production. `beta.env` contient les
variables d'exécution Laravel résolues (pas les noms `BETA_*` du Compose),
avec le réseau Docker bêta dans `beta.network`. Le lancement ponctuel de
migration vérifie ses identités réelles avant d'agir.

Les fichiers de preuve, phases de livraison et attestations opérateur sont
décrits dans [la procédure de livraison](operations.md#livraisons-contrôlées-et-répétition-bêta).
