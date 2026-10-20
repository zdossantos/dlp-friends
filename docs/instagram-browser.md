# Lien de bio Instagram (issue 283)

## Parcours livré

Utiliser `https://<domaine-public>/open` dans la bio Instagram. Le domaine est
celui de `APP_URL` pour l’environnement concerné. `/open` respecte la résolution
de langue habituelle (compte, cookie, puis `Accept-Language`). `/fr/open` et
`/en/open` imposent la langue et permettent de changer de langue sur la page.

Cette page publique est accessible aussi aux membres connectés et reste hors
indexation. Elle propose les instructions pour utiliser le menu d’Instagram
lorsque l’option d’ouverture externe existe, un champ de lien en lecture seule,
un bouton de copie et un accès à l’accueil. Sans JavaScript, le champ et les
instructions restent utilisables. Un refus ou une absence du presse-papiers
sélectionne le champ et affiche les instructions de copie manuelle. Le lien à
copier vient de `APP_URL` et cible uniquement `/fr` ou `/en`, jamais `/open`.
Les paramètres `url`, `redirect` ou autres ne permettent pas de changer la cible.

Aucune détection de navigateur ni redirection automatique n’est nécessaire :
les instructions restent disponibles même si Instagram modifie son user agent.
« Continuer sur DLP Friends » poursuit la visite dans le navigateur actuel ;
ce bouton ne promet pas une ouverture externe. Le parcours d’installation
existant est proposé à la fin du tutoriel produit.

## Recherche du 20 octobre 2026

Les recherches web ont été recoupées avec des documentations et les dépôts des
auteurs des outils de test. Les observations publiées ci-dessous ne sont **pas**
des essais réalisés sur DLP Friends.

| Méthode | Éléments vérifiés dans les sources | Décision |
| --- | --- | --- |
| `intent://…#Intent;scheme=https;…;end` sur Android | La documentation Chrome décrit les liens déclenchés par une action utilisateur, les activités `BROWSABLE` et `S.browser_fallback_url`. Le projet Inapp Debugger publie un succès dans Instagram Android, daté du 7 novembre 2024. Cela ne garantit pas la version actuelle d’Instagram. | Candidat à tester sur appareil ; pas activé sans vérification dans Instagram. Un `package=com.android.chrome` imposerait Chrome et ne correspondrait pas forcément au navigateur choisi par la personne. |
| `x-safari-https://…` sur iPhone | Les essais publiés dans Inapp Debugger rapportent des régressions suivant les versions d’Instagram, notamment à partir de 417, avec des résultats divergents pour 419. | Écarté du parcours livré : pas de garantie de fonctionnement et Safari imposé. |
| `instagram://extbrowser/?url=…` sur iPhone | Des projets open source utilisent ce schéma privé. InAppBrowser-Escaper l’a adopté dans sa version 1.0.1 après un échec rapporté avec iOS 26.4.1 et Instagram 424.1. La documentation officielle consultée ne fournit pas de contrat Meta pour ce mécanisme. | Candidat à tester ; pas activé sans vérification réelle. Aucun mécanisme privé présenté comme une API supportée. |
| `target="_blank"`, paramètre d’URL ou simple redirection HTTPS | Les sources consultées ne démontrent pas que ces mécanismes forcent Instagram à quitter sa vue intégrée. | Pas de promesse d’ouverture externe ; navigation HTTPS ordinaire pour continuer. |
| Menu Instagram et copie du lien | Parcours indépendant d’un schéma privé. La présence et le libellé de l’option du menu peuvent varier : le texte prévoit explicitement son absence. | Parcours livré, avec copie manuelle possible si le presse-papiers est inaccessible. |

Sources primaires :

- [Chrome : Android Intents](https://developer.chrome.com/docs/android/intents).
- [Android : intentions courantes et ouverture d’une page web](https://developer.android.com/guide/components/intents-common#Browser).
- [Apple : déclaration et traitement des schémas URL](https://developer.apple.com/documentation/xcode/defining-a-custom-url-scheme-for-your-app).
- [Inapp Debugger : matrice d’essais de son auteur](https://github.com/shalanah/inapp-debugger/blob/main/README.md).
- [Inapp Debugger : régressions du schéma Safari et versions testées](https://github.com/shalanah/inapp-debugger/issues/16).
- [InAppBrowser-Escaper : incident sur iOS 26.4.1 / Instagram 424.1 et correctif](https://github.com/jeonghunn/InAppBrowser-Escaper/issues/2).
- [inapp-escape : code et description des mécanismes privés et Android](https://github.com/m11to/inapp-escape).

## Vérification automatisée

- `tests/Feature/ExternalBrowserTest.php` : URL de bio, langues, HTML de secours,
  accès connecté, absence de redirection et cible indépendante des paramètres.
- `tests/Browser/ExternalBrowserTest.php` : refus du presse-papiers, confirmation
  après copie, changement de langue et continuation sans boucle dans Chromium.

Vérifications visuelles dans Chromium : français à 320 px et anglais à 1280 px,
sans débordement horizontal. Avec JavaScript désactivé, le champ reste visible
et le bouton de copie automatique est masqué.

L’émulation mobile Chromium vérifie l’interface ; elle ne reproduit ni la vue
intégrée d’Instagram ni les mécanismes iOS et Android d’ouverture externe.

## Recette sur téléphones — à compléter avant clôture de l’issue

Aucun iPhone ou téléphone Android avec Instagram n’était accessible pendant
l’implémentation. Les deux essais suivants restent **non réalisés**. Ne pas
considérer le dernier critère d’acceptation de l’issue 283 comme rempli.

| Appareil | Version OS | Version Instagram | Navigateur par défaut et version | Résultat / limites |
| --- | --- | --- | --- | --- |
| iPhone : non testé | Non relevée | Non relevée | Non relevé | À tester sur appareil réel |
| Android : non testé | Non relevée | Non relevée | Non relevé | À tester sur appareil réel |

Sur chaque appareil :

1. Ouvrir `/open` depuis une bio Instagram en français, puis en anglais.
2. Vérifier la lisibilité à petite largeur et au zoom, les liens de langue et
   la navigation avec les aides d’accessibilité de l’appareil.
3. Ouvrir le menu du navigateur intégré ; relever la présence et le libellé de
   l’option externe. Si disponible, l’utiliser et vérifier le navigateur ouvert.
4. Copier le lien puis le coller dans le navigateur choisi. Vérifier l’accueil
   localisé, la création/connexion au compte et l’accès aux instructions PWA.
5. Vérifier le secours lorsque le presse-papiers n’est pas disponible et lorsque
   le menu ne propose aucune ouverture externe.
6. Vérifier « Continuer sur DLP Friends », le retour arrière et l’ouverture
   directe de `/open` depuis le navigateur classique, sans boucle.
7. Consigner ici les modèles, versions, date et résultats exacts. Toute future
   activation d’un intent ou schéma privé nécessite ses propres essais, dont
   refus de confirmation, navigateur absent et retour à la page de secours.
