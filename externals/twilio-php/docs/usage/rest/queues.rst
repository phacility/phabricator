=============
Queues
=============

Create a New Queue
=====================

To create a new queue, make an HTTP POST request to the Queues resource.

.. code-block:: php

    require('/path/to/twilio-php/Services/Twilio.php');

    $client = new Services_Twilio('AC123', '123');
    // Default MaxSize is 100. Or change it by adding a parameter, like so
    $queue = $client->account->queues->create('First Queue', 
        array('MaxSize' => 10));

    print $queue->sid;
    print $queue->friendly_name;

Listing Queues
====================

It's easy to iterate over your list of queues.

.. code-block:: php

    $client = new Services_Twilio('AC123', '123');
    foreach ($client->account->queues as $queue) {
        echo $queue->sid;
    }

Deleting Queues
====================

.. code-block:: php

    $client = new Services_Twilio('AC123', '123');
    $queue_sid = 'QQ123';
    $client->account->queues->delete('QQ123');

Retrieve the Member at the Front of a Queue
===========================================

The Members class has a method called ``front`` which can be used to retrieve
the member at the front of the queue.

.. code-block:: php

    $client = new Services_Twilio('AC123', '123');
    $queue = $client->account->queues->get('QQ123');
    $firstMember = $queue->members->front();
    echo $firstMember->position;
    echo $firstMember->call_sid;

