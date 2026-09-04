import {findAllInClosestScope} from "./Helpers";

export class DataDependant {
    dependant: HTMLElement;

    constructor(el: HTMLElement) {
        this.dependant = el;

        const dependency = this.dependant.dataset.dependency;
        // data-dependency-value por defecto "1"
        this.dependant.dataset.dependencyValue = this.dependant.dataset.dependencyValue || "1";
        const dependencyValue = this.dependant.dataset.dependencyValue;

        if (!dependency || !dependencyValue) {
            console.error(`El elemento tiene dependencia vacia`, this.dependant);
            return;
        }

        let dependencyInputs = findAllInClosestScope<HTMLInputElement | HTMLSelectElement>(this.dependant, `
            input${dependency},
            select${dependency},
            ${dependency} input,
            ${dependency} select
        `);

        if (dependencyInputs.length === 0) {
            console.error(`No se encontro input de la dependencia "${this.dependant.dataset.dependency}" para el elemento`, this.dependant);
            return;
        }

        this.evaluarDependencias(dependencyInputs, this.dependant);
        dependencyInputs.forEach(input => {
            input.addEventListener('change', () => { this.evaluarDependencias(dependencyInputs, this.dependant); })
        });
    }

    evaluarDependencias(dependencies: (HTMLInputElement | HTMLSelectElement)[], dependant: HTMLElement) {
        const dependencyValue = dependant.dataset.dependencyValue;
        const dependencyType = dependant.dataset.dependencyType || "hide"
        const dependencyCondition = dependant.dataset.dependencyCondition || "equal";

        if (dependencyCondition == "equal" && !dependencyValue) {
            console.error(`No hay dependency value para el elemento`, dependant);
            return;
        }

        const visible = dependencies.some(dependency => {
            // Input radio o checkbox
            if (dependency instanceof HTMLInputElement && (dependency.type == "radio" || dependency.type == "checkbox")) {
                return (dependencyCondition == "any" && dependency.checked)
                    || (dependencyCondition == "equal" && dependency.checked && dependency.value == dependencyValue)
                    || (dependencyCondition == "nequal" && dependency.checked && dependency.value != dependencyValue);
            }

            // Input normal o select
            return (dependencyCondition == "any" && dependency.value)
                || (dependencyCondition == "equal" && dependency.value == dependencyValue)
                || (dependencyCondition == "nequal" && dependency.value != dependencyValue);
        });

        // esconder/desactivar
        if (dependencyType == "hide") {
            dependant.style.display = visible ? '' : 'none';
        } else if (dependencyType == "disable") {
            if (dependant instanceof HTMLInputElement || dependant instanceof HTMLSelectElement) {
                dependant.disabled = !visible;
                return;
            }

            const inputs = dependant.querySelectorAll<HTMLInputElement | HTMLSelectElement>('input, select');
            inputs.forEach(input => {
                input.disabled = !visible;
            })
        }

        // borrar valor
        if (!visible && !(dependant instanceof HTMLInputElement)) {
            const inputs = dependant.querySelectorAll<HTMLInputElement | HTMLSelectElement>('input, select');
            inputs.forEach(input => {
                if (input.type == "radio" || input.type == "checkbox") {
                    input.checked = false;
                } else {
                    input.value = '';
                }

                const event = new Event('change');
                input.dispatchEvent(event);
            })
        }
    }
}
