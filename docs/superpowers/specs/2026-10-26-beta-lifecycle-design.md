# Cycle de vie bêta — issue 306

Statut : conception mise en œuvre sur branche de travail ; administrateur dédié
validé par l’utilisateur. Installation et cycle réels à vérifier sur serveur.

## Objectif

Depuis GitHub Actions, initialiser la bêta avec une copie de production ou les
seuls référentiels, mettre à jour son candidat en conservant les essais, puis
l'arrêter sans supprimer ses volumes. Réutiliser la sélection Release Please,
les images par digest et les protections existantes. Aucune livraison production.

## Approche proposée

Étendre `beta.yml`, `deploy-beta.sh`, `beta-ssh-deploy.py` et les opérations serveur
existantes ; ajouter un workflow manuel d'arrêt. Garder le relais SSH restreint :
actions énumérées, cibles fixées par configuration privée, aucun argument shell,
chemin ou code fourni par GitHub. Retirer le déclenchement `workflow_run` :
l'issue n'exige aucune livraison automatique et une CI ne doit pas relancer la bêta.

Une chaîne parallèle utilisant un jeton Coolify plus large doublerait les contrôles
et les chemins de déploiement. Une procédure de commandes manuelles ne répondrait
pas au parcours nominal demandé. L'extension du relais est donc recommandée.

## Actions opérateur

- `update` : déployer le dernier candidat admissible et migrer explicitement,
  sans import ni purge des essais. Autorisé après un arrêt pour reprendre les
  données existantes, uniquement si la précédente préparation était complète.
- `initialize-production` : remplacer les essais par une copie complète neutralisée.
- `initialize-references` : remplacer les essais par les seuls référentiels et
  amorcer un administrateur propre à la bêta.
- `stop`, dans un second workflow : fermer puis arrêter l'application et ses
  dépendances exclusivement dédiées, sans suppression de ressource ni volume.

Les deux initialisations exigent un champ explicite confirmant le remplacement
des essais. Une absence de candidat fait échouer l'action avant toute mutation.
Les mises à jour obsolètes échouent lors de la revalidation avant mutation.

## Référentiels uniquement

Liste fermée proposée, d'après les modèles et migrations du dépôt : `roles`,
`avatars`, `interest_categories`, `interests`, `interest_settings`,
`conversation_starters`, `partner_settings`, `product_onboarding_settings`,
`seasonal_themes`. Conserver leurs identifiants pour les dépendances : intérêts
vers catégories et réglages du tutoriel vers avatars. Le registre `migrations`
accompagne le schéma source ; il n'est pas un catalogue métier.

Exporter le schéma complet sans données puis les seules tables autorisées à
partir d'un instantané cohérent. Ne jamais importer temporairement les utilisateurs
ou leurs activités dans ce mode. Vérifier les clés étrangères de la liste avant
copie et refuser une dépendance inattendue vers une table exclue.

Copier uniquement les objets désignés par `avatars.image_path`, avec contrôle
du chemin et du contenu ; aucun préfixe S3 général ni fichier personnel.
Vérifier l'absence de lignes dans toutes les tables exclues avant amorçage,
puis le seul compte administrateur attendu et ses relations d'accès.
Ne pas utiliser `DatabaseSeeder`, qui crée des comptes de démonstration.

### Accès administrateur proposé — décision en attente

Créer un compte synthétique administrateur avec une adresse configurée dans un
fichier privé serveur et un mot de passe aléatoire fort. Conserver les éléments
de connexion uniquement dans un fichier serveur `0600`, jamais dans les réponses
SSH, les artefacts ou les journaux Actions. Aucun utilisateur de production ni
mot de passe de démonstration. Documenter la récupération privée par l'opérateur.
Réutiliser les règles de majorité, statut, rôle et accès bêta existantes.
Ne pas régénérer le mot de passe lors d'une simple mise à jour ou reprise.

## Exécution et reprise

Les deux workflows partagent un groupe de concurrence sans annulation ; le
relais serveur tient le même verrou que les autres opérations durant tout le
parcours. Enregistrer des métadonnées privées persistantes : action, phase,
digest, commit et statut, sans données métier ni secrets.

Avant tout changement, identifier les ressources Coolify réelles, leur
environnement, les dépendances dédiées et les volumes. Refuser une ressource
partagée ou de production. Démarrer les dépendances dédiées nécessaires ;
maintenir l'application fermée par la maintenance Redis et drainer ses processus.
Un premier démarrage doit établir cette fermeture avant d'exposer l'application.

Pour une initialisation, réutiliser les validations MySQL/Redis/Garage et la
neutralisation existantes. Vérifier l'image, le commit et les métadonnées du
candidat ; appliquer les migrations explicitement hors entrypoints. Attendre
le déploiement Coolify, vérifier le digest réellement exécuté, les migrations,
la configuration sûre, les services et les accès, puis rouvrir seulement après
réussite. Les workers ne traitent aucune donnée importée avant neutralisation.

À l'arrêt, fermer et drainer avant d'arrêter les dépendances. Garage partagé,
proxy et ressources production restent actifs. L'arrêt conserve l'état préparé.
Après erreur, garder fermé et marquer l'état incomplet ; ne jamais supprimer
implicitement les essais pour réparer. Une reprise conservant les données
réexécute les étapes contrôlées ; un remplacement exige une nouvelle sélection
explicite d'initialisation. Ne pas effectuer de rollback de migrations automatique.

## Répétition de migration production

Une mise à jour bêta conservant les essais ne crée aucune attestation de
répétition. Invalider les preuves précédentes lorsqu'une opération change leur
copie ou candidat. La procédure production continue d'exiger une copie fraîche,
la double exécution des migrations et les parcours attestés. Mettre à jour
explicitement les documents qui assimilent aujourd'hui tout nouveau candidat
bêta à une répétition sur copie fraîche.

## Vérifications prévues

Adapter les tests Pest d'infrastructure et de neutralisation : les deux imports,
absence de données et fichiers personnels en mode référentiels, amorçage gardé,
mise à jour sans import ni purge, reprise après arrêt, conservation des volumes,
isolation, candidat dépassé, verrouillage, échecs et maintien de la fermeture.
Tester les commandes Laravel sur MySQL de test isolé avec un cycle rouge/vert.
Contrôler les workflows et les scripts existants avec doubles Coolify/Docker,
puis exécuter les contrôles PHP, frontend et Docker concernés.

## État observé et limites

Le 26 octobre 2026, `main` a été synchronisé sans changement local préexistant.
Les noms des variables GitHub nécessaires au relais sont présents. Aucun secret
n'a été lu. L'état actuel des ressources Coolify, les accès serveur et la
configuration privée de l'opérateur n'ont pas encore été vérifiés. Les relevés
d'infrastructure du 17 octobre ne constituent pas une preuve actuelle.

Avant activation réelle, vérifier les identités, la fermeture, les permissions,
Mailpit et la capacité du serveur. Toute initialisation réelle doit annoncer
la perte des essais bêta et recevoir l'autorisation correspondante. Aucun
remplacement ni arrêt réel n'a été exécuté dans ce travail de conception.

Décision du 26 octobre : administrateur bêta dédié validé par le propriétaire pour le mode référentiels.
