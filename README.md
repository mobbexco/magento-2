# Mobbex for Magento 2

## Requisitos
* PHP >= 7.0
* Magento >= 2.1.0
* Composer >= 1

## Instalación
> [!NOTE]
> Recuerde que todos los comandos deben ejecutarse en el directorio de instalación de Magento

> [!WARNING]
> Si está utilizando composer 1 para la instalación, primero añada el repositorio a composer mediante el comando `composer config repositories.mobbexco-php-plugins-sdk vcs https://github.com/mobbexco/php-plugins-sdk`

1. Descargue el paquete:
    ```
    composer require mobbexco/magento-2
    ```

2. Asegurese de que el módulo se encuentra activo:
    ```
    php bin/magento module:enable Mobbex_Webpay
    ```

3. Actualice la base de datos y regenere los archivos:
    ```
    php bin/magento setup:upgrade
    php bin/magento setup:static-content:deploy -f
    ```

4. Añada las credenciales de Mobbex al módulo desde el panel de administración.

## Actualización
Para actualizar el módulo ejecute el siguiente comando, y luego repita los pasos 2 y 3 de la instalación:
```
composer update mobbexco/magento-2
```
> Si al ejecutar el comando se presentan conflictos con dependencias ejecute el comando `composer remove mobbexco/magento-2` y vuelva a realizar los pasos de la instalación.

## Hooks

Debido a las limitaciones de la plataforma en el manejo de eventos, hemos decidido implementar un método propio para extender las funcionalidades del módulo.

Puntualmente, las diferencias al momento de implementar un observer con estos eventos son las siguientes:
- El observer **no necesita implementar la ObserverInterface**, debido a que se ejecuta directamente el método que coincida con el nombre del hook.
- El método del observer recibe como parámetros los argumentos enviados, en lugar de obtenerlos mediante un parámetro de tipo observer.
- Los valores retornados modifican el resultado obtenido al momento de ejecutar el hook.

A continuación, un ejemplo utilizando el hook `mobbexCheckoutRequest`:
```php
<?php

namespace Vendor\Module\Observer;

class Hooks
{
    public function mobbexCheckoutRequest($body, $order)
    {
        $body['reference'] = $order->getId();

        return $body;
    }
}
```

Y un ejemplo de como se registra el evento en el archivo `events.xml`. Recuerde que aquí debe escribirse utilizando snake-case:
```xml
<config>
    <event name="mobbex_checkout_request">
        <observer name="vendor_module_hooks" instance="Vendor\Module\Observer\Hooks" />
    </event>
</config>
```

El módulo cuenta con los siguientes hooks actualmente:
<table>
    <thead>
        <tr>
            <th>Nombre</th>
            <th>Utilidad</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>mobbexCheckoutRequest</td>
            <td>Modificar el body que se utiliza para crear el checkout.</td>
        </tr>
        <tr>
            <td>mobbexProcessPayment</td>
            <td>Remplazar la información que se utiliza para generar las opciones de pago.</td>
        </tr>
        <tr>
            <td>mobbexWebhookReceived</td>
            <td>Guardar datos adicionales al recibir el webhook de Mobbex.</td>
        </tr>
        <tr>
            <td>mobbexProductSettings</td>
            <td>Añadir opciones a la configuración por producto del plugin.</td>
        </tr>
        <tr>
            <td>mobbexCategorySettings</td>
            <td>Añadir opciones a la configuración por categoría del plugin.</td>
        </tr>
    </tbody>
</table>
