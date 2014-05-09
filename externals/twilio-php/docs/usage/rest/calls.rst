=============
 Phone Calls
=============

Making a Phone Call
===================

The :class:`Calls` resource allows you to make outgoing calls:

.. code-block:: php

    $client = new Services_Twilio('AC123', '123');
    $call = $client->account->calls->create(
      '9991231234', // From this number
      '8881231234', // Call this number
      'http://foo.com/call.xml'
    );
    print $call->length;
    print $call->sid;

Adding Extra Call Parameters
============================

Add extra parameters, like a `StatusCallback` when the call ends, like this:

.. code-block:: php

    $client = new Services_Twilio('AC123', '123');
    $call = $client->account->calls->create(
        '9991231234', // From this number
        '8881231234', // Call this number
        'http://foo.com/call.xml',
        array(
        'StatusCallback' => 'http://foo.com/callback',
        'StatusCallbackMethod' => 'GET'
        )
    );

A full list of extra parameters can be found `here
<http://www.twilio.com/docs/api/rest/making-calls#post-parameters-optional>`_.

Listing Calls
=============

It's easy to iterate over your list of calls.

.. code-block:: php

    $client = new Services_Twilio('AC123', '123');
    foreach ($client->account->calls as $call) {
        echo "From: {$call->from}\nTo: {$call->to}\nSid: {$call->sid}\n\n";
    }

Filtering Calls
======================

Let's say you want to find all of the calls that have been sent from
a particular number. You can do so by constructing an iterator explicitly:

.. code-block:: php

    $client = new Services_Twilio('AC123', '123');
    foreach ($client->account->calls->getIterator(0, 50, array(
        'From' => '+14105551234'
    )) as $call) {
        echo "From: {$call->from}\nTo: {$call->to}\nSid: {$call->sid}\n\n";
    }

Accessing Resources from a Specific Call
========================================

The :class:`Call` resource has some subresources you can access, such as
notifications and recordings. If you have already have a :class:`Call`
resource, they are easy to get:

.. code-block:: php

    $client = new Services_Twilio('AC123', '123');
    foreach ($client->account->calls as $call) {
      $notifications = $call->notifications;
      if (is_array($notifications)) {
        foreach ($notifications as $notification) {
          print $notification->sid;
        }
      }

      $transcriptions = $call->transcriptions;
      if (is_array($transcriptions)) {
        foreach ($transcriptions as $transcription) {
          print $transcription->sid;
        }
      }

      $recordings = $call->recordings;
      if (is_array($recordings)) {
        foreach ($recordings as $recording) {
          print $recording->sid;
        }
      }
    }

Be careful, as the above code makes quite a few HTTP requests and may display 
PHP warnings for unintialized variables.

Retrieve a Call Record
======================

If you already have a :class:`Call` sid, you can use the client to retrieve
that record.:

.. code-block:: php

    $client = new Services_Twilio('AC123', '123');
    $sid = "CA12341234"
    $call = $client->account->calls->get($sid)

Modifying live calls
====================

The :class:`Call` resource makes it easy to find current live calls and
redirect them as necessary:

.. code-block:: php

    $client = new Services_Twilio('AC123', '123');
    $calls = $client->account->calls->getIterator(0, 50, array('Status' => 'in-progress'));
    foreach ($calls as $call) {
      $call->update(array('Url' => 'http://foo.com/new.xml', 'Method' => 'POST'));
    }

Ending all live calls is also possible:

.. code-block:: php

    $client = new Services_Twilio('AC123', '123');
    $calls = $client->account->calls->getIterator(0, 50, array('Status' => 'in-progress'));
    foreach ($calls as $call) {
      $call->hangup();
    }

Note that :meth:`hangup` will also cancel calls currently queued.
