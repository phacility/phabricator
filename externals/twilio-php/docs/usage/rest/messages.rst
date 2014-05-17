=============
Messages
=============

Sending a Message
=====================

The :class:`Messages <Services_Twilio_Rest_Messages>` resource allows you to
send outgoing SMS or MMS messages.

.. code-block:: php

    require('/path/to/twilio-php/Services/Twilio.php');

    $client = new Services_Twilio('AC123', '123');
    $message = $client->account->messages->sendMessage(
      '+14085551234', // From a Twilio number in your account
      '+12125551234', // Text any number
      'Hello monkey!',                          // Message body (if any)
      array('http://example.com/image.jpg'),    // An array of MediaUrls
    );

    echo $message->sid;

Listing Messages
====================

It's easy to iterate over your messages.

.. code-block:: php

    $client = new Services_Twilio('AC123', '123');
    foreach ($client->account->messages as $message) {
        echo "From: {$message->from}\nTo: {$message->to}\nBody: " . $message->body;
    }

Filtering Messages
======================

Let's say you want to find all of the messages that have been sent from
a particular number. You can do so by constructing an iterator explicitly:

.. code-block:: php

    $client = new Services_Twilio('AC123', '123');
    foreach ($client->account->messages->getIterator(0, 50, array(
        'From' => '+14105551234',
    )) as $message) {
        echo "From: {$message->from}\nTo: {$message->to}\nBody: " . $message->body;
    }
