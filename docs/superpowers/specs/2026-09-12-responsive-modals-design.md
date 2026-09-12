# Modales responsives shadcn-vue

## Objectif

Toutes les modales applicatives utilisent le modèle officiel « Responsive
Modal (Dialog & Drawer) » de shadcn-vue : un `Drawer` sous 640 px et un
`Dialog` à partir de 640 px. Le contenu, l'état, les actions et les règles
métier restent identiques entre les deux formats.

Source de référence :
[DialogResponsive.vue](https://github.com/unovue/shadcn-vue/blob/dev/apps/v4/components/demo/DialogResponsive.vue).

## Architecture

L'application reprend directement la structure de l'exemple officiel dans un
composable ciblé :

```ts
const isDesktop = useMediaQuery('(min-width: 640px)')

const Modal = computed(() => ({
  Root: isDesktop.value ? Dialog : Drawer,
  Trigger: isDesktop.value ? DialogTrigger : DrawerTrigger,
  Content: isDesktop.value ? DialogContent : DrawerContent,
  Header: isDesktop.value ? DialogHeader : DrawerHeader,
  Title: isDesktop.value ? DialogTitle : DrawerTitle,
  Description: isDesktop.value ? DialogDescription : DrawerDescription,
  Footer: isDesktop.value ? DialogFooter : DrawerFooter,
  Close: isDesktop.value ? DialogClose : DrawerClose,
}))
```

`useResponsiveModal()` expose `isDesktop` et `Modal`. Il ne crée pas une
nouvelle primitive, ne réimplémente pas Reka UI et ne masque aucune option des
composants shadcn-vue. Chaque consommateur emploie les composants dynamiques de
l'exemple officiel avec `<component :is="Modal.Root">`.

## Périmètre de migration

La migration couvre toutes les surfaces actuellement basées sur `Dialog` :

- panneau événementiel et confirmations d'événements ;
- célébration de match ;
- blocage d'un membre ;
- suppression d'un membre administré ;
- suppression du compte ;
- suppression d'une passkey ;
- configuration de l'authentification à deux facteurs ;
- confirmations d'administration des avatars et centres d'intérêt.

Les `Sheet` de navigation et la barre latérale restent des `Sheet` : ce sont des
éléments de navigation, pas des modales contextuelles.

## Comportement et présentation

- Le breakpoint officiel reste exactement `(min-width: 640px)`.
- Le Drawer mobile s'ouvre depuis le bas et prévoit le padding de zone sûre.
- Le Dialog desktop reste centré et conserve ses largeurs maximales actuelles.
- Les modales longues rendent uniquement leur contenu interne défilable et
  gardent leurs actions accessibles.
- L'ouverture contrôlée ou non contrôlée, les restrictions de fermeture, les
  confirmations, les formulaires et les états de chargement sont préservés.
- Le titre et la description restent reliés aux primitives accessibles de
  Reka UI par l'intermédiaire des composants shadcn-vue.
- Le focus initial et son retour au déclencheur restent gérés par les
  primitives. Le panneau événementiel conserve en plus sa restauration du
  scroll et du focus métier.
- La célébration de match conserve son calque visuel, son niveau d'empilement
  et son mode non fermable lorsqu'il est verrouillé.

## Tests et critères d'acceptation

Les tests navigateur doivent d'abord échouer avec les Dialog actuels sur
mobile, puis confirmer :

1. chaque famille de modales expose un Drawer sous 640 px ;
2. les mêmes parcours exposent un Dialog à partir de 640 px ;
3. annuler et confirmer exécutent toujours les actions attendues ;
4. les formulaires conservent validation, erreurs et focus ;
5. les modales contrôlées et non fermables respectent leur état ;
6. aucun avertissement d'accessibilité ni erreur JavaScript n'apparaît ;
7. les tests existants des événements, réglages, profils, administration,
   découverte et authentification restent verts.

Le lint, le formatage, les types, le build frontend et la suite Pest concernée
doivent passer avant livraison.
