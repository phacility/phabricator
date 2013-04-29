# RESTful

Library for writing RESTful PHP clients.

[![Build Status](https://secure.travis-ci.org/bninja/restful.png)](http://travis-ci.org/bninja/restful)

The design of this library was heavily influenced by [Httpful](https://github.com/nategood/httpful). 

## Requirements

- [PHP](http://www.php.net) >= 5.3 **with** [cURL](http://www.php.net/manual/en/curl.installation.php)
- [Httpful](https://github.com/nategood/httpful) >= 0.1
    
## Issues

Please use appropriately tagged github [issues](https://github.com/bninja/restful/issues) to request features or report bugs.

## Installation

You can install using [composer](#composer), a [phar](#phar) package or from [source](#source). Note that RESTful is [PSR-0](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-0.md) compliant:

### Composer

If you don't have Composer [install](http://getcomposer.org/doc/00-intro.md#installation) it:

    $ curl -s https://getcomposer.org/installer | php

Add this to your `composer.json`: 

    {
        "require": {
            "bninja/restful": "*"
        }
    }
    
Refresh your dependencies:

    $ php composer.phar update
    

Then make sure to `require` the autoloader and initialize both:
    
    <?php
    require(__DIR__ . '/vendor/autoload.php');
    
    Httpful\Bootstrap::init();
    RESTful\Bootstrap::init();
    ...

### Phar

Download an Httpful [phar](http://php.net/manual/en/book.phar.php) file, which are all [here](https://github.com/nategood/httpful/downloads):    
    
    $ curl -s -L -o httpful.phar https://github.com/downloads/nategood/httpful/httpful.phar

Download a RESTful [phar](http://php.net/manual/en/book.phar.php) file, which are all [here](https://github.com/bninja/restful/downloads):

    $ curl -s -L -o restful.phar https://github.com/bninja/restful/downloads/restful-{VERSION}.phar
    
And then `include` both:

    <?php
    include(__DIR__ . '/httpful.phar');
    include(__DIR__ . '/restful.phar');
    ...

### Source

Download [Httpful](https://github.com/nategood/httpful) source:

    $ curl -s -L -o httpful.zip https://github.com/nategood/httpful/zipball/master;
    $ unzip httpful.zip; mv nategood-httpful* httpful; rm httpful.zip

Download the RESTful source:

    $ curl -s -L -o restful.zip https://github.com/bninja/restful/zipball/master
    $ unzip restful.zip; mv bninja-restful-* restful; rm restful.zip

And then `require` both bootstrap files:

    <?php
    require(__DIR__ . "/httpful/bootstrap.php")
    require(__DIR__ . "/restful/bootstrap.php")
    ...

## Usage

	TODO

## Testing
    
    $ phpunit --bootstrap vendor/autoload.php tests/

## Publishing

1. Ensure that **all** [tests](#testing) pass
2. Increment minor `VERSION` in `src/RESTful/Settings` and `composer.json` (`git commit -am 'v{VERSION} release'`)
3. Tag it (`git tag -a v{VERSION} -m 'v{VERSION} release'`)
4. Push the tag (`git push --tag`)
5. [Packagist](http://packagist.org/packages/bninja/restful) will see the new tag and take it from there
6. Build (`build-phar`) and upload a [phar](http://php.net/manual/en/book.phar.php) file 

## Contributing

1. Fork it
2. Create your feature branch (`git checkout -b my-new-feature`)
3. Write your code **and [tests](#testing)**
4. Ensure all tests still pass (`phpunit --bootstrap vendor/autoload.php tests/`)
5. Commit your changes (`git commit -am 'Add some feature'`)
6. Push to the branch (`git push origin my-new-feature`)
7. Create new pull request
