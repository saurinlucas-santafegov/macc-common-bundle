import { findInClosestScope } from "./Helpers";

/** Forma mínima que LocalidadSelector necesita de cada opción devuelta por la API. */
interface OpcionGeografica {
    id: number | string;
    nombre?: string;
}

const URL_DEPARTAMENTOS_DEFAULT = 'api/private/provincias/{id}/departamentos';
const URL_LUGARES_DEFAULT = 'api/private/departamentos/{id}/lugares';

/**
 * Encadena los selects provincia -> departamento -> localidad.
 *
 * El select de localidad declara a quién depende con `data-provincia-field` y
 * `data-departamento-field` (selectores CSS, resueltos en el scope más cercano).
 * Los endpoints se pueden sobreescribir por proyecto con `data-departamentos-url`
 * y `data-lugares-url`, usando `{id}` como placeholder.
 */
export class LocalidadSelector {
    provinciaSelect: HTMLSelectElement;
    departamentoSelect: HTMLSelectElement;
    localidadSelect: HTMLSelectElement;
    private departamentosUrl: string;
    private lugaresUrl: string;

    constructor(localidadSelect: HTMLSelectElement) {
        if (!localidadSelect.dataset || !localidadSelect.dataset.provinciaField || !localidadSelect.dataset.departamentoField) {
            throw new TypeError(`[LocalidadSelector] ERROR: select de localidad no contiene 'data-provincia-field' y/o 'data-departamento-field'.`);
        }

        this.localidadSelect = localidadSelect;
        this.departamentosUrl = localidadSelect.dataset.departamentosUrl || URL_DEPARTAMENTOS_DEFAULT;
        this.lugaresUrl = localidadSelect.dataset.lugaresUrl || URL_LUGARES_DEFAULT;

        const provinciaSelect = findInClosestScope<HTMLSelectElement>(this.localidadSelect, `
            select${this.localidadSelect.dataset.provinciaField},
            ${this.localidadSelect.dataset.provinciaField} select
        `);

        if (!provinciaSelect) {
            throw new ReferenceError(`[LocalidadSelector] ERROR: no se encontro select de provincia '${this.localidadSelect.dataset.provinciaField}'.`);
        }

        this.provinciaSelect = provinciaSelect;

        const departamentoSelect = findInClosestScope<HTMLSelectElement>(this.localidadSelect, `
            select${this.localidadSelect.dataset.departamentoField},
            ${this.localidadSelect.dataset.departamentoField} select
        `);

        if (!departamentoSelect) {
            throw new ReferenceError(`[LocalidadSelector] ERROR: no se encontro select de departamento '${this.localidadSelect.dataset.departamentoField}'.`);
        }

        this.departamentoSelect = departamentoSelect;

        // Inicializar
        if (!this.provinciaSelect.value) {
            // Borrar seleccion
            this.departamentoSelect.value = '';
            this.localidadSelect.value = '';

            // Deshabilitar selects
            this.departamentoSelect.disabled = true;
            this.localidadSelect.disabled = true;
        } else {
            this.onProvinciaChange();
        }

        if (!this.departamentoSelect.value) {
            // Borrar seleccion
            this.localidadSelect.value = '';

            // Deshabilitar selects
            this.localidadSelect.disabled = true;
        } else {
            this.onDepartamentoChange();
        }

        // Registrar eventos
        this.provinciaSelect.addEventListener('change', () => { this.onProvinciaChange() });
        this.departamentoSelect.addEventListener('change', () => { this.onDepartamentoChange() });
    }

    async onProvinciaChange() {
        // Borrar seleccion
        this.departamentoSelect.value = '';
        this.localidadSelect.value = '';

        // Deshabilitar selects
        this.departamentoSelect.disabled = !this.provinciaSelect.value;
        this.localidadSelect.disabled = !this.provinciaSelect.value;

        if (this.provinciaSelect.value) {
            // Popular selects
            const departamentos = await this.getDepartamentos(this.provinciaSelect.value);
            this.popular(this.departamentoSelect, departamentos);

            // Trigger events
            const event = new Event('change');
            this.departamentoSelect.dispatchEvent(event);
            this.localidadSelect.dispatchEvent(event);
        }
    }

    async onDepartamentoChange() {
        // Borrar seleccion
        this.localidadSelect.value = '';

        // Deshabilitar select
        this.localidadSelect.disabled = !this.departamentoSelect.value;

        if (this.departamentoSelect.value) {
            // Popular select
            const lugares = await this.getLugares(this.departamentoSelect.value);
            this.popular(this.localidadSelect, lugares);

            // Trigger events
            const event = new Event('change');
            this.localidadSelect.dispatchEvent(event);
        }
    }

    getDepartamentos(provinciaId: string): Promise<OpcionGeografica[]> {
        return this.fetchOpciones(this.departamentosUrl, provinciaId, 'Departamentos de Provincia');
    }

    getLugares(departamentoId: string): Promise<OpcionGeografica[]> {
        return this.fetchOpciones(this.lugaresUrl, departamentoId, 'Lugares de Departamento');
    }

    private popular(select: HTMLSelectElement, opciones: OpcionGeografica[]): void {
        select.innerHTML = '<option value>-- Seleccionar --</option>';
        opciones.forEach(opcion => {
            const opt: HTMLOptionElement = document.createElement('option');
            opt.value = `${opcion.id}`;
            opt.innerHTML = opcion.nombre ? opcion.nombre : '';
            select.appendChild(opt);
        });
    }

    private async fetchOpciones(urlTemplate: string, id: string, descripcion: string): Promise<OpcionGeografica[]> {
        const csrf = document.head.querySelector('[name="csrf-token"]');
        if (!csrf || !(csrf instanceof HTMLMetaElement)) {
            console.error("[LocalidadSelector] No se encontró CSRF");
            return [];
        }

        const url = `${document.baseURI}${urlTemplate.replace('{id}', encodeURIComponent(id))}`;

        const data = await fetch(url, {
            method: "GET",
            headers: {
                "Accept": "application/ld+json",
                "X-CSRF-TOKEN": csrf.content,
            },
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Error fetching ${descripcion} ${id}: ${response.status}`);
                }

                return response.json();
            })
            .catch(e => console.error(e));

        if (!data || !data.totalItems || data.totalItems < 1) {
            return [];
        }

        return data.member;
    }
}
