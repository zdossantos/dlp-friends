# Environnement bêta privé

État : application bêta déployée avec MySQL, Redis et Mailpit dédiés ;
contrôles HTTP et WebSocket sur comptes synthétiques réalisés, y compris la
révocation du rôle admin sur une session et un socket existants. L'automatisation
reste à terminer. Aucune donnée de production copiée
et aucune répétition sur cette copie encore réalisée.
La cible approuvée est décrite dans la [spécification](superpowers/specs/2026-10-17-isolated-release-beta-design.md).

## Capacité avant activation

Relevé en lecture seule le 17 octobre 2026 à 06:49 UTC sur le serveur Coolify
prévu : 7 894 Mio de RAM totale, 1 120 Mio disponibles, aucun swap, 6 CPU,
charge moyenne 8,49 / 5,13 / 4,73 et disque racine à 88 % (30 Gio libres).
Ce relevé précède le retrait des services médias demandé par l'opérateur.

Le 17 octobre à 10:27 UTC, après arrêt de Bazarr, RomM et Streamystats avec
leurs deux bases et le worker Streamystats, la mémoire disponible atteint
2 190 Mio, soit 1 070 Mio de plus. La charge moyenne est de 1,07 / 2,35 /
3,25. Les six conteneurs ont ensuite été retirés sans suppression de volumes ;
les sept définitions Compose (dont le service ponctuel de migration
Streamystats) sont retirées de la configuration Coolify enregistrée et relue.
Les données restent dans `/opt/nas/{bazarr,romm,streamystats}` ; une copie
privée du Compose précédent est conservée sur le serveur. Aucun redémarrage
global de la stack médias n'a été nécessaire. Les quatre processus DLP Friends,
MySQL et Redis sont restés sains, `/up` répond HTTP 200 et l'accueil HTTP 302.

À la demande de l'opérateur (usage seul, lenteur acceptable), les limites
applicatives bêta sont réduites à **480 Mio et 0,80 CPU cumulés** : web
192 Mio, worker 128 Mio (recyclage à 96 Mio), scheduler et Reverb 80 Mio chacun.
Ce sont des plafonds, pas une mesure de consommation ni une preuve de tenue
en charge. Vérifier les limites Docker réellement appliquées et l'absence
d'OOM pendant les parcours avant activation ; ne pas relever les plafonds
automatiquement après un échec.

MySQL 8.4, Redis 7.4, Mailpit, les imports et une réserve pour les services
existants doivent encore tenir en mémoire. Moins de 500 Mio pour l'ensemble,
base incluse, n'est pas démontré. La capacité libérée permet de reprendre la
préparation ; confirmer les budgets des ressources dédiées et la réserve
observable avant leur démarrage, puis mesurer les parcours. Aucun achat
d'hébergement n'a été effectué.

Consommations instantanées observées (Mio, arrondies) :

| Service                                      |     RAM |
| -------------------------------------------- | ------: |
| Quatre MySQL, dont celui de DLP Friends      |   1 784 |
| Coolify, hors proxy/base/Redis               |     395 |
| Jellyfin                                     |     351 |
| RomM et sa base                              |     443 |
| Streamystats, sa base et son worker          |     499 |
| Sonarr, Radarr, Bazarr, Prowlarr             |     695 |
| DLP Friends : web, worker, scheduler, Reverb |     241 |
| DLP Friends : MySQL / Redis                  | 551 / 5 |

Les deux premières mesures MySQL ne sont pas simultanées (544 puis 551 Mio
pour DLP Friends). Les lignes regroupées se recoupent et ne doivent pas être
additionnées. Les conteneurs utilisent environ 5 459 Mio au total ; l'hôte et
ses autres processus utilisent aussi de la mémoire. Ce tableau précède le
retrait explicite des services médias décrit ci-dessus.
Même limitée, la bêta partage CPU, disque et noyau avec la production : les
plafonds réduisent le risque sans garantir une absence totale d'impact.

Restent à vérifier sur l'infrastructure : ressources et secrets dédiés,
contrôle applicatif administrateur sur HTTP/médias/canaux privés, Mailpit privé, DNS/TLS,
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

À la demande de l'opérateur, aucune restriction par adresse IP n'est prévue.
En `APP_ENV=beta`, Laravel exige un compte actif avec le rôle `admin` pour
les pages, médias servis par l'application et autorisations de canaux privés.
La connexion, la double authentification et la récupération du mot de passe
restent accessibles ; les inscriptions sont refusées. Les rôles et le statut
sont relus à chaque requête. Reverb les recontrôle avant chaque livraison
privée : retirer `admin` dans la base bêta révoque aussi les abonnements déjà
ouverts. Les contrôles métier habituels restent applicables aux administrateurs.
Les fichiers publics de l'interface, `robots.txt` et `/up` restent accessibles.
Le bucket reste privé, sans URL publique contournant Laravel ; Mailpit reste
sur le réseau interne sans port ni domaine public.
Vérifier les refus visiteur/membre/partenaire, les accès administrateur et la
révocation sur HTTP, médias et canaux privés **avant toute donnée réelle**.
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

## Mesure après déploiement sur comptes synthétiques

Le 17 octobre 2026, après les essais HTTP et WebSocket, les processus bêta
consomment 61,62 Mio (web), 44,88 Mio (Reverb), 37,48 Mio (scheduler) et
37,32 Mio (worker), soit 181,30 Mio. Les dépendances dédiées consomment
220,1 Mio (MySQL), 5,488 Mio (Redis) et 7,273 Mio (Mailpit) : environ
414 Mio au total observés. Cette mesure précède l'import de données réelles
et ne constitue pas une garantie de consommation maximale.

Les plafonds effectifs sont 480 Mio pour l'application, 384 Mio pour MySQL,
48 Mio pour Redis et 32 Mio pour Mailpit, soit 944 Mio cumulés. Aucun processus
applicatif bêta n'a été tué par manque de mémoire pendant ces contrôles.
L'hôte dispose alors de 1 824 Mio disponibles ; les quatre processus
production sont sains depuis cinq jours et `/up` répond HTTP 200.

## Déploiement automatique limité à la bêta

Le workflow peut utiliser un compte SSH `dlp-beta-deploy` au lieu d'un jeton
Coolify d'équipe. Configurer `BETA_SSH_HOST` et `BETA_SSH_KNOWN_HOSTS` dans les
variables GitHub, et la clé privée dédiée dans `BETA_SSH_PRIVATE_KEY`. La clé
d'hôte doit être relevée sur le serveur par un canal déjà authentifié ; le
client refuse une clé inconnue ou modifiée. Ne jamais utiliser la clé SSH
personnelle de l'opérateur. Le chemin API existant reste disponible si aucun
hôte SSH n'est configuré, avec un jeton limité à une équipe bêta isolée.

Sur le serveur, installer une copie relue de `beta-ssh-deploy.py`, appartenant
à root et non modifiable par le compte de déploiement. Le fichier de
configuration privé `0600`, dans un répertoire root `0700`, contient :

```json
{
    "application_uuid": "UUID_APPLICATION_BETA",
    "production_application_uuid": "UUID_APPLICATION_PRODUCTION",
    "lock_file": "/srv/dlp-operations/private/beta-deploy.lock"
}
```

La clé publique GitHub doit être limitée dans `authorized_keys` par
`restrict,command="sudo -n /usr/local/sbin/dlp-beta-deploy"`. Le compte a un
mot de passe verrouillé, aucun autre accès et ne peut modifier ni son dossier
SSH, ni sa clé autorisée, ni le script. Le wrapper root refuse tout argument,
fixe `PATH` à `/usr/sbin:/usr/bin:/sbin:/bin`, applique `umask 077`, puis lance
Python avec le chemin absolu du script et de sa configuration. La règle
sudoers n'autorise que ce wrapper **sans argument** (syntaxe `""`), sans
`SETENV` ; la vérifier avec `visudo -cf` avant installation.

Le relais accepte uniquement un petit JSON avec l'UUID bêta fixé, un SHA Git
complet et un digest de l'image GHCR du projet. Il vérifie aussi que
l'application Coolify appartient à l'environnement `beta`, utilise le bon
dépôt et n'a aucune livraison active. Seuls `APP_IMAGE`, le commit, la branche du candidat et la
file de déploiement de cette application sont modifiés. Aucune migration,
copie de données ou reprise de maintenance ne peut être demandée par la clé.
Le contrôle du candidat Release Please reste exécuté juste avant l'appel SSH.
Ce mécanisme ne lance aucun service permanent supplémentaire.

Coolify 4.3.23 recharge le Compose depuis le HEAD de la branche configurée,
même si un SHA est indiqué. Le workflow crée donc une branche
`chore/beta-candidates/<SHA>` pour chaque candidat et vérifie son SHA avant
l'appel SSH. Il ne la déplace ni ne la force jamais ; une référence existante
incohérente fait échouer le déploiement. Le relais sélectionne cette branche.
Le job de déploiement a besoin de `contents: write` uniquement pour créer cette
référence ; le build du candidat reste séparé des scripts de confiance.
Ces branches techniques ne créent ni tag ni release ; leur nettoyage reste
une opération explicite après retrait des candidats de Coolify.

Avant d'activer le relais, un ruleset GitHub actif doit interdire les mises à
jour, suppressions et force pushes sur `refs/heads/chore/beta-candidates/**`,
sans acteur exempté ; la création reste autorisée. Cette protection ferme
l'intervalle entre la vérification du SHA et la lecture du Compose par Coolify.
Le ruleset `Immutable beta candidate branches` a été activé le 17 octobre 2026.
Son retrait nécessite de suspendre le déploiement automatique. Le nettoyage
des anciennes références exige une intervention explicite sur cette protection.
