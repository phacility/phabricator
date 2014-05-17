[![Build Status](https://secure.travis-ci.org/twilio/twilio-php.png?branch=master)](http://travis-ci.org/twilio/twilio-php)

## Installation

You can install **twilio-php** via PEAR or by downloading the source.

#### Via PEAR (>= 1.9.3):

PEAR is a package manager for PHP. Open a command line and use these PEAR
commands to download the helper library:

    $ pear channel-discover twilio-pear.herokuapp.com/pear
    $ pear install twilio/Services_Twilio

If you get the following message:

    $ -bash: pear: command not found

you can install PEAR from their website, or download the source directly.

#### Via Composer:

**twilio-php** is available on Packagist as the
[`twilio/sdk`](http://packagist.org/packages/twilio/sdk) package.

#### Via ZIP file:

[Click here to download the source
(.zip)](https://github.com/twilio/twilio-php/zipball/master) which includes all
dependencies.

Once you download the library, move the twilio-php folder to your project
directory and then include the library file:

    require '/path/to/twilio-php/Services/Twilio.php';

and you're good to go!

## A Brief Introduction

With the twilio-php library, we've simplified interaction with the
Twilio REST API. No need to manually create URLS or parse XML/JSON.
You now interact with resources directly. Follow the [Quickstart
Guide](http://readthedocs.org/docs/twilio-php/en/latest/#quickstart)
to get up and running right now. The [User
Guide](http://readthedocs.org/docs/twilio-php/en/latest/#user-guide) shows you
how to get the most out of **twilio-php**.

## Quickstart

### Send an SMS

```php
<?php
// Install the library via PEAR or download the .zip file to your project folder.
// This line loads the library
require('/path/to/twilio-php/Services/Twilio.php');

$sid = "ACXXXXXX"; // Your Account SID from www.twilio.com/user/account
$token = "YYYYYY"; // Your Auth Token from www.twilio.com/user/account

$client = new Services_Twilio($sid, $token);
$message = $client->account->messages->sendMessage(
  '9991231234', // From a valid Twilio number
  '8881231234', // Text this number
  "Hello monkey!"
);

print $message->sid;
```

### Make a Call

```php
<?php
// Install the library via PEAR or download the .zip file to your project folder.
// This line loads the library
require('/path/to/twilio-php/Services/Twilio.php');

$sid = "ACXXXXXX"; // Your Account SID from www.twilio.com/user/account
$token = "YYYYYY"; // Your Auth Token from www.twilio.com/user/account

$client = new Services_Twilio($sid, $token);
$call = $client->account->calls->create(
  '9991231234', // From a valid Twilio number
  '8881231234', // Call this number

  // Read TwiML at this URL when a call connects (hold music)
  'http://twimlets.com/holdmusic?Bucket=com.twilio.music.ambient'
);
```

### Generating TwiML

To control phone calls, your application needs to output
[TwiML](http://www.twilio.com/docs/api/twiml/ "Twilio Markup Language"). Use
`Services_Twilio_Twiml` to easily create such responses.

```php
<?php
require('/path/to/twilio-php/Services/Twilio.php');

$response = new Services_Twilio_Twiml();
$response->say('Hello');
$response->play('https://api.twilio.com/cowbell.mp3', array("loop" => 5));
print $response;
```

That will output XML that looks like this:

```xml
<?xml version="1.0" encoding="utf-8"?>
<Response>
    <Say>Hello</Say>
    <Play loop="5">https://api.twilio.com/cowbell.mp3</Play>
<Response>
```

## [Full Documentation](http://readthedocs.org/docs/twilio-php/en/latest/ "Twilio PHP Library Documentation")

The documentation for **twilio-php** is hosted
at Read the Docs. [Click here to read our full
documentation.](http://readthedocs.org/docs/twilio-php/en/latest/ "Twilio PHP
Library Documentation")

## Prerequisites

* PHP >= 5.2.3
* The PHP JSON extension

## Reporting Issues

We would love to hear your feedback. Report issues using the [Github
Issue Tracker](https://github.com/twilio/twilio-php/issues) or email
[help@twilio.com](mailto:help@twilio.com).

