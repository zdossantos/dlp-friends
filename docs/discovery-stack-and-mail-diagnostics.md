# Issues 241 et 242 — diagnostic du 3 octobre 2026

## Pile Découverte (242)

Régression reproduite dans Chromium : après un refus, le départage aléatoire à
score égal réordonnait entièrement le lot retourné. Le test attendait
Chloé, David, Emma, Farid, Hugo et recevait Hugo, Gabriel, Farid, Emma, David.

Le client transmet désormais les identifiants des cartes restantes (cinq au
maximum). Le serveur conserve leur ordre uniquement après application de tous
les filtres d’éligibilité existants, puis complète avec les résultats classés.
Ce contexte ne change ni le score ni les droits d’accès. Les cartes déjà évaluées
ne sont pas réintroduites. Les clics restent immédiatement visibles ; les
écritures sont envoyées une par une pour éviter les réponses désordonnées.
Une erreur restaure la carte concernée et les décisions encore non envoyées.

Les tests couvrent le renouvellement de deux lots avec un classement inversé,
les clics rapides sur réseau retardé, la restauration après erreur, les indices
invalides et les profils masqués, bloqués, supprimés ou à avatar désactivé.

### Comparaison documentaire de deux interfaces

Sources consultées le 3 octobre 2026 ; il s’agit de comportements décrits dans
les aides officielles, sans session connectée ni déduction de leur architecture.

| Interface | Comportement documenté | Limites de l’observation |
| --- | --- | --- |
| [Tinder](https://www.help.tinder.com/hc/en-us/articles/115003496683-I-m-seeing-profiles-I-ve-already-tapped-through) | Des profils déjà évalués peuvent revenir après une mauvaise connexion ou la recréation d’un compte. | L’aide ne garantit ni l’ordre des aperçus ni la taille ou le moment du renouvellement. |
| [Bumble For Friends](https://support.bumbleforfriends.com/hc/en-us/articles/16754800178077-What-is-Backtrack) | Backtrack permet de revenir sur un swipe gauche via une flèche, avec un abonnement Premium. | Ce retour volontaire ne décrit pas la restauration après erreur réseau ni le renouvellement de la pile. |

DLP Friends garde ses propres règles MVP : pas d’annulation libre d’une décision
enregistrée ; réessayer une écriture échouée reste possible. Les détails non
publiés de ces deux produits ne constituent pas des garanties à reproduire.

## Liens des mails (241)

Signalement confirmé par le membre : Yahoo Mail sur Android, bouton et lien de
secours affichés mais sans effet au toucher. Version de l’application, Android
et navigateur par défaut non connus ; aucune reproduction sur son appareil.

Le MIME de l’application a été inspecté avec des adresses et signatures
synthétiques. Les deux ancres HTML contenaient déjà la destination attendue.
En revanche, la partie texte du secours affichait `[URL](URL)` : la copie de
cette ligne n’était pas une adresse directement utilisable.

Le secours est maintenant une ancre HTML explicite et une URL seule dans la
partie texte. Les mails de vérification et de réinitialisation sont testés en
français et en anglais. Les routes signées, l’expiration, la session requise,
les jetons et le renvoi de vérification restent ceux du parcours existant.
Aucun jeton réel n’est nécessaire dans les preuves de diagnostic.

Cette correction améliore le secours mais ne prouve pas la résolution du
problème de toucher Yahoo. [L’aide Yahoo Android](https://help.yahoo.com/kb/SLN36812.html)
mentionne notamment le navigateur par défaut et Android System WebView lorsque
les liens ne s’ouvrent pas. Un test manuel avec un nouveau mail reçu sur
l’appareil affecté reste nécessaire pour distinguer le client, l’adresse de
l’application et le transport. Mailpit ne reproduit pas Yahoo.

La documentation opérationnelle mentionne Resend alors qu’AGENTS.md indique un
SMTP de production à décider : aucun changement de fournisseur n’est inclus.

## Vérifications et environnement local

- `vendor/bin/pest --display-warnings` : 1 090 tests réussis, cinq ignorés,
  26 035 assertions, code de sortie 0.
- PHPStan, Pint, TypeScript, Prettier et build Vite vérifiés. ESLint vérifié
  avec exclusion des anciens dossiers `.worktrees/` locaux.
- Image Docker runtime construite ; web, worker, scheduler et Reverb mis à jour
  et sains. Migration héritée de `main` appliquée explicitement.
- Les quatre variantes de mail ont été réellement reçues via le SMTP Mailpit
  local ; leurs deux ancres HTML et leur secours texte ont été vérifiés avec
  une adresse synthétique. `/up` et `/login` répondent HTTP 200.
- À la demande du membre, 149 décisions et 17 matchs locaux ont été supprimés.
  La cascade a supprimé 17 conversations, 19 messages et sept réactions ;
  55 notifications de conversation ont été retirées. Les 29 comptes ont été
  conservés. Aucun autre domaine n’a été réinitialisé.
