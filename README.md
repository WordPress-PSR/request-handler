WordPress Request Handler
===========

A [PSR-15](https://www.php-fig.org/psr/psr-15/) Request Handler wrapper around WordPress core.

Installation
------------

```bash
$> composer require wordpress-psr/request-handler
```

Overview
-----------

This package allows WordPress installations to be used in a PSR Request and Response context.
This package is not very useful by itself but can be combined with any number of 
[Request Handler Runners](https://github.com/laminas/laminas-httphandlerrunner/) or [Middlewares](https://github.com/middlewares/psr15-middlewares)
to use WordPress is ways which were never possible before. 

Swoole
-------

Using this request handler and combining it with the [chubbyphp-swoole-request-handler](https://github.com/chubbyphp/chubbyphp-swoole-request-handler)
it is possible to run WordPress in the persistent, high performance, event-driven, asynchronous swoole http server.
See the [WordPress PSR Swoole project](https://github.com/WordPress-PSR/swoole) for more details.
In addition to Swoole since this request handler is using the psr-15 standard other event driven libraries such as [react](https://reactphp.org/) or [amp](https://amphp.org/http-server/classes/middleware) should work as well.

Example Usage
-------
See tests/server.php for a working example of how this request handler could be used.
This file was used to test the request handler with this line in the nginx conf:
```
try_files $uri /wordpress/$uri /tests/server.php?$args;
```
which is needed so static files in the wordpress sub-folder can be accessed and anything else is handled by server.php.

WordPress Modifications
-------
WordPress core code needed to be modified to get certain aspects of the request handler to work. These are kept in a [fork](https://github.com/superdav42/wordpress-core) right now but hopefully after their usefulness has been proven the changes can be merged into core.
Rector was used to make some modifications such as changing all called to `exit` or `die` to a new function called `wp_exit`. `wp_exit()` just does the action `wp_exit` before calling exit so that this request handler can throw an exception which return the execution flow to the request handler which returns a response object instead of exiting.    
All calls to `header()` and `setcookie` were also changed to trigger an action that this request handler hooks onto. The hook records the headers and cookies so they can be added to the response object.
Other changes are performed to allow WordPress to work as a long-running process. Mostly using `require` instead of `require_once` where appropriate.

License
-------

GPL, see LICENSE.
