===========================
Generate Capability Tokens
===========================

`Twilio Client <http://www.twilio.com/api/client>`_ allows you to make and recieve connections in the browser. You can place a call to a phone on the PSTN network, all without leaving your browser. See the `Twilio Client Quickstart <http:/www.twilio.com/docs/quickstart/client>`_ to get up and running with Twilio Client.

Capability tokens are used by `Twilio Client <http://www.twilio.com/api/client>`_ to provide connection security and authorization. The `Capability Token documentation <http://www.twilio.con/docs/tokens>`_ explains indepth the purpose and features of these tokens.

:php:class:`Services_Twilio_Capability` is responsible for the creation of these capability tokens. You'll need your Twilio AccountSid and AuthToken.

.. code-block:: php

    require('/path/to/twilio-php/Services/Twilio/Capability.php');

    $accountSid = "AC123123";
    $authToken = "secret";

    $capability = new Services_Twilio_Capability($accountSid, $authToken);


Allow Incoming Connections
==============================

Before a device running `Twilio Client <http://www.twilio.com/api/client>`_ can recieve incoming connections, the instance must first register a name (such as "Alice" or "Bob"). The :php:meth:`allowCclientIncoming` method adds the client name to the capability token.

.. code-block:: php

    $capability->allowClientIncoming("Alice");


Allow Outgoing Connections
==============================

To make an outgoing connection from a `Twilio Client <http://www.twilio.com/api/client>`_ device, you'll need to choose a `Twilio Application <http://www.twilio.com/docs/api/rest/applications>`_ to handle TwiML URLs. A Twilio Application is a collection of URLs responsible for outputing valid TwiML to control phone calls and SMS.

.. code-block:: php

    $applicationSid = "AP123123"; // Twilio Application Sid
    $capability->allowClientOutgoing($applicationSid);

:php:meth:`allowClientOutgoing` accepts an optional array of parameters. These parameters will be passed along when Twilio requests TwiML from the application.

.. code-block:: php

    $applicationSid = "AP123123";    // Twilio Application Sid
    $params = array("Foo" => "Bar"); // Parameters to be passed
    $capability->allowClientOutgoing($applicationSid, $params);


Generate a Token
==================

.. code-block:: php

    $token = $capability->generateToken();

By default, this token will expire in one hour. If you'd like to change the token expiration time, :php:meth:`generateToken` takes an optional argument which specifies `time to live` in seconds.

.. code-block:: php

    $token = $capability->generateToken(600);

This token will now expire in 10 minutes.

