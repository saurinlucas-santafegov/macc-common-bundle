/**
 * Busca en el ancestro más cercano. Sirve para no salirse del item de colección al que pertenecen.
 */
export function findAllInClosestScope<T extends Element>(from: Element, selector: string): T[] {
    for (let scope: Element | null = from.parentElement; scope; scope = scope.parentElement) {
        const found = Array.from(scope.querySelectorAll<T>(selector));

        if (found.length > 0) {
            return found;
        }
    }

    return [];
}

export function findInClosestScope<T extends Element>(from: Element, selector: string): T | null {
    return findAllInClosestScope<T>(from, selector)[0] || null;
}
