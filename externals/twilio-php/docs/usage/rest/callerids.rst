============
 Caller Ids
============

Validate a Phone Number
=======================
Adding a new phone number to your validated numbers is quick and easy:

.. code-block:: php

    $client = new Services_Twilio('AC123', '123');
    $response = $client->account->outgoing_caller_ids->create('+15554441234');
    print $response->validation_code;

Twilio will call the provided number and for the validation code to be entered.

Listing all Validated Phone Numbers
===================================

Show all the current caller_ids:

.. code-block:: php

    $client = new Services_Twilio('AC123', '123');
    foreach ($client->account->outgoing_caller_ids as $caller_id) {
      print $caller_id->friendly_name;
    }
