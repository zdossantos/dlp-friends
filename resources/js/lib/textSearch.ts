export function normalizeSearchText(value: string): string {
    return value
        .trim()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLocaleLowerCase();
}
