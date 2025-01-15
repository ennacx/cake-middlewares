# CakePHP Middlewares

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
// in src/Application.php
<?php
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

                'maintenance_message' => env('MAINTENANCE_MESSAGE', null),

                'trust_proxy' => (env('USE_PROXY', 'false') === 'true')
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

* thru_ip_list
  * Specify the IP addresses, including the mask length, that can be skipped during maintenance and used to run normal applications.
* check_method
  * Specify either ```Ennacx\CakeMiddlewares\Enum\MaintenanceCheckMethod``` enum.
* check_file_path
  * This is a required parameter when you specify ```MaintenanceCheckMethod::FILE``` to ```check_method```, and specifies the full path of the file. The existence of this file is checked to determine whether the device is in maintenance mode.
* is_maintenance
  * This is a required parameter when you specify ```MaintenanceCheckMethod::FLAG``` to ```check_method```. This boolean value is used to determine whether the device is under maintenance.
* maintenance_message
  * Specify the message to be passed to the CakePHP Exception argument if there is one. If not, leave it unspecified or specify ```null```.
* trust_proxy
  * This is necessary to determine whether to obtain the IP from ```X-Forwareded-For```, in order to obtain the IP from the ```clientIP()``` method of the ```\Cake\Http\ServerRequest``` object and determine whether it is an IP to be passed through.
