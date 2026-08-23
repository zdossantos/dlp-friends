# Réglages GitHub versionnés

Ces fichiers sont des entrées exactes pour l’API GitHub du dépôt
`zdossantos/dlp-friends`. Ils ne contiennent aucun secret.

## Application

```sh
gh api --method PATCH repos/zdossantos/dlp-friends --input .github/settings/repository.json
gh api --method PUT repos/zdossantos/dlp-friends/actions/permissions/workflow --input .github/settings/workflow-permissions.json
gh api --method PUT repos/zdossantos/dlp-friends/branches/main/protection --input .github/settings/main-protection.json
```

Appliquer ces fichiers après le merge de la migration sur `main`. Supprimer les
branches obsolètes uniquement après avoir confirmé que `main` est la branche par
défaut, que sa nouvelle protection est active et que les arbres à supprimer
correspondent bien au parent pré-migration.

## Audit

```sh
gh api repos/zdossantos/dlp-friends
gh api repos/zdossantos/dlp-friends/actions/permissions/workflow
gh api repos/zdossantos/dlp-friends/branches/main/protection
gh api repos/zdossantos/dlp-friends/branches --paginate
gh workflow list --all
gh api repos/zdossantos/dlp-friends/actions/variables
gh secret list --app actions
```

Appliquer la protection après le premier passage des six checks CI, afin que
GitHub connaisse leurs noms. Ne jamais placer un jeton dans ces fichiers.
