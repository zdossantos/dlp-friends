# Réglages GitHub versionnés

Ces fichiers sont des entrées exactes pour l’API GitHub du dépôt
`zdossantos/dlp-friends`. Ils ne contiennent aucun secret.

## Application

```sh
gh api --method PATCH repos/zdossantos/dlp-friends --input .github/settings/repository.json
gh api --method PUT repos/zdossantos/dlp-friends/actions/permissions/workflow --input .github/settings/workflow-permissions.json
gh api --method PUT repos/zdossantos/dlp-friends/branches/develop/protection --input .github/settings/develop-protection.json
gh api --method PUT repos/zdossantos/dlp-friends/branches/main/protection --input .github/settings/main-protection.json
```

## Audit

```sh
gh api repos/zdossantos/dlp-friends
gh api repos/zdossantos/dlp-friends/actions/permissions/workflow
gh api repos/zdossantos/dlp-friends/branches/develop/protection
gh api repos/zdossantos/dlp-friends/branches/main/protection
```

Appliquer les protections après le premier passage des cinq checks CI, afin que
GitHub connaisse leurs noms. Ne jamais placer un jeton dans ces fichiers.
