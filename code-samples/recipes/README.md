# Recipes module

This directory contains selected production code from the Recipes module of the MOVE!T SaaS platform.

The sample demonstrates the application flow across several layers:

```text
Nette Form
    ↓
Presenter
    ↓
Service
    ↓
Repository
    ↓
MySQL database
```

## Included files

- `RecipesForm.php` – creation and validation of the Nette form
- `RecipesPresenter.php` – request handling, authorization and coordination of the module
- `RecipesService.php` – application and business logic
- `RecipesRepository.php` – database access and persistence
- `PaginatorFactory.php` – reusable pagination helper

## Important notice

These files are selected directly from the production application, but they do not represent a complete runnable module.

Some dependencies, templates, translations, configuration files and related application services are intentionally not included.

The complete MOVE!T source code remains private for security and commercial reasons.
