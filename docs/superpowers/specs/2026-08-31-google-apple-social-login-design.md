# Connexion sociale Google et Apple — conception

## Objectif

Permettre aux membres de créer un compte et de se reconnecter avec Google ou
Apple, sans contourner la majorité, l'état actif du compte ni la vérification
de l'adresse e-mail. La fonctionnalité reste limitée à ces deux fournisseurs
et ne livre pas de gestion avancée de plusieurs identités.

## Dépendances et intégration

Google utilise le fournisseur natif de `laravel/socialite`. Apple utilise
`socialiteproviders/apple`, enregistré avec le mécanisme d'extension Socialite
prévu pour Laravel 11 et versions ultérieures. Cette combinaison évite un
adaptateur Apple maison et conserve une surface OAuth homogène et simulable
dans les tests Pest.

Les identifiants clients, secrets, clés et URI de callback proviennent
exclusivement de variables d'environnement référencées par
`config/services.php`. `.env.example` documente les noms attendus sans contenir
de secret. L'intégration ne journalise ni réponse brute du fournisseur, ni
access token, ni refresh token.

## Routes et interface

Chaque fournisseur autorisé, `google` ou `apple`, expose une route publique de
redirection et une route publique de callback. Le fournisseur est résolu par
une liste fermée ; toute autre valeur est rejetée avant tout appel à Socialite.
Les callbacks utilisent la protection d'état OAuth de Socialite et sont limités
en fréquence.

Les pages de connexion et d'inscription affichent les deux mêmes boutons. Leur
contenu visible se limite au logo du fournisseur et à son nom, « Google » ou
« Apple ». Les logos sont sobres, monochromes, accessibles, et ne remplacent
pas le libellé textuel. Tous les libellés, erreurs et attributs accessibles
utilisent les catalogues français et anglais existants.

Une page Inertia distincte complète la première connexion sociale. Elle ne
demande que la date de naissance, donnée que Google et Apple ne fournissent pas
de manière exploitable. Aucune donnée sociale sensible n'est exposée dans ses
props.

## Modèle de données

La table `social_accounts` contient :

- une clé primaire ;
- `user_id`, avec suppression en cascade ;
- `provider`, limité par l'application à `google` ou `apple` ;
- `provider_user_id` ;
- les horodatages Laravel.

Une contrainte unique sur `(provider, provider_user_id)` garantit qu'une
identité externe ne peut être liée qu'une fois. Aucun token OAuth n'est stocké.
Le modèle `User` expose la relation avec ses comptes sociaux et le modèle
`SocialAccount` reste centré sur cette liaison.

## Première connexion

Le callback récupère l'identité avec Socialite et exige un identifiant stable.
Pour une identité encore inconnue, il exige aussi une adresse e-mail dont la
fiabilité est établie par le callback validé du fournisseur. Une adresse relais
privée Apple est une adresse valide. Cette preuve remplace la vérification par
e-mail de DLP Friends : aucun message de confirmation supplémentaire n'est
envoyé.

Le serveur place temporairement en session uniquement le fournisseur,
l'identifiant fournisseur et l'adresse e-mail normalisée. Il ne conserve aucun
token. Il redirige ensuite vers le formulaire de date de naissance.

La soumission valide une date obligatoire et une majorité de 18 ans au jour de
la requête. Dans une transaction, l'application revérifie les conflits, crée
un utilisateur `active` avec `email_verified_at` renseigné, lui attribue le
rôle `user`, puis crée la liaison sociale. Le compte reçoit un mot de passe
aléatoire non communiqué afin de respecter le schéma existant ; ce mot de passe
n'est ni journalisé ni exposé. La session OAuth temporaire est consommée, le
membre est authentifié avec régénération de session puis redirigé vers le flux
existant de complétion du profil.

Une validation refusée, notamment pour minorité, ne crée aucun utilisateur ni
liaison sociale.

## Reconnexion et éligibilité

Quand `(provider, provider_user_id)` existe déjà, le callback réutilise
directement le compte lié. Il n'exige pas une nouvelle vérification d'e-mail :
le compte possède déjà `email_verified_at`. Un compte dont le statut n'est pas
`active` n'est pas reconnecté.

Après authentification, le flux existant reste la source de vérité : le membre
passe par la destination authentifiée, puis par les middlewares `verified` et
`social`, la complétion du profil et le tutoriel. La connexion sociale ne crée
aucun accès alternatif aux fonctionnalités sociales.

## Conflits et erreurs

Une identité inconnue dont l'adresse appartient déjà à un utilisateur provoque
un conflit explicite. Elle n'est jamais rattachée automatiquement, afin qu'une
preuve de contrôle du fournisseur ne suffise pas à prendre le contrôle d'un
compte DLP Friends existant. Le message localisé invite la personne à utiliser
sa méthode de connexion actuelle.

La création transactionnelle traite également le cas où l'identité est liée
concurremment. Elle ne crée ni doublon ni compte orphelin. Annulation chez le
fournisseur, état OAuth invalide, identité incomplète, e-mail indisponible,
configuration absente et compte inactif produisent des messages utilisateur
explicites, sans inclure de détail technique ou de donnée fournisseur.

Les erreurs attendues reviennent vers la page de connexion. Les exceptions
inattendues peuvent être journalisées avec un contexte minimal composé du nom
du fournisseur et d'une catégorie d'erreur, jamais avec les paramètres du
callback, les objets Socialite ou les tokens.

## Tests et vérifications

Les tests Pest utilisent les faux fournisseurs Socialite pour Google et Apple
et couvrent au minimum :

- les redirections des deux fournisseurs et le rejet d'un fournisseur inconnu ;
- la première connexion et l'affichage du formulaire de date de naissance ;
- le refus d'un mineur sans création partielle ;
- la création d'un compte adulte, actif et déjà vérifié ;
- l'attribution du rôle `user` et la redirection vers le profil obligatoire ;
- la reconnexion des deux fournisseurs vers le même compte lié ;
- le conflit avec une adresse e-mail existante ;
- le conflit concurrent d'une identité déjà liée ;
- le refus d'un compte inactif ;
- le callback annulé ou invalide et l'absence d'e-mail fiable ;
- l'absence de persistance des tokens et leur absence des réponses.

Un test de schéma confirme les colonnes, la clé étrangère et l'unicité de
`social_accounts`. Les tests frontend et le build vérifient la présence des
deux boutons accessibles et les traductions. Les contrôles ciblés précèdent les
contrôles PHP et frontend complets concernés.

## Documentation et état produit

La livraison met à jour la matrice du PRD de « Planifié » à « Implémenté »,
l'inventaire de preuves, le modèle de données et l'architecture technique.
Elle documente les variables d'environnement et les URI de callback requises
pour Google et Apple sans inclure de valeur de production.

## Hors périmètre

- fournisseurs autres que Google et Apple ;
- rattachement manuel ou suppression d'identités depuis les réglages ;
- rattachement automatique à un compte existant portant le même e-mail ;
- stockage ou utilisation d'access tokens ou refresh tokens ;
- récupération d'un mot de passe pour un compte exclusivement social ;
- automatisation opérationnelle du renouvellement des secrets Apple au-delà de
  la configuration prise en charge par le fournisseur retenu.
