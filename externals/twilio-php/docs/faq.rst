==========================
Frequently Asked Questions
==========================

Hopefully you can find an answer here to one of your questions. If not, please
contact `help@twilio.com <mailto:help@twilio.com>`_.

Debugging Requests
------------------

Sometimes the library generates unexpected output. The simplest way to debug is
to examine the HTTP request that twilio-php actually sent over the wire. You
can turn on debugging with a simple flag:

.. code-block:: php

    require('Services/Twilio.php');

    $client = new Services_Twilio('AC123', '456bef');
    $client->http->debug = true;

Then make requests as you normally would. The URI, method, headers, and body
of HTTP requests will be logged via the ``error_log`` function.


require: Failed to open stream messages
-----------------------------------------

If you are trying to use the helper library and you get an error message that
looks like this:

.. code-block:: php

    PHP Warning:  require(Services/Twilio.php): failed to open stream: No such 
    file or directory in /path/to/file

    Fatal error: require(): Failed opening required 'Services/Twilio.php' 
    (include_path='.:/usr/lib/php:/usr/local/php-5.3.8/lib/php') in 
    /Library/Python/2.6/site-packages/phpsh/phpsh.php(578): on line 1

Your PHP file can't find the Twilio library. The easiest way to do this is to
move the Services folder from the twilio-php library into the folder containing
your file. So if you have a file called ``send-sms.php``, your folder structure
should look like this:

.. code-block:: bash

    .
    ├── send-sms.php
    ├── Services
    │   ├── Twilio.php
    │   ├── Twilio
    │   │   ├── ArrayDataProxy.php
    │   │   ├── (..about 50 other files...)

If you need to copy all of these files to your web hosting server, the easiest
way is to compress them into a ZIP file, copy that to your server with FTP, and
then unzip it back into a folder in your CPanel or similar.

You can also try changing the ``require`` line like this:

.. code-block:: php

    require('/path/to/twilio-php/Services/Twilio.php');

You could also try downloading the library via PEAR, a package manager for PHP, 
which will add the library to your PHP path, so you can load the Twilio library
from anywhere. Run this at the command line:

.. code-block:: bash

    $ pear channel-discover twilio.github.com/pear
    $ pear install twilio/Services_Twilio

If you get the following message:

.. code-block:: bash

    $ -bash: pear: command not found

you can install PEAR from their website.

SSL Validation Exceptions
-------------------------

If you are using an outdated version of `libcurl`, you may encounter
SSL validation exceptions. If you see the following error message, you have
a SSL validation exception: ::

    Fatal error: Uncaught exception 'Services_Twilio_TinyHttpException' 
    with message 'SSL certificate problem, verify that the CA cert is OK. 

    Details: error:14090086:SSL routines:SSL3_GET_SERVER_CERTIFICATE:certificate 
    verify failed' in [MY PATH]\Services\Twilio\TinyHttp.php:89

This means that Twilio is trying to offer a certificate to verify that you are
actually connecting to `https://api.twilio.com <https://api.twilio.com>`_, but
your curl client cannot verify our certificate. 

There are four solutions to this problem:

Upgrade your version of the twilio-php library
==============================================

Since November 2011, the SSL certificate has been built in to the helper
library, and it is used to sign requests made to our API. If you are still
encountering this problem, you can upgrade your helper library to the latest
version, and you should not encounter this error anymore.

If you are using an older version of the helper library, you can try one of the
following three methods:

Upgrade your version of libcurl
===============================

The Twilio certificate is included in the latest version of the
``libcurl`` library. Upgrading your system version of ``libcurl`` will
resolve the SSL error. `Click here to download the latest version of
libcurl <http://curl.haxx.se/download.html>`_.

Manually add Twilio's SSL certificate
=====================================

The PHP curl library can also manually verify an SSL certificate. In your
browser, navigate to
`https://github.com/twilio/twilio-php/blob/master/Services/cacert.pem
<https://github.com/twilio/twilio-php/blob/master/Services/cacert.pem>`_ 
and download the file. (**Note**: If your browser presents ANY warnings
at this time, your Internet connection may be compromised. Do not download the
file, and do not proceed with this step). Place this file in the same folder as
your PHP script. Then, replace this line in your script:

.. code-block:: php

    $client = new Services_Twilio($sid, $token);

with this one:

.. code-block:: php

    $http = new Services_Twilio_TinyHttp(
        'https://api.twilio.com',
        array('curlopts' => array(
            CURLOPT_SSL_VERIFYPEER => true, 
            CURLOPT_SSL_VERIFYHOST => 2, 
            CURLOPT_CAINFO => getcwd() . "/cacert.pem")));

    $client = new Services_Twilio($sid, $token, "2010-04-01", $http);

Disable certificate checking
============================

A final option is to disable checking the certificate. Disabling the
certificate check means that a malicious third party can pretend to be
Twilio, intercept your data, and gain access to your Account SID and
Auth Token in the process. Because this is a security vulnerability,
we **strongly discourage** you from disabling certificate checking in
a production environment. This is known as a `man-in-the-middle attack
<http://en.wikipedia.org/wiki/Man-in-the-middle_attack>`_.

If you still want to proceed, here is code that will disable certificate
checking:

.. code-block:: php

    $http = new Services_Twilio_TinyHttp(
        'https://api.twilio.com',
        array('curlopts' => array(CURLOPT_SSL_VERIFYPEER => false))
    );

    $client = new Services_Twilio('AC123', 'token', '2010-04-01', $http);

If this does not work, double check your Account SID, token, and that you do
not have errors anywhere else in your code. If you need further assistance,
please email our customer support at `help@twilio.com`_.

