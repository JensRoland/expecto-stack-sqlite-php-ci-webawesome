# SPCW-app-name

Built with [Expecto.dev](https://expecto.dev/) and the Sqlite-PHP-CodeIgniter-WebAwesome stack.

## Deployment of CI4 applications

Important: `index.php` is inside the *public* folder,
for better security and separation of components.

This means that you should configure your web server to "point" to your project's *public* folder, and
not to the project root. A better practice would be to configure a virtual host to point there.

**Please** read the [CodeIgniter user guide](https://codeigniter.com/user_guide/index.html) for a better explanation of how CI4 works!

## Server Requirements

PHP version 8.2 or higher is required, with the following extensions installed:

- [intl](http://php.net/manual/en/intl.requirements.php)
- [mbstring](http://php.net/manual/en/mbstring.installation.php)

Additionally, make sure that the following extensions are enabled in your PHP:

- json (enabled by default - don't turn it off)
- [mysqlnd](http://php.net/manual/en/mysqlnd.install.php) if you plan to use MySQL
- [libcurl](http://php.net/manual/en/curl.requirements.php) if you plan to use the HTTP\CURLRequest library
