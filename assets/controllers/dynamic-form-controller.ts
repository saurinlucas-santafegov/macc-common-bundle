import { Controller } from '@hotwired/stimulus';
import { FormCollection, initFormPlugins } from '../src/FormCollection';

export default class DynamicFormController extends Controller {
    formCollections?: FormCollection[];

    connect() {
        this.formCollections = initFormPlugins(this.element, {
            formAdd: detail => this.dispatch('form-add', { detail }),
            formRemove: detail => this.dispatch('form-remove', { detail }),
            selectChange: detail => this.dispatch('select-change', { detail }),
        });
    }

    // Métodos llamados desde stimulus action
    addFormToCollection(e: Event) {
        if (this.formCollections === undefined) {
            console.error(`ERROR: se intentó agregar item cuando no hay FormCollection inicializadas.`);
            return;
        }

        const addButton = e.currentTarget;

        if (!(addButton instanceof HTMLElement)) {
            console.error(`ERROR: no se encontró botón de agregar item`);
            return;
        }

        if (!addButton.dataset || !addButton.dataset.collectionHolderId) {
            console.error(`ERROR: no se encontró data-collection-holder-id`);
            return;
        }

        // encontrar collection. Puede estar anidada
        const collectionElement = this.element.querySelector(`#${addButton.dataset.collectionHolderId}`);
        const collection = (collectionElement as any)?.formCollection as FormCollection;
        if (!collection) {
            console.error(`ERROR: no se encontró collection "${addButton.dataset.collectionHolderId}" para agregar item o no está inicializada.`);
            return;
        }

        collection.addForm();
    }

    deleteFormFromCollection(e: Event) {
        if (this.formCollections === undefined) {
            console.error(`ERROR: se intentó borrar item cuando no hay FormCollection inicializadas.`);
            return;
        }

        const deleteButton = e.currentTarget;

        if (!(deleteButton instanceof HTMLElement)) {
            console.error(`ERROR: no se encontró botón de delete`);
            return;
        }

        if (!deleteButton.dataset || !deleteButton.dataset.parentCollectionId || !deleteButton.dataset.parentItemIndex) {
            console.error(`ERROR: el boton de delete no tiene data-parent-collection-id o data-parent-item-index.`);
            return;
        }

        // encontrar collection
        const collectionIndex = this.formCollections.findIndex(formCollection => formCollection.id == deleteButton.dataset.parentCollectionId);
        if (collectionIndex == -1) {
            console.error(`ERROR: no se encontró collection "${deleteButton.dataset.parentCollectionId}" para borrar item o no está inicializada.`);
            return;
        }

        this.formCollections[collectionIndex].removeForm(parseInt(deleteButton.dataset.parentItemIndex));
    }
}
