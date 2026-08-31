# Design system DLP Friends

## Responsabilité et relation avec le PRD

Ce document est la source de vérité du langage visuel et des règles
d’interface de DLP Friends. Le contrat produit et les parcours attendus sont
définis dans le [`PRD.md`](PRD.md) ; l’implémentation des tokens se trouve dans
`resources/css/app.css` et les primitives réutilisables dans
`resources/js/components/ui/`.

Une règle décrite comme actuelle doit être observable dans l’interface. Une
orientation qui n’est pas encore livrée est explicitement présentée comme une
cible.

## Principes visuels

- Une ambiance contemporaine, chaleureuse et légèrement magique, sans imiter
  l’identité officielle de Disney.
- Des surfaces neutres chaudes qui laissent le violet porter les actions
  principales et le turquoise doux les actions secondaires.
- Le doré reste rare : il souligne un match, un badge ou un état important et
  ne devient pas une couleur d’action générale.
- Une hiérarchie simple, des cartes généreusement arrondies et des ombres
  discrètes donnent de la profondeur sans surcharger les parcours.
- La couleur ne constitue jamais le seul moyen de comprendre une action, une
  sélection ou une erreur.

## Tokens de couleur et thèmes

Les composants utilisent les tokens sémantiques plutôt que des couleurs brutes.
Les valeurs de référence sont définies en HSL dans `resources/css/app.css`.

| Token | Thème clair | Thème sombre | Usage |
| --- | --- | --- | --- |
| `background` | neutre chaud très clair, `hsl(32 40% 98%)` | violet profond, `hsl(258 30% 8%)` | Fond global |
| `foreground` | violet presque noir | neutre chaud très clair | Texte principal |
| `card` / `popover` | blanc chaud | violet sombre | Surfaces élevées |
| `primary` | violet `hsl(263 63% 46%)` | violet clair `hsl(265 80% 72%)` | Action et focus principaux |
| `secondary` | rose poudré pastel, `#F3B6CF` | vieux rose doux, `#D58EAA` | Actions secondaires et mises en valeur significatives |
| `accent` | violet neutre très clair, `hsl(258 24% 94%)` | violet neutre sombre, `hsl(258 18% 19%)` | Survols, menus et interactions discrètes |
| `muted` | neutre chaud | violet neutre | Surfaces et textes secondaires |
| `destructive` | rouge soutenu | rouge clair | Action ou état destructif |
| `border`, `input`, `ring` | variantes adaptées au thème | variantes adaptées au thème | Séparation, champs et focus |

Les thèmes disponibles sont `light`, `dark` et `system`. La préférence est
persistée, et la classe `.dark` active l’ensemble cohérent de tokens sombres.
La palette d'interface partagée n'utilise pas de rose. Toute nouvelle couleur
doit d’abord recevoir une fonction sémantique ; une valeur locale n’est
acceptable que pour une illustration, une couleur d'avatar administrable ou un
état métier réellement spécifique.

## Typographie, espacement, rayons et ombres

### Choix typographique

La combinaison retenue est **Instrument Sans** pour la lecture et **Cinzel
Decorative** pour une accentuation éditoriale rare. Les deux familles sont
distribuées sous SIL Open Font License 1.1, qui autorise leur intégration et
leur redistribution avec l’application. Leurs sous-ensembles latins couvrent
les caractères, accents, apostrophes et ponctuations nécessaires au français
et à l’anglais.

| Combinaison évaluée | Qualités | Limites | Décision |
| --- | --- | --- | --- |
| Instrument Sans seule | Très lisible, sobre et économique | Identité peu différenciée sur les moments de marque | Écartée : elle ne répond pas au besoin d’accentuation |
| Instrument Sans + Bricolage Grotesque | Chaleureuse, expressive et cohérente avec une interface contemporaine | Deux sans serif créent une distinction moins immédiate et Bricolage est plus agitée dans les grands titres | Écartée au profit d’un contraste plus calme |
| Instrument Sans + Fraunces | Lecture fonctionnelle stable et serif douce adaptée aux grands titres | Expression trop éditoriale et insuffisamment distinctive pour la marque | Écartée au profit d’une accentuation plus décorative |
| Instrument Sans + Cinzel Decorative | Lecture fonctionnelle stable, contraste marqué et identité forte sur les moments de marque | Les formes décoratives perdent en lisibilité dans les petites tailles et les contenus denses | Retenue avec un périmètre strict et une graisse forte |

Références de licence et de couverture :
[Instrument Sans](https://github.com/Instrument/instrument-sans),
[Cinzel Decorative](https://github.com/NDISCOVER/Cinzel) et
[Bricolage Grotesque](https://github.com/ateliertriay/bricolage). Les notices
et le texte OFL des deux familles distribuées sont conservés dans
[`THIRD_PARTY_FONTS.md`](../THIRD_PARTY_FONTS.md).

### Tokens et règles d’usage

| Token | Famille et replis | Graisses chargées | Usage |
| --- | --- | --- | --- |
| `font-sans` | Instrument Sans, repli sans serif métriquement ajusté, polices système | 400, 500, 600 | Corps, formulaires, navigation, messages, tableaux et majorité des titres |
| `font-accent` | Cinzel Decorative, repli serif, Georgia, serif | 700 | Nom de marque dans les signatures visuelles et grand titre éditorial de l’accueil |

- Le texte courant part de `1rem` avec une hauteur de ligne de `1.5`; les
  petites annotations restent au minimum à `0.75rem` avec une hauteur de ligne
  suffisante pour le zoom et le repli.
- Les titres fonctionnels utilisent Instrument Sans en 600 avec
  l’interlettrage resserré déjà établi. Le grand titre d’accueil et le nom de
  marque utilisent Cinzel Decorative 700 ; le grand titre va de `2.25rem` à
  `3.75rem`, avec la hauteur de ligne Tailwind correspondante et sans italique.
- Cinzel Decorative est interdite dans les paragraphes, petites tailles, formulaires,
  contrôles, erreurs, données, tableaux, navigation et conversations. La
  typographie ne porte jamais seule le sens ou l’état d’un contrôle.
- Les fichiers sont récupérés au build puis servis avec les assets de
  l’application, sans requête vers un fournisseur de polices en production.
  Seuls les poids et le sous-ensemble latin utilisés sont produits ; seul
  Instrument Sans 400, critique pour le texte courant, est préchargé. Les
  autres WOFF2 sont chargés à l’usage et le WOFF reste disponible pour
  compatibilité. `font-display: swap` garantit un texte visible. Le repli
  d’Instrument Sans est métriquement ajusté ; Cinzel Decorative conserve
  volontairement un repli serif (`ui-serif`, Georgia) pour préserver le
  caractère décoratif en cas d’indisponibilité.

### Espacement, rayons et ombres

- L’espacement suit l’échelle Tailwind existante. Les interfaces rapprochent
  les éléments d’un même groupe et séparent clairement les groupes distincts.
- Le rayon sémantique de base vaut `0.875rem`; `sm`, `md` et `lg` en dérivent.
  Les cartes de profil et contrôles majeurs peuvent utiliser des rayons plus
  généreux déjà établis par les composants métier.
- Les ombres restent légères et teintées par `primary` lorsque cela renforce la
  hiérarchie. Elles ne remplacent jamais une bordure ou un contraste utile.

## Composants et états

Réutiliser dans cet ordre :

1. les primitives déjà présentes dans `resources/js/components/ui/` ;
2. Reka UI lorsqu’une primitive accessible manque ;
3. un composant métier ciblé lorsqu’une interaction propre à DLP Friends ne
   peut pas être composée simplement.

Le socle comprend notamment boutons, champs, sélecteurs, cases à cocher,
cartes, dialogues, feuilles latérales, menus, badges, alertes, info-bulles,
navigation et retours de chargement. Les composants métier couvrent notamment
la carte de découverte, le carrousel d’avatars, la présentation du profil, le
match, la conversation et le blocage.

Chaque contrôle interactif prévoit les états normal, survol, focus visible,
actif ou sélectionné, désactivé, chargement et erreur lorsque ces états sont
pertinents. Une action destructrice utilise le token `destructive` et une
confirmation explicite. Un état asynchrone important est annoncé de façon
compréhensible, pas seulement animé.

## Responsive et navigation

- Concevoir mobile first à partir d’une largeur minimale de 320 px.
- Les paliers Tailwind `sm`, `md`, `lg` et `xl` enrichissent la disposition sans
  modifier l’ordre logique du contenu.
- Les parcours membres utilisent une navigation inférieure sur les petits
  écrans ; les surfaces administratives et de réglages peuvent employer une
  navigation latérale ou une grille plus large.
- Respecter les zones sûres avec `env(safe-area-inset-*)` pour les écrans
  concernés.
- Limiter la largeur des formulaires, profils, cartes de découverte et
  conversations afin de préserver la lisibilité sur grand écran.
- Éviter le défilement horizontal. Les contenus longs doivent se replier ou se
  tronquer avec une alternative accessible.

## Accessibilité

- Toutes les actions sont atteignables au clavier et possèdent un focus visible
  fondé sur le token `ring`.
- Un bouton uniquement représenté par une icône reçoit un nom accessible ; les
  icônes décoratives sont masquées aux technologies d’assistance.
- Les champs possèdent un libellé associé, et les erreurs sont reliées au champ
  concerné sans dépendre uniquement de la couleur.
- Les dialogues gèrent titre, description, focus initial et retour du focus au
  déclencheur.
- Les mises à jour utiles emploient `aria-live`, `aria-busy` ou un statut
  équivalent avec parcimonie.
- Les contrastes doivent rester suffisants dans les deux thèmes. Tester les
  combinaisons sémantiques, notamment texte atténué, destructive et doré.
- Respecter la réduction des animations demandée par le système ; une animation
  ne doit jamais être nécessaire à la compréhension.
- Les cibles tactiles principales visent au moins 44 px dans les parcours
  mobiles.

## Assets et propriété intellectuelle

Le logo validé du projet et les assets dont les droits sont démontrés peuvent
être utilisés. Aucun personnage, logo, attraction ou illustration Disney non
autorisé n’est ajouté au produit. Une évocation de chaleur ou de magie repose
sur la palette, la lumière, les formes et le mouvement, jamais sur la copie
d’un univers graphique protégé.

Les images d’avatars administrables restent des contenus applicatifs privés et
doivent respecter les exigences de [`security-privacy.md`](security-privacy.md).

## Règles d’évolution

- Modifier d’abord le token sémantique partagé lorsqu’une évolution concerne
  tout le produit.
- Réutiliser une variante existante avant de créer une nouvelle primitive.
- Documenter ici tout nouveau token, état de composant ou règle responsive
  partagé pendant l’implémentation correspondante.
- Vérifier au minimum les thèmes clair et sombre, le clavier, les petits écrans
  et les libellés accessibles pour tout changement visuel transversal.
- Ne pas utiliser le design system pour redéfinir un parcours ou une règle
  métier : ces décisions appartiennent au [`PRD.md`](PRD.md).
