# CakePHP Middlewares

[![PHP Version Require](https://poser.pugx.org/ennacx/cake-middlewares/require/php)](https://packagist.org/packages/ennacx/cake-middlewares)
[![Latest Stable Version](https://poser.pugx.org/ennacx/cake-middlewares/v)](https://packagist.org/packages/ennacx/cake-middlewares)
[![Total Downloads](https://poser.pugx.org/ennacx/cake-middlewares/downloads)](https://packagist.org/packages/ennacx/cake-middlewares)
[![Latest Unstable Version](https://poser.pugx.org/ennacx/cake-middlewares/v/unstable)](https://packagist.org/packages/ennacx/cake-middlewares)
[![License](https://poser.pugx.org/ennacx/cake-middlewares/license)](https://packagist.org/packages/ennacx/cake-middlewares)

## Requires
* PHP 8.1 or later
* composer 2.0 or later

## Install

You can install this plugin into your CakePHP application using [composer](https://getcomposer.org).

```
composer require ennacx/cake-middlewares
```

## Usage

### Maintenance middleware

#### Installation

```php
<?php
// in src/Application.php

use Ennacx\CakeMiddlewares\Enum\MaintenanceCheckMethod;
use Ennacx\CakeMiddlewares\Middleware\MaintenanceMiddleware;

class Application extends BaseApplication
{
    public function middleware(MiddlewareQueue $middlewareQueue): MiddlewareQueue
    {
        $middleware
            // Catch any exceptions in the lower layers,
            // and make an error page/response
            ->add(new ErrorHandlerMiddleware(Configure::read('Error'), $this))

            // Handle plugin/theme assets like CakePHP normally does.
            ->add(new AssetMiddleware([
                'cacheTime' => Configure::read('Asset.cacheTime'),
            ]))

            // Add routing middleware.
            // If you have a large number of routes connected, turning on routes
            // caching in production could improve performance.
            // See https://github.com/CakeDC/cakephp-cached-routing
            ->add(new RoutingMiddleware($this))

            // Add Maintenance middleware
            ->add(new MaintenanceMiddleware([
                'thru_ip_list' => ['127.0.0.1/32'],

                'check_method' => \Ennacx\CakeMiddlewares\Enum\MaintenanceCheckMethod::FILE,

                'check_file_path' => TMP . 'maintenance',

                'is_maintenance' => true,

                'check_date_from'   => null,
                'check_date_to'     => null,
                'check_date_format' => 'Y-m-d H:i:s',

                'maintenance_message' => 'Sorry, we are currently performing server maintenance.',

                'use_proxy' => true
            ]))

            // Parse various types of encoded request bodies so that they are
            // available as array through $request->getData()
            // https://book.cakephp.org/4/en/controllers/middleware.html#body-parser-middleware
            ->add(new BodyParserMiddleware())

            // Cross Site Request Forgery (CSRF) Protection Middleware
            // https://book.cakephp.org/4/en/security/csrf.html#cross-site-request-forgery-csrf-middleware
            ->add(new CsrfProtectionMiddleware([
                'httponly' => true,
            ]));

        return $middleware;
    }
}
```

Recommended to insert it next to RoutingMiddleware.

#### Options

* thru_ip_list (```string[]```)
  * Specify the IP addresses, including the mask length, that can be skipped during maintenance and used to run normal applications.
* check_method (```Ennacx\CakeMiddlewares\Enum\MaintenanceCheckMethod```)
  * Specify either ```Ennacx\CakeMiddlewares\Enum\MaintenanceCheckMethod``` enum.
* check_file_path (```string```)
  * This is a required parameter when you specify ```MaintenanceCheckMethod::FILE``` to ```check_method```, and specifies the full path of the file. The existence of this file is checked to determine whether the system is in maintenance mode.
* is_maintenance (```bool```)
  * This is a required parameter when you specify ```MaintenanceCheckMethod::FLAG``` to ```check_method```. This boolean value is used to determine whether the device is under maintenance.
* check_date_from (```string``` or ```null```)
  * This is a required parameter when you specify ```MaintenanceCheckMethod::DATE``` to ```check_method```. The system will be in maintenance mode from this date(time) onwards.
  * If ```null``` specified, the maintenance state will continue until the date(time) specified in ```check_date_to```.
  * The format of this date(time) will be determined in ```check_date_format``` below.
* check_date_to (```string``` or ```null```)
  * This is a required parameter when you specify ```MaintenanceCheckMethod::DATE``` to ```check_method```. The system will be under maintenance until this date(time).
  * If ```null``` specified, the maintenance state will continue from the date(time) specified in ```check_date_from```.
  * The format of this date(time) will be determined in ```check_date_format``` below.
* check_date_format (```string```)
  * Specifies the format of the date(time) described in ```check_date_from``` and ```check_date_to```.
* maintenance_message (```string``` or ```null```)
  * Specify the message to be passed to the CakePHP Exception argument if there is one. If not, leave it unspecified or specify ```null```.
* use_proxy (```bool```)
  * This is necessary to determine whether to obtain the IP from ```HTTP_X_FORWARDED_FOR```, in order to obtain the IP from the ```clientIP()``` method of the ```\Cake\Http\ServerRequest``` object and determine whether it is an IP to be passed through.

#### Utility

If you specify ```check_method``` as ```MaintenanceCheckMethod::FILE```, you can use this switcher to easily switch the maintenance state, for example, by batch or command.

```php
<?php
/*
 * Specifies the path of the flag file.
 * If not specified or null, the path of "TMP . 'maintenance'" will be referenced.
 */
$flagFilePath = TMP . 'maintenance_flag_file';

// Get instance
$sw = \Ennacx\CakeMiddlewares\Utils\MaintenanceSwitch::getInstance($flagFilePath);

/*
 * To maintenance mode
 * 
 * If it returns false, it is already in maintenance mode.
 * If the file creation fails, throws \RuntimeException.
 */
$result = $sw->on();

/*
 * To Operation mode
 * 
 * If it returns false, it is already in operation mode.
 * If the file remove fails, throws \RuntimeException.
 */
$result = $sw->off();

/*
 * Switching between Maintenance and Operation
 * 
 * Executes one of the on() and off() methods depending on the existence of a maintenance file.
 * If the file creation/remove fails, throws \RuntimeException.
 */
$result = $sw->toggle();

/*
 * Check for the existence of the maintenance flag file.
 */
$maintenance = $sw->isMaintenance();
```

## License
[MIT](https://en.wikipedia.org/wiki/MIT_License)

[CreativeCommons BY-SA](https://creativecommons.org/licenses/by-sa/4.0/)
