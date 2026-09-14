import { useMediaQuery } from '@vueuse/core';
import { computed } from 'vue';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    Drawer,
    DrawerClose,
    DrawerContent,
    DrawerDescription,
    DrawerFooter,
    DrawerHeader,
    DrawerTitle,
    DrawerTrigger,
} from '@/components/ui/drawer';

export function useResponsiveModal() {
    const isDesktop = useMediaQuery('(min-width: 640px)');
    const Modal = computed(() => ({
        Root: isDesktop.value ? Dialog : Drawer,
        Trigger: isDesktop.value ? DialogTrigger : DrawerTrigger,
        Content: isDesktop.value ? DialogContent : DrawerContent,
        Header: isDesktop.value ? DialogHeader : DrawerHeader,
        Title: isDesktop.value ? DialogTitle : DrawerTitle,
        Description: isDesktop.value ? DialogDescription : DrawerDescription,
        Footer: isDesktop.value ? DialogFooter : DrawerFooter,
        Close: isDesktop.value ? DialogClose : DrawerClose,
    }));

    return { isDesktop, Modal };
}
