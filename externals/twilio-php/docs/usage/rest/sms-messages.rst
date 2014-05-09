=============
SMS Messages
=============

Sending a SMS Message
=====================


The :php:class:`Services_Twilio_Rest_SmsMessages` resource allows you to send
outgoing text messages.

.. code-block:: php

    require('/path/to/twilio-php/Services/Twilio.php');

    $client = new Services_Twilio('AC123', '123');
    $message = $client->account->sms_messages->create(
      '+14085551234', // From a Twilio number in your account
      '+12125551234', // Text any number
      "Hello monkey!"
    );

    print $message->sid;

Listing SMS Messages
====================

It's easy to iterate over your SMS messages.

.. code-block:: php

    $client = new Services_Twilio('AC123', '123');
    foreach ($client->account->sms_messages as $message) {
        echo "From: {$message->from}\nTo: {$message->to}\nBody: " . $message->body;
    }

Filtering SMS Messages
======================

Let's say you want to find all of the SMS messages that have been sent from
a particular number. You can do so by constructing an iterator explicitly:

.. code-block:: php

    $client = new Services_Twilio('AC123', '123');
    foreach ($client->account->sms_messages->getIterator(0, 50, array(
        'From' => '+14105551234',
    )) as $message) {
        echo "From: {$message->from}\nTo: {$message->to}\nBody: " . $message->body;
    }
