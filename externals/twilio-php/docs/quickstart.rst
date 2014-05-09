=============
Quickstart
=============

Making a Call
==============

.. code-block:: php

    $sid = "ACXXXXXX"; // Your Twilio account sid
    $token = "YYYYYY"; // Your Twilio auth token

    $client = new Services_Twilio($sid, $token);
    $call = $client->account->calls->create(
      '9991231234', // From this number
      '8881231234', // Call this number
      'http://foo.com/call.xml'
    );

Generating TwiML
==================

To control phone calls, your application need to output TwiML. Use :class:`Services_Twilio_Twiml` to easily create such responses.

.. code-block:: php

    $response = new Services_Twilio_Twiml();
    $response->say('Hello');
    print $response;

.. code-block:: xml

    <?xml version="1.0" encoding="utf-8"?>
    <Response><Play loop="5">monkey.mp3</Play><Response>
