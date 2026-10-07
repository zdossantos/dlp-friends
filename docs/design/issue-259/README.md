# Refonte DLP Friends — dossier de validation #259

**Statut : première direction refusée ; révision en cours sur Explorer.** Aucun écran de production n’est modifié. Ce dossier est une étape de conception ; il ne constitue pas une décision d’intégration ni la clôture de [l’issue #259](https://github.com/zdossantos/dlp-friends/issues/259).

Le brief révisé demande un imaginaire magique et une interface compacte, avec les pages bornées sans scroll de page. Les fonds figuratifs reconnaissables sont refusés ; une lumière très diffuse et discrète est expérimentée. Explorer est l’écran témoin révisé : sa nouvelle carte remet un avatar illustré au premier plan, dans un cadre évoquant un carnet enchanté. L’avatar est un exemple original de revue ; le catalogue administrable reste la référence pour l’intégration. Les autres vues documentent encore la couverture V1, pas une direction approuvée.

## Consulter les maquettes

Ouvrir [le prototype](prototype/index.html?screen=explore). Le bandeau « Maquette » déroule le sélecteur des **67 surfaces**, la langue, le thème, la saison et l’état. Chaque sélection est partageable par son URL. Il s’agit de données fictives et d’actions simulées, sans connexion au backend, outil de mesure ou service externe.

Si le navigateur refuse les fichiers locaux, depuis la racine du dépôt :

```sh
python3 -m http.server 8259 --bind 127.0.0.1
```

Puis ouvrir `http://127.0.0.1:8259/docs/design/issue-259/prototype/index.html`.

| Pour commencer | Mobile                                                     | Desktop                                          |
| -------------- | ---------------------------------------------------------- | ------------------------------------------------ |
| Explorer V3    | [Petit navigateur](captures/revision-3/small-browser.png)  | Adapter la fenêtre du prototype à 1440 px        |
| Public         | Adapter la fenêtre à 320 px                                | [Accueil](captures/home-1440.png)                |
| Administration | [Membres à 320 px](captures/members-320.png)               | Tableau disponible à partir de 1024 px           |
| Partenaire     | [Profil, sombre](captures/partner-profile-390.png)         | Comparaison publié / brouillon sur deux colonnes |
| Événement      | [Discussion Halloween sombre](captures/group-chat-390.png) | [Liste Noël sombre](captures/events-1440.png)    |

Les captures V1 sont historiques et leur direction a été refusée. Les [captures Explorer V3](captures/revision-2/) montrent une révision provisoire, pas l’application en production. Les avatars abstraits évitent d’utiliser des images personnelles ou des personnages sous droits. Les polices et le logo proviennent du dépôt ; leur notice est conservée dans [assets](prototype/assets/THIRD_PARTY_FONTS.md).

## Lire et décider

1. [Inventaire : code existant → maquettes](inventory.md).
2. [Audit Impeccable priorisé et preuves](audit.md).
3. [Direction visuelle, composants, responsive et mouvement](direction.md).
4. [Parcours à valider et décisions attendues](validation.md).
5. [Découpage de l’intégration et stratégie de tests](implementation-plan.md).
6. [Vérifications effectuées et limites](verification.md).

L’issue exige une validation explicite des maquettes avant la refonte. La réponse attendue porte sur la direction, la navigation mobile, les parcours sensibles et les variantes. Les corrections restent dans ce dossier jusqu’à cette validation. Ne pas utiliser `Closes #259` dans une PR de proposition.
