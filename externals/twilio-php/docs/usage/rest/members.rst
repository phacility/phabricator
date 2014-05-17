=============
Members
=============

List All Members in a Queue
============================

Each queue instance resource has a list of members.

.. code-block:: php

    $client = new Services_Twilio('AC123', '123');
    $queue_sid = 'QQ123';
    $queue = $client->account->queues->get('QQ123');
    foreach ($queue->members as $member) {
        echo "Call Sid: {$member->call_sid}\nWait Time: {$member->wait_time}\n";
    }

Dequeue a Member
=================

.. code-block:: php

    $client = new Services_Twilio('AC123', '123');
    $queue = $client->account->queues->get('QQ123');
    foreach ($queue->members as $member) {
        // Dequeue the first member and fetch the Forward twimlet for that
        // member.
        $member->dequeue('http://twimlets.com/forward', 'GET');
        break;
    }

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

