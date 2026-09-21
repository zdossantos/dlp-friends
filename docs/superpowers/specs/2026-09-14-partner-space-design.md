# Espace partenaire et annonces — conception

## Statut et objectif

Cette spécification formalise l'issue 199 et les décisions produit validées le
14 septembre 2026. Elle ajoute un rôle partenaire à privilèges minimaux, une
présentation publique modérée, des annonces partenaires consenties et des
statistiques agrégées, sans créer de moteur publicitaire générique.

Le partenaire ne reçoit jamais d'accès implicite à l'administration, aux
catalogues, aux membres, aux statistiques privées ni aux messages. Les droits
ordinaires restent exclusivement ceux conférés par ses autres rôles.

## Périmètre

Le périmètre comprend :

- le rôle cumulable `partner` et sa gestion auditée depuis la liste admin des
  utilisateurs ;
- une fiche partenaire bilingue avec brouillon, validation et version publiée ;
- l'affichage de six partenaires publiés au maximum sur les accueils français
  et anglais ;
- des annonces in-app préparées par un partenaire et approuvées par un
  administrateur ;
- un consentement membre distinct, désactivé par défaut ;
- un envoi asynchrone reprenable, concurrent et idempotent ;
- des statistiques agrégées de livraison, lecture, masquage et clic ;
- l'ajout de toutes les données personnelles concernées à l'export membre ;
- les écrans partenaire et les surfaces d'administration nécessaires.

Sont exclus : e-mail, web push, ciblage individuel, import d'audience, HTML
libre, pièces jointes, scripts, traduction automatique, facturation,
segmentation, statistiques publicitaires détaillées et place de marché.

## Rôles, accès et audit

`RoleName` reçoit la valeur `partner`. Le rôle est cumulable avec `user` et
`admin`, sans être implicite. Un compte qui possède uniquement `partner`
accède à l'espace partenaire mais pas aux fonctions sociales, qui exigent
toujours `user`. Un partenaire également administrateur conserve séparément
les droits de chaque rôle.

Seul un administrateur peut attribuer ou retirer `partner`. La liste admin des
utilisateurs expose pour chaque ligne une action « Gérer les rôles » ouvrant un
dialogue accessible. L'administrateur choisit les rôles autorisés et confirme
explicitement chaque changement. Les règles suivantes sont appliquées côté
serveur :

- `user` et `partner` peuvent être attribués ou retirés ;
- `admin` ne peut pas être attribué ou retiré depuis cette interface afin de
  conserver le processus privilégié existant en console ;
- l'acteur ne peut pas modifier ses propres rôles depuis la liste ;
- une requête sans changement reste sans effet et ne crée pas d'audit ;
- le retrait de `partner` révoque immédiatement l'accès partenaire, mais ne
  supprime aucune fiche, annonce, livraison ou trace nécessaire à l'audit.

Chaque changement effectif crée une entrée immuable comportant acteur, cible,
rôle, action `assigned` ou `removed` et heure serveur. Une Policy protège la
lecture et la mutation des rôles. Toutes les routes partenaire utilisent le
middleware de rôle et des Policies par ressource. Toutes les routes admin
restent réservées à `admin`, y compris pour un compte uniquement partenaire.

## Fiche partenaire et publication

Chaque compte partenaire possède au plus une fiche. La fiche porte l'état de
publication, la révision publiée courante et une position administrative. Les
contenus éditables sont conservés dans des révisions distinctes comprenant :

- nom français et nom anglais, obligatoires, avec une limite de 100 caractères ;
- description française et description anglaise, obligatoires, avec une
  limite de 500 caractères ;
- image transformée privée ;
- état `draft`, `pending_approval`, `approved` ou `rejected` ;
- auteur, décisionnaire, dates et motif de rejet facultatif.

Une seule révision modifiable en brouillon existe à la fois par fiche. Une
révision soumise devient immuable. Après un rejet, le partenaire crée une
nouvelle révision à partir du contenu rejeté. Une nouvelle soumission ne change
jamais la révision déjà publiée. Lorsqu'un administrateur approuve, la révision
devient la nouvelle version publique de façon transactionnelle. Il peut aussi
dépublier la fiche sans supprimer ses versions.

L'image est obligatoire avant la première soumission. Le serveur vérifie son
MIME réel, sa taille et ses dimensions, la réencode pour supprimer les
métadonnées et crée la variante nécessaire à l'accueil. Elle reste sur le
stockage privé et n'est diffusée que par une route contrôlée. L'interface
rappelle que le partenaire doit posséder les droits sur le visuel ;
l'approbation administrative matérialise le contrôle éditorial sans prétendre
constituer une preuve juridique autonome.

L'administration ordonne manuellement les fiches publiées. Les accueils Blade
français et anglais chargent côté serveur les six premières fiches publiées au
maximum, dans cet ordre, et utilisent uniquement la révision approuvée et la
langue de la page. Aucun repli automatique d'une langue vers l'autre n'est
nécessaire puisque les deux variantes sont obligatoires. Le rendu demeure
HTML-first, sans runtime Vue, navigable au clavier, lisible dès 320 px et
compatible avec les thèmes clair et sombre.

## Annonces et modération

Une annonce appartient à une fiche partenaire et comprend un titre limité à
80 caractères, un contenu texte limité à 500 caractères et une URL de
destination. Le contenu est saisi tel quel, sans traduction automatique ; une
annonce unique est donc affichée identiquement quelle que soit la langue du
membre. Le texte reste échappé et aucun HTML n'est accepté.

L'URL doit être absolue, utiliser `https`, contenir un hôte valide et ne pas
inclure d'identifiants incorporés. Les URL avec utilisateur ou mot de passe,
les adresses IP locales ou privées, `localhost`, et les schémas non HTTPS sont
refusés. La destination est vérifiée à la création, à la soumission et à
l'approbation. Le serveur ne récupère pas l'URL et ne suit pas ses redirections.

L'annonce suit les états :

- `draft` : modifiable et supprimable par le partenaire ;
- `pending_approval` : immuable, en attente d'une décision admin ;
- `approved` : approuvée et prête à lancer l'envoi ;
- `sending` : envoi commencé, non annulable ;
- `sent` : tous les destinataires préparés ont atteint un état terminal ;
- `rejected` : refusée avec motif facultatif ;
- `cancelled` : annulée avant le début de l'envoi.

Le partenaire peut soumettre, retirer un brouillon et annuler avant `sending`.
L'administrateur peut rejeter, approuver ou annuler avant `sending`. Une
modification après rejet crée un nouveau brouillon afin de préserver la
décision antérieure. Le titre, le contenu et l'URL sont figés à la soumission.

Le délai minimal entre deux envois commencés du même partenaire est un réglage
singleton administrable, exprimé en jours, avec 30 jours par défaut. Sa valeur
est comprise entre 1 et 365. L'approbation puis le démarrage vérifient ce délai.
La vérification décisive verrouille la fiche partenaire et les annonces
concernées dans une transaction afin que deux requêtes concurrentes ne puissent
pas le contourner.

## Consentement et audience

La préférence `partner_announcements` est distincte des notifications
fonctionnelles et désactivée par défaut pour tous les comptes existants et
nouveaux. Les réglages expliquent sa finalité et permettent de l'activer ou de
la retirer à tout moment. Le retrait bloque les nouvelles livraisons sans
effacer ni modifier les notifications déjà reçues.

Un destinataire est éligible seulement s'il :

- possède le rôle `user` ;
- a explicitement activé `partner_announcements` ;
- possède un compte actif, vérifié et non en attente de suppression.

L'éligibilité est évaluée lors de la préparation de l'envoi puis juste avant
chaque livraison. Le partenaire ne choisit jamais de membre et ne voit jamais
la liste d'audience. Les annonces partenaires constituent une catégorie
distincte du centre de notifications.

## Envoi fiable et idempotence

L'approbation ne diffuse rien dans la requête HTTP. Une action de démarrage
transactionnelle verrouille l'annonce et le partenaire, revalide l'état, le
délai et la destination, attribue un identifiant d'exécution unique, passe
l'annonce à `sending`, puis programme la préparation de l'audience après le
commit.

La préparation parcourt les membres éligibles par identifiant croissant et
crée une livraison unique pour chaque couple annonce–membre. Une contrainte
unique en base constitue la garantie finale contre les doublons. Les jobs de
livraison verrouillent leur ligne, revérifient l'éligibilité et le consentement,
puis créent au plus une notification Laravel. Les états de livraison sont
`pending`, `delivered`, `skipped` ou `failed`, avec le nombre de tentatives et
une erreur technique bornée ne contenant aucune donnée sensible.

Chaque livraison conserve aussi un instantané immuable du titre, du contenu et
de l'URL de destination, ainsi que l'identifiant public de l'annonce source.
Cet instantané ne contient ni identité de compte expéditeur, ni destinataire,
ni secret opérationnel. Sa relation vers l'annonce source devient nulle lorsque
cette dernière est purgée : l'historique du destinataire et son lien restent
alors utilisables jusqu'au propre cycle de suppression du destinataire.

La notification Laravel en base, le passage de la livraison à `delivered` et
la métrique de livraison sont validés atomiquement et au plus une fois. Une
reprise de livraison remet explicitement les échecs à `pending`, puis ré-enfile
toutes les livraisons `pending`. Elle ne recrée jamais une notification pour
une livraison `delivered`, et une livraison `skipped` n'est jamais rejouée. Les
jobs Laravel utilisent les tentatives et délais progressifs existants.

Le broadcast temps réel est une projection distincte, avec une garantie
**at-least-once**. Une livraison `delivered` conserve `broadcasted_at` à
`null` jusqu'à la confirmation de l'émission. Un crash peut survenir après
l'acceptation du broadcast par le transport mais avant l'enregistrement de
cette confirmation ; la reprise republie alors exactement le même UUID de
notification et le même payload. Le client ou le transport doit dédupliquer
ces relectures par UUID. La notification en base reste la source durable et le
broadcast ne fournit que l'immédiateté.

L'administration peut relancer les échecs d'une annonce et ré-enfiler les
broadcasts `delivered` non confirmés d'une annonce `sending` ou `sent`, sans
réinitialiser la livraison, recréer la notification ou modifier sa métrique.
Il n'existe pas d'option de renvoi global. L'annonce devient `sent` lorsque la
préparation est terminée et qu'aucune livraison traitable ne reste.

Les notifications stockent uniquement l'identifiant d'annonce, les clés de
présentation nécessaires et une cible interne. Elles n'incluent aucune donnée
personnelle, aucun message privé et aucune copie de contenu membre.

## Lecture, masquage et clic

Une notification partenaire est « vue » lorsqu'elle est marquée comme lue par
le mécanisme du centre. Elle peut être explicitement masquée par son
destinataire ; le masquage retire seulement cette notification de ses listes et
enregistre l'heure correspondante. Il ne modifie pas les autres catégories.

Le bouton d'action utilise une route interne contenant un jeton aléatoire
opaque propre à la livraison. Le contrôleur retrouve la livraison, enregistre
atomiquement le clic, puis effectue une redirection HTTP vers l'URL HTTPS figée
de l'annonce. Le premier clic renseigne `first_clicked_at` et augmente le
compteur de clics uniques une seule fois ; chaque passage augmente le compteur
total. Aucun paramètre identifiant le membre n'est ajouté à la destination.

Lecture, masquage et clic mettent à jour dans la même transaction la livraison
et les agrégats de l'annonce. Des contraintes et mises à jour atomiques
empêchent un double comptage unique sous concurrence.
Lorsque l'annonce et ses agrégats ont atteint leur échéance, les interactions
restent enregistrées sur la livraison du destinataire sans recréer de métrique.

## Statistiques

Le partenaire et l'administrateur voient par annonce :

- nombre de destinataires préparés ;
- notifications livrées ;
- notifications vues ;
- notifications masquées ;
- clics uniques ;
- clics totaux ;
- taux de lecture, de masquage et de clic unique, calculés sur les livraisons.

Les partenaires ne voient que leurs propres annonces. Aucun écran, export ou
réponse ne révèle une identité, une adresse, un appareil, une segmentation ou
un historique individuel de destinataire. Les administrateurs voient les mêmes
agrégats et, séparément, les nombres opérationnels `pending`, `failed` et
`skipped` nécessaires à la reprise.

Les agrégats sont conservés deux ans après la fin de l'envoi, puis purgés par
une tâche planifiée idempotente. Les lignes identifiables suivent la durée de
vie du compte destinataire et sont supprimées avec lui ; les agrégats déjà
constitués ne sont pas décrémentés. Les traces de rôle et de modération sont
également limitées à deux ans, sauf obligation légale ultérieure documentée.

## Interfaces

L'espace partenaire Inertia contient :

- « Ma fiche » : état de la version publique, brouillon bilingue, image,
  soumission et décisions ;
- « Mes annonces » : création, soumission, annulation, états et historique ;
- « Statistiques » : cartes agrégées et tableau par annonce.

La navigation partenaire apparaît seulement avec `partner`. La disparition du
rôle la retire immédiatement au prochain partage Inertia, mais la sécurité
repose toujours sur Laravel.

L'administration ajoute :

- le dialogue de rôles depuis chaque ligne de la liste des utilisateurs ;
- une file de validation des fiches et leur ordre public ;
- une file de validation des annonces ;
- le délai minimal d'envoi ;
- les statistiques et commandes de reprise autorisées.

Les dialogues et formulaires respectent le focus, les libellés, les erreurs
reliées aux champs, les cibles tactiles de 44 px et les annonces accessibles.
Tous les textes système, validations, confirmations, états et libellés sont
présents en français et en anglais dans les catalogues existants.

## Export personnel et suppression

L'export JSON direct existant reçoit des sections versionnées :

- `notification_preferences` avec la valeur et la date de modification du
  consentement partenaire ;
- pour le destinataire, ses annonces reçues et ses propres dates de livraison,
  lecture, masquage, premier clic et nombre de clics ;
- `role_history` pour les changements dont le compte est la cible, sans données
  superflues sur l'acteur ;
- pour un partenaire, sa fiche, toutes ses révisions, ses annonces, décisions,
  états d'envoi et statistiques agrégées ;
- aucune livraison ni interaction individuelle appartenant à autrui.

La demande de suppression rend immédiatement invisibles les fiches du
partenaire et annule ses annonces `draft`, `pending_approval` ou `approved`.
Une annonce déjà `sending` n'accepte plus de nouvelles livraisons et ses jobs
restants deviennent `skipped`. La purge différée supprime préférences,
révisions non nécessaires, images privées et livraisons dont le compte supprimé
était destinataire. Les livraisons appartenant à d'autres destinataires ne sont
pas supprimées avec l'expéditeur : elles perdent leur relation à l'annonce à
l'échéance de celle-ci et gardent seulement l'instantané reçu. Les agrégats
anonymes et audits minimaux restent jusqu'à leur échéance de deux ans. Une
révision encore publiée est toujours exclue de la purge, même si son
`expires_at` est dépassé ; seul l'historique non actif expire.

## Erreurs et cohérence

Les Form Requests gèrent les validations HTTP ; les Policies refusent les
accès ; les Actions transactionnelles portent publication, approbation,
démarrage et statistiques. Une erreur d'image ne remplace jamais la révision
publique. Une transaction échouée ne crée ni notification ni compteur.

Les mutations concurrentes du cycle partenaire prennent leurs verrous dans
l'ordre global `user` → `partner_profile` → `partner_announcement` →
`partner_announcement_delivery` → `partner_announcement_metric`. Les verrous
singleton de classement précèdent le jeu ordonné des fiches dans les seules
actions de modération concernées. L'approbation verrouille et revalide le
propriétaire actif avant de publier une révision.

Les conflits d'état et de délai renvoient une erreur métier localisée sans
révéler l'existence d'une ressource inaccessible. Les erreurs techniques de
file sont bornées, observables par les administrateurs et reprenables. Aucun
contenu sensible, jeton de clic ou corps d'annonce n'est journalisé.

## Tests et critères de livraison

Les tests Pest couvrent :

- ajout du rôle, cumul, middleware, Policies et retrait immédiat ;
- attribution depuis la liste, confirmations, interdiction d'auto-modification
  et audit ;
- validation bilingue, images, brouillon immuable, approbation, dépublication,
  ordre et limite de six ;
- URL HTTPS, limites textuelles, transitions d'annonce et délai configurable ;
- consentement par défaut, retrait et exclusions d'audience ;
- transactions concurrentes, contraintes uniques, reprise partielle, absence
  de double notification persistante et replay du broadcast avec le même UUID
  après un crash avant confirmation ;
- lecture, masquage, clics total et unique sous concurrence ;
- statistiques sans identité, rétention, suppression et export personnel.

Les tests navigateur couvrent l'espace partenaire, la gestion admin des rôles,
les validations de fiches et annonces, les statistiques, le consentement et le
rendu public français/anglais en clair, sombre, mobile et ordinateur. Les pages
publiques sont également vérifiées par des assertions HTML SSR, accessibilité,
SEO et absence du runtime applicatif.

La livraison exige les tests ciblés pendant chaque cycle rouge–vert, puis les
contrôles PHP, TypeScript, lint, formatage, build et suites Pest concernées. Les
documents `PRD.md`, `data-model.md`, `technical-architecture.md`,
`security-privacy.md`, `design-system.md` et l'inventaire d'implémentation sont
mis à jour avec le comportement réellement livré.
