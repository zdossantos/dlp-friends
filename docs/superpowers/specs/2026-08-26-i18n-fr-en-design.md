# Internationalisation française et anglaise — conception

## Objectif et périmètre

DLP Friends prend en charge le français (`fr`) et l’anglais (`en`). Le français est la langue de référence et de secours. La locale doit rester identique entre Laravel et chaque rendu ou navigation Inertia.

La fonctionnalité couvre tous les textes système visibles et générés : pages publiques, authentification, onboarding, profil, découverte, réglages, administration, navigation, attributs accessibles, validations, notifications, toasts et e-mails. Elle ne traduit jamais les contenus saisis par les membres.

Les catégories d’intérêts restent un détail technique MVP : elles ne gagnent ni traduction ni interface. Les intérêts visibles disposent d’un nom français et d’une traduction anglaise facultative ; le nom français est employé si celle-ci manque.

## Résolution et persistance de la locale

Une liste unique de locales supportées (`fr`, `en`) et de la locale par défaut est définie côté Laravel. Un middleware web dédié sélectionne la locale avant le rendu des contrôleurs, des validations, des notifications et d’Inertia selon l’ordre suivant :

1. `users.locale` du membre connecté ;
2. cookie navigateur `locale` issu d’un choix explicite ;
3. première locale supportée fournie par `Accept-Language` ;
4. français.

Le middleware normalise les sous-tags tels que `fr-FR` et `en-GB`. Seules les valeurs autorisées sont acceptées. Il appelle `app()->setLocale()` et configure Carbon afin que le backend rende la même locale que le frontend.

Un endpoint de changement de langue valide la locale. Il pose le cookie pour les visiteurs ; pour un membre connecté, il enregistre aussi la préférence sur le compte. La réponse redirige vers la page précédente pour qu’Inertia reçoive immédiatement les nouvelles traductions. Les cookies restent signés, `SameSite=Lax`, sécurisés en HTTPS et limités aux deux valeurs autorisées.

## Données et interface

Une migration ajoute `users.locale`, nullable, et `interests.name_en`, nullable. Les modèles, factories, seeders, requêtes de validation et la représentation Inertia des intérêts exposent ces valeurs. Un accesseur centralise le libellé visible de l’intérêt en fonction de la locale active, avec fallback français.

`HandleInertiaRequests` partage la locale active et le catalogue de messages frontend déjà résolu par Laravel. Vue reçoit un composable `useTranslations()` et une fonction `t(key, replacements?)`; il ne détermine jamais une locale ni ne possède de second catalogue. Les clés sont structurées par domaine et les deux catalogues français/anglais restent versionnés avec les traductions Laravel. Les composants et pages remplacent leurs chaînes système par cette fonction.

Un même sélecteur accessible est réutilisé dans les layouts public et d’authentification, ainsi que dans les réglages du compte. Il expose un libellé, le choix courant, des options dans leur langue et un retour de statut lisible par lecteur d’écran. Il est entièrement exploitable au clavier.

Les dates et heures utilisent les outils `Intl` du navigateur avec la locale partagée ; les pluriels utilisent les entrées de traduction Laravel. Les textes des e-mails et notifications sont fournis par les catalogues Laravel dans la locale du destinataire, établie par le middleware pour les actions HTTP et par la préférence persistée pour les notifications mises en file.

## Erreurs et contrôle qualité

Les règles de validation utilisent des clés de traduction Laravel et non des phrases codées en dur. Les toasts passent des clés et remplacements plutôt que des messages déjà traduits lorsque la réponse peut déclencher une navigation Inertia dans une autre locale.

Les tests couvrent la détection `Accept-Language`, les quatre priorités, le cookie visiteur, la persistance sur le compte et sa restauration, le changement immédiat via Inertia, les validations et notifications localisées, ainsi que le fallback français des intérêts. Les tests navigateur vérifient les deux langues et l’accessibilité du sélecteur. Une règle ESLint ciblée ou un contrôle de recherche de chaînes est ajouté aux vérifications projet pour signaler les nouveaux textes système Vue non passés par `t()`.

Les migrations sont compatibles avec la version précédente : les nouvelles colonnes sont nullable et l’affichage conserve le français tant qu’aucune traduction ou préférence n’existe.
