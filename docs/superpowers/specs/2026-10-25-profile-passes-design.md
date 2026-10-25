# Statuts Disneyland Pass et Cast Member — issue 311

Proposition validée le 25 octobre 2026, complétée par la demande de visuels SVG dans la zone avatar.

- Sélecteur facultatif : aucun, Bronze One, Bronze Max, Silver, Gold, Bronze (ancienne gamme). L'ancien Bronze reste une valeur distincte, sans conversion.
- Case Cast Member indépendante, cumulable avec tout pass. Valeurs initiales : aucun pass et faux.
- Informations autodéclarées, modifiables et supprimables ; aucun justificatif ni droit applicatif.
- Petite carte SVG originale dans la zone avatar des profils détaillés et cartes de découverte, avec type exact et tag Cast Member indépendant dans cette même zone. Réutiliser ProfilePresentation et ProfilePassCard, y compris profils intégrés et passés. Affichage sur les cartes de découverte et déplacement du tag Cast Member demandés le 25 octobre 2026 après revue du Docker local.
- Reprendre les autorisations du profil ; masquer ces nouveaux statuts pour une paire bloquée. Export personnel et purge habituelle du profil.
- FR/EN, contrôles accessibles et utilisables à 320 px, clair/sombre.

## Approche

Deux attributs du profil, enum PHP pour le pass et booléen pour Cast Member ; validation dans MemberProfileRequest, diffusion via PublicMemberData. Un seul composant visuel SVG paramétré par le pass évite cinq illustrations dupliquées. Aucun visuel officiel n'est copié : géométrie originale, tons bronze/argent/or, libellés traduits.

Les images officielles impliqueraient des droits de réutilisation non établis ; un statut exclusif empêcherait le cumul demandé. Pas de catalogue administrable, de prix, d'avantages, de filtre ou de changement du classement.
