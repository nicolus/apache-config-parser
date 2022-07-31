## Apache2 Config Parser
This is a small PHP library which allows you to parse an Apache2 config file and list every site configured site with its domain, port, document root and aliases. It is loosely based on this repo : https://github.com/shabuninil/apache_config_parser 

### Installation
Install it with [composer](https://getcomposer.org/) :
```shell
composer require nicolus/apache-config-parser
```

### Usage
Create a new `Parser` by providing a starting point (either an Apache2 configuration file, or a directory that contains configuration files), and use the `->getHosts()` method to retrieve an array of `Hosts`.

For example :
```php
use Nicolus\ApacheConfigParser\Parser;

require 'vendor/autoload.php';

$parser = new Parser('/etc/apache2/apache2.conf');
$hosts = $parser->getHosts();

print_r($hosts);
```
would output something like :

```
Array
(
    [0] => Nicolus\ApacheConfigParser\Host Object
        (
            [name] => example.com
            [port] => 80
            [root] => /var/www/example.com/
            [aliases] => Array
                (
                    [0] => www.example.com
                )
        )

    [1] => Nicolus\ApacheConfigParser\Host Object
        (
            [name] => example.com
            [port] => 443
            [root] => /var/www/example.com/
            [aliases] => Array
                (
                    [0] => www.example.com
                )
        )

    [2] => Nicolus\ApacheConfigParser\Host Object
        (
            [name] => mysite.com
            [port] => 443
            [root] => /var/www/mysite/public/
            [aliases] => Array
                (
                )
        )
```


### Notes :

* "Include" and "IncludeOptional" directives are respected and will load the included files.
* The '*' wildcards in includes are respected.
* If you pass a directory path ending with a `/` (eg. `/etc/apache2/sites-enabled/`) it will load all files in this directory and its subdirectories recursively, and then handle includes.
* This is definitely not a full blown parser, it should cover most regular usecases, but expect it to break in some edge cases.

### Support and contributions

If you encounter a problem feel free to open an issue on github and describe what went wrong with an example config file.

Pull requests are welcome, especially if they don't break tests or add new tests.
