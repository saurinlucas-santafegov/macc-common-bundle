# MACC Common Bundle

Install:
```sh
composer require macc/common-bundle
```

## UserInterface

Se debe configurar Doctrine para que resuelva las relaciones que apuntan a UserInterface como la entidad Usuario que se usa en el proyecto:

```yaml
# config/packages/doctrine.yaml
doctrine:
    orm:
        # ... otra config ...
        resolve_target_entities:
            Symfony\Component\Security\Core\User\UserInterface: App\Entity\Usuario
            # O App\Entity\Usuario\Usuario dependiendo el proyecto
```
