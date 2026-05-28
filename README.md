# GDMexico_GoogleTagManager

Módulo personalizado para Magento 2 que integra funcionalidades de Google Tag Manager y seguimiento personalizado.

## Características

- Integración personalizada con Google Tag Manager
- Seguimiento de eventos en Magento 2
- Personalización de DataLayer
- Eventos frontend personalizados
- Configuración administrable desde Magento

## Requisitos

- Magento Open Source / Adobe Commerce 2.4.x
- PHP 8.x

## Instalación

### Instalación vía Composer

Agregar el repositorio en el `composer.json` principal del proyecto Magento:

```json
"repositories": {
    "module-google-tag-manager": {
        "type": "vcs",
        "url": "git@github.com:Grupo-Dico/module-google-tag-manager.git"
    }
}