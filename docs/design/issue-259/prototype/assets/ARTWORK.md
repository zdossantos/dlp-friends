# Lumière diffuse de revue

Trois illustrations originales ont été générées avec imagegen le 7 octobre 2026 : jardin imaginaire standard, Halloween et Noël. Le brief exclut logos, personnages, architecture de parc réel et personnes. Les PNG originaux sont conservés hors du dépôt ; les JPEG de revue sont limités à 1 200 px et compressés à une qualité de 80.

Après retour produit, aucun décor figuratif net n’est affiché. Explorer utilise uniquement une couche fortement floutée (60 px), désaturée et à 12 % d’opacité. Ce traitement est expérimental, en attente du choix de DA. Ces assets ne doivent pas être transférés automatiquement en production ; une lumière CSS peut suffire si ce traitement est retenu.

## Avatar de carte V3

`avatar-stargazer-v3.png` est une illustration originale générée avec imagegen pour la maquette : renard des étoiles, veste prune et écharpe rose, sur fond transparent. Aucun personnage de franchise ni logo n’a été demandé. Le PNG est limité à 512 px et conserve sa transparence. Il représente l’avatar fictif de Camille, pas une photo ni un nouveau membre du catalogue de production.

La future intégration réutilisera `AvatarPortrait.vue`, l’image du catalogue et ses deux couleurs de fond. La nouvelle composition doit supporter des silhouettes de catalogue variées avec `object-fit: contain`, sans couper tête, oreilles ou corps.
