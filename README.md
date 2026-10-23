# MACC Common Bundle

Servicios, comandos y componentes de front compartidos para los proyectos del
Ministerio de Ambiente y Cambio Climático.

## Instalación

```sh
composer require macc/common-bundle
```

## Componentes Twig

Requieren `twig_component.anonymous_template_directory` configurado en el proyecto
(viene por defecto en la recipe).

Un proyecto puede sobreescribir cualquiera creando `templates/components/Macc/<Nombre>.html.twig`.

## Frontend

Los assets se distribuyen dentro del propio bundle. En el proyecto hay que tener:

```jsonc
// package.json
{
    "dependencies": {
        "@macc/common": "file:vendor/macc/common-bundle/assets"
    }
}
```

### Controller de Stimulus

Se registra a mano para usar el nombre `dynamic-form` que usa el form theme:

```js
// assets/bootstrap.js
import DynamicFormController from '@macc/common/controllers/dynamic-form-controller';

export const app = startStimulusApp(/* ... */);
app.register('dynamic-form', DynamicFormController);
```

Cualquier formulario con `{{ stimulus_controller('dynamic-form') }}` inicializa colecciones, select2 ajax, selector de localidades y dependencias entre campos.

### API

```ts
import { FormCollection, initFormPlugins, Select2Field, LocalidadSelector,
         DataDependant, findInClosestScope, findAllInClosestScope } from '@macc/common';
```

- `initFormPlugins(root, events)`: inicializa todos los plugins dentro de `root`.
  Es lo que usa el controller al conectar y `FormCollection` al insertar un item nuevo.
- `FormCollection`: colecciones de Symfony con alta/baja, anidamiento y
  `addFormFromEntity()` para precargar un item desde una entidad.
- `Select2Field`: select2 con origen ajax (espera respuesta Hydra: `{ member: [{id, text}] }`).
- `LocalidadSelector`: encadena provincia → departamento → localidad. Endpoints por
  defecto `api/private/provincias/{id}/departamentos` y `api/private/departamentos/{id}/lugares`,
  sobreescribibles con `data-departamentos-url` / `data-lugares-url`.
- `DataDependant`: muestra/oculta o habilita/deshabilita un elemento según el valor de otro campo.
