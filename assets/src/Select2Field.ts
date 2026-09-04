
require('select2');
import $ from 'jquery';

export class Select2Field extends EventTarget {
    select: HTMLSelectElement;
    $select: any;
    url: string;
    searchParams?: URLSearchParams;

    constructor(select: HTMLSelectElement) {
        super();

        this.select = select;
        this.$select = $(this.select);

        if (!this.$select.data('select2-url')) {
            throw new TypeError("Select no contiene 'data-select2-url'.");
        }

        this.url = this.$select.data('select2-url');

        const minimumInputLength = this.$select.data('select2-minimum-input-length') != undefined ? Number(this.$select.data('select2-minimum-input-length')) : 2;

        this.$select.select2({
            ajax: {
                url: this.url,
                dataType: 'json',
                delay: 250,
                data: (params: any) => this.getData(params),
                processResults: function (data: any) {
                    return {
                        results: data.member.map((item: any) => ({
                            id: item.id,
                            text: item.text,
                        }))
                    };
                }
            },
            minimumInputLength: minimumInputLength
        });

        this.$select.on('select2:select select2:unselect', (e: any) => {
            // Dispatch para EventTarget listeners
            const event = new CustomEvent('select-change', {
                detail: {
                    value: this.getValue(),
                    originalEvent: e,
                }
            });
            this.dispatchEvent(event);

            // Dispatch evento nativo del DOM
            this.select.dispatchEvent(new Event('change', { bubbles: true }));
        });

        // opcion pre-seleccionada. docs: https://select2.org/programmatic-control/add-select-clear-items/#preselecting-options-in-an-remotely-sourced-ajax-select2
        const selectedOption = Array.from(this.select.options).find(o => o.value && o.selected);
        if (selectedOption) {
            const params = new URLSearchParams();
            params.set('id', selectedOption.value);
            $.ajax({
                type: 'GET',
                url: this.url,
                dataType: 'json',
                data: {
                    id: selectedOption.value,
                },
            }).then((data) => {
                if (typeof data === 'object' && data !== null && 'member' in data) {
                    const item = data.member[0];
                    // create the option and append to Select2
                    var option = new Option(item.text, item.id, true, true);
                    this.$select.append(option).trigger('change');

                    // manually trigger the `select2:select` event
                    this.$select.trigger({
                        type: 'select2:select',
                        params: {
                            data: data
                        }
                    });
                } else {
                    console.error(data);
                    throw new ReferenceError("No se pudo cargar la opción por defecto de select2, revise su endpoint. Debe permitir parámetro id y devolver un solo elemento dentro de data.member[].");
                }
            });
        }
    }

    getValue(): any {
        return this.$select.val();
    }

    on<T = any>(eventName: string, callback: (event: CustomEvent<T>) => void): void {
        this.addEventListener(eventName, callback as EventListener);
    }

    off<T = any>(eventName: string, callback: (event: CustomEvent<T>) => void): void {
        this.removeEventListener(eventName, callback as EventListener);
    }

    getData(params: any) {
        return {
            q: params.term,
            // unpack parametros
            ...(this.searchParams ? Object.fromEntries(this.searchParams) : {})
        };
    }

    addParam(name: string, value: string|number): this {
        if (!this.searchParams) {
            this.searchParams = new URLSearchParams();
        }

        this.searchParams.set(name, String(value));
        return this;
    }

    removeParam(name: string): this {
        if (this.searchParams && this.searchParams.has(name)) {
            this.searchParams.delete(name);
        }

        return this;
    }

    setDisable(disabled = true): this {
        this.$select.prop("disabled", disabled);
        return this;
    }

    clear(): this {
        this.$select.val(null).trigger('change');
        return this;
    }
}
