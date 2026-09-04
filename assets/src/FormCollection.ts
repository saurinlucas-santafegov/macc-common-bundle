import { LocalidadSelector } from "./LocalidadSelector";
import { Select2Field } from "./Select2Field";
import { DataDependant } from "./DataDependant";

/** Callbacks opcionales para reenviar los eventos que emiten los plugins inicializados. */
export interface FormPluginEvents {
    formAdd?: (detail: { value: Element }) => void;
    formRemove?: (detail: { value: number | Element }) => void;
    selectChange?: (detail: { element: HTMLSelectElement, value: any, originalEvent: any }) => void;
}

/**
 * Inicializa, dentro de `root`
 */
export function initFormPlugins(root: Element, events: FormPluginEvents = {}): FormCollection[] {
    // Colecciones (pueden estar anidadas)
    const collections = Array.from(root.querySelectorAll<HTMLButtonElement>('[data-collection-holder-id]'))
        .map(addButton => new FormCollection(addButton));

    collections.forEach(collection => {
        if (events.formAdd) {
            collection.on<{ value: Element }>('form-add', e => events.formAdd(e.detail));
        }

        if (events.formRemove) {
            collection.on<{ value: number | Element }>('form-remove', e => events.formRemove(e.detail));
        }
    });

    // Campos de localización (provincia, departamento, localidad)
    root.querySelectorAll<HTMLSelectElement>('[data-provincia-field][data-departamento-field]')
        .forEach(localidadSelect => new LocalidadSelector(localidadSelect));

    // Select2 con origen ajax
    root.querySelectorAll<HTMLSelectElement>('.select2-ajax').forEach(select => {
        const select2 = new Select2Field(select);
        (select as any).select2Field = select2;

        if (events.selectChange) {
            select2.on<{ value: any, originalEvent: any }>('select-change', e => events.selectChange({
                element: select2.select,
                value: e.detail.value,
                originalEvent: e.detail.originalEvent,
            }));
        }
    });

    // Dependencias entre campos
    root.querySelectorAll<HTMLElement>('[data-dependency]').forEach(el => { new DataDependant(el) });

    return collections;
}

export class FormCollection extends EventTarget {
    id: string;
    container: HTMLElement;
    addButton: HTMLButtonElement;
    prototype: string;
    prototypeName: string;
    index: number;  // solo para asignar id's a items
    items: Element[];
    formCollections: FormCollection[]; // formCollections anidadas

    constructor(addButton: HTMLButtonElement) {
        super();

        this.addButton = addButton;

        if (!addButton.dataset || !addButton.dataset.collectionHolderId) {
            throw new TypeError(`[FormCollection] ERROR: botón sin data-collection-holder-id.`);
        }

        const container = document.querySelector<HTMLElement>(`#${addButton.dataset.collectionHolderId}`);

        if (!container) {
            throw new ReferenceError(`[FormCollection] ERROR: container "#${addButton.dataset.collectionHolderId}" no encontrado`);
        }

        this.container = container;

        if (!this.container.id || !this.container.dataset || !this.container.dataset.prototype || !this.container.dataset.index) {
            throw new TypeError(`[FormCollection] ERROR: container sin data-prototype o data-index`);
        }

        this.id = this.container.id;
        this.prototype = this.container.dataset.prototype || '';
        this.prototypeName = this.container.dataset.prototypeName || '__name__';
        this.index = parseInt(this.container.dataset.index);
        this.items = [];
        this.formCollections = [];

        // Inicializar items existentes
        const items = this.container.querySelectorAll<HTMLElement>('[data-item-index]');
        items.forEach(item => {
            this.items.push(item);
        });

        // Guardar instancia de FormCollection en el propio elemento para acceder desde controllers "externos"
        (this.container as any).formCollection = this as FormCollection;
    }

    addForm() {
        // embed form
        const embeddedFormWrapper = this._embedForm();

        this.index++;

        // add delete button
        this._addDeleteButton(embeddedFormWrapper);

        // ===== PLUGINS =====
        this._initPlugins(embeddedFormWrapper)

        // Dispatch para EventTarget listeners
        const event = new CustomEvent('form-add', {
            detail: {
                value: embeddedFormWrapper,
            }
        });
        this.dispatchEvent(event);
    }

    private _embedForm(): Element {
        const escapedName = this.prototypeName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        const regex = new RegExp(escapedName, 'g');
        const template = document.createElement('template');
        template.innerHTML = this.prototype.replace(regex, this.index.toString());
        if (template.content.childNodes.length !== 1) {
            throw new Error(`HTML de template debe representar un único nodo, se obtuvieron ${template.content.childNodes.length}.`);
        }

        const embeddedFormWrapper = template.content.firstElementChild

        this.container.appendChild(embeddedFormWrapper);
        this.items.push(embeddedFormWrapper);

        return embeddedFormWrapper;
    }

    private _addDeleteButton(form: Element) {
        const deleteButton = form.querySelector<HTMLElement>('[data-role="delete"]');
        deleteButton?.addEventListener('click', () => this.removeForm(form));
    }

    addFormFromEntity<T extends Record<string, any>>(entity: T, customMapper?: (wrapper: HTMLElement, entity: T) => void) {
        // embed form
        const embeddedFormWrapper = this._embedForm();

        this.index++;

        // add delete button
        this._addDeleteButton(embeddedFormWrapper);

        // ===== INYECTAR ENTIDAD =====
        Object.entries(entity).forEach(([key, value]) => {
            if (value === null || value === undefined) return;

            // Buscamos dinámicamente el input de Symfony ignorando el prefijo y el índice.
            // Ej: name="certificado[stocks][1][cantidadInicial]" -> matchea "[cantidadInicial]"
            const input = embeddedFormWrapper.querySelector<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>(`[name$="[${key}]"]`);

            if (input) {
                this._injectValueIntoInput(input, value);
            }
        });

        // custom mapper
        if (customMapper) {
            customMapper(embeddedFormWrapper as HTMLElement, entity);
        }

        // ============================

        // plugins
        this._initPlugins(embeddedFormWrapper)

        // Dispatch para EventTarget listeners
        const event = new CustomEvent('form-add', {
            detail: {
                value: embeddedFormWrapper,
            }
        });
        this.dispatchEvent(event);
    }

    private _injectValueIntoInput(input: HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement, value): void {
        let finalValue = value;
        // Si el valor es una entidad anidada
        if (typeof value === 'object' && value !== null && 'id' in value) {
            finalValue = value.id.toString();
        } else {
            finalValue = String(value);
        }

        // checkboxes | radios
        if (input.type === 'checkbox' || input.type === 'radio') {
            const checkInput = input as HTMLInputElement;
            checkInput.checked = (checkInput.value === finalValue || finalValue === 'true' || finalValue === '1');
            checkInput.dispatchEvent(new Event('change', { bubbles: true }));
            return;
        }

        // selects
        if (input instanceof HTMLSelectElement) {
            // Si es un select2-ajax, el <option> no existe en el DOM HTML vacío. Hay que crearlo al vuelo.
            if (input.classList.contains('select2-ajax') && !input.querySelector(`option[value="${finalValue}"]`)) {
                // Se usa ID como text, pero debería usarse el custom mapper para el text de los selects
                const text = typeof value === 'object' && value !== null && 'text' in value ? value.text.toString() : finalValue;
                const option = new Option(text, finalValue, true, true);
                input.appendChild(option);
            } else {
                input.value = finalValue;
            }

            // Disparamos el evento nativo. El "Magic Bridge" en Select2Field se encarga de escucharlo.
            input.dispatchEvent(new Event('change', { bubbles: true }));
            return;
        }

        // Inputs de texto estándar
        input.value = finalValue;
        input.dispatchEvent(new Event('input', { bubbles: true }));
    }

    removeForm(item: Element): undefined;
    removeForm(index: number): undefined;
    removeForm(indexOrItem: number | Element): undefined {
        // Encontrar item
        let itemIndex: number = -1;
        if (indexOrItem instanceof Element)
            itemIndex = this.items.findIndex((el: Element) => el == indexOrItem);
        else
            itemIndex = this.items.findIndex((el: HTMLElement) => el.dataset.itemIndex == indexOrItem.toString());

        if (itemIndex == -1) {
            console.warn(`[FormCollection] WARNING: se intento borrar un item que no existe en la colección.`, indexOrItem);
            return;
        }

        // Eliminar
        this.items[itemIndex].remove();
        this.items.splice(itemIndex, 1);

        // No decrementamos index, para saber cantidad de items ver el array de items

        // Dispatch para EventTarget listeners
        const event = new CustomEvent('form-remove', {
            detail: {
                value: indexOrItem,
            }
        });
        this.dispatchEvent(event);
    }

    private _initPlugins(form: Element) {
        // Los eventos de las colecciones/selects anidados se reemiten en esta colección.
        const nested = initFormPlugins(form, {
            formAdd: detail => this.dispatchEvent(new CustomEvent('form-add', { detail })),
            selectChange: detail => this.dispatchEvent(new CustomEvent('select-change', {
                detail: {
                    value: detail.value,
                    originalEvent: detail.originalEvent,
                }
            })),
        });

        this.formCollections.push(...nested);
    }

    clear(): this {
        this.items.forEach(item => item.remove());
        this.items.splice(0, this.items.length);

        const event = new CustomEvent('form-remove', {
            detail: {
                value: -1,
            }
        });
        this.dispatchEvent(event);

        return this;
    }

    disableAdd(disable: boolean = true): this {
        this.addButton.disabled = disable;

        return this;
    }

    disableDelete(disable: boolean = true): this {
        // TODO: agregar opción para controlar si desactivar solo los de 1er nivel o desactivar recursivamente los de los FormCollecion anidados.
        this.container.querySelectorAll<HTMLButtonElement>('[data-role="delete"]').forEach(deleteButton => {
            deleteButton.disabled = disable;
        });
        return this;
    }

    on<T = any>(eventName: string, callback: (event: CustomEvent<T>) => void): void {
        this.addEventListener(eventName, callback as EventListener);
    }

    off<T = any>(eventName: string, callback: (event: CustomEvent<T>) => void): void {
        this.removeEventListener(eventName, callback as EventListener);
    }
}
