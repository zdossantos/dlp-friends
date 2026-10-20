# Horizon — issue #293

Source approuvée : `horizon-source.svg`, copie exacte de
`dlp-friends-logo-concepts/orbites/b-horizon-espacement-v2.svg` de l’archive
jointe à [l’issue #293](https://github.com/zdossantos/dlp-friends/issues/293).
Les six ambiances ont été approuvées avant intégration. Le porteur du projet a
confirmé le 19 octobre 2026 qu’il s’agit d’une adaptation originale autorisée.

## Régénérer

Avec les dépendances du projet installées, Bun 1.3.14, PHP et Chromium :

```sh
bunx playwright install chromium
bun scripts/brand/generate.mjs
```

`php scripts/brand/generate-beta.php` reste un point d’entrée compatible et
régénère désormais tout le kit, pour éviter une divergence production/bêta.
La génération est explicite et n’est exécutée ni au démarrage ni au build.
Aucune dépendance supplémentaire n’a été ajoutée. Le libellé BETA des images
est vectorisé depuis Instrument Sans 600 (licence dans `THIRD_PARTY_FONTS.md`).
Le badge de l’application conserve sa traduction selon la langue du membre.

## Livrables et usages

| Chemin public | Usage |
| --- | --- |
| `brand/dlp-friends-logo{,-dark,-halloween,-halloween-dark,-christmas,-christmas-dark}.svg` | Sélection automatique des six ambiances par `.app-brand-mark` dans Vue et Blade |
| `brand/dlp-friends-logo-{black,white}.svg` | Monochromes transparents |
| `brand/variants/{standard,halloween,noel}-{clair,sombre}/` | Kit complet de chaque ambiance : symbole, signatures horizontale/verticale, favicons SVG/ICO, Apple, PWA standard/maskable et versions bêta |
| `favicon.svg`, `favicon.ico` | Navigateurs ; ICO contient 16, 32 et 48 px |
| `apple-touch-icon.png` | Apple Touch Icon opaque, 180 px ; également image des métadonnées sociales existantes |
| `pwa/icon-{192,512}.png` | Installation, notifications couleur et page hors connexion |
| `pwa/icon-maskable-{192,512}.png` | Installation Android, fond plein et dessin dans le cercle central de diamètre 80 % |
| `pwa/badge-96.png` | Badge de notification monochrome blanc transparent |
| `beta/` | Mêmes chemins installables avec badge et manifeste distinct |
| `pwa/screenshot-{fr,en}.png` | Captures réelles de l’accueil public en 390 × 844, sans données personnelles |

Les chemins installables stables utilisent l’ambiance standard sombre. Les
exports saisonniers complets sont disponibles dans le kit ; les changements
de thème dans l’application n’imposent pas une réinstallation. Le service worker
précache les logos employés dans l’interface et le badge de notification.
Les signatures SVG du kit conservent la typographie éditable de la maquette
approuvée ; les composants de l’application conservent leur token `font-accent`.

La géométrie des quatre tracés est conservée. Seul le dégagement peint en ivoire
devient un masque transparent. À 16 px, l’étoile perd du détail sans modifier
le tracé validé. Les fonds des Apple/PWA sont opaques ; les SVG de symbole et
monochromes restent transparents. Les icônes métier, avatars et pictogrammes
Lucide de l’interface restent ceux du produit.

## Vérification

```sh
php artisan test tests/Feature/BrandAssetsTest.php tests/Feature/HorizonBrandTest.php tests/Feature/BetaBrandingTest.php tests/Feature/PwaManifestTest.php tests/Feature/PwaServiceWorkerTest.php
php artisan test tests/Browser/AppearanceTest.php tests/Browser/BetaBrandingTest.php
```
