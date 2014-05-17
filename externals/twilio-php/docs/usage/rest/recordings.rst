==========
Recordings
==========

Listing Recordings
------------------

Run the following to get a list of all of your recordings:

.. code-block:: php

    $accountSid = 'AC1234567890abcdef1234567890a';
    $authToken = 'abcdef1234567890abcdefabcde9';
    $client = new Services_Twilio($accountSid, $authToken);

    foreach($client->account->recordings as $recording) {
        echo "Access recording {$recording->sid} at:" . "\n";
        echo $recording->uri;
    }

For more information about which properties are available for a recording
object, please see the `Twilio Recordings API Documentation <http://www.twilio.com/docs/api/rest/recording>`_.

Please note that the ``uri`` returned by default is a JSON dictionary
containing metadata about the recording; you can access the .wav version by
stripping the ``.json`` extension from the ``uri`` returned by the library.

Filtering Recordings By Call Sid
--------------------------------

Pass filters as an array to filter your list of recordings, with any of the
filters listed in the `recording list documentation <http://www.twilio.com/docs/api/rest/recording#list-get-filters>`_.

.. code-block:: php

    $accountSid = 'AC1234567890abcdef1234567890a';
    $authToken = 'abcdef1234567890abcdefabcde9';
    $client = new Services_Twilio($accountSid, $authToken);

    foreach($client->account->recordings->getIterator(0, 50, array('DateCreated>' => '2011-01-01')) as $recording) {
        echo $recording->uri . "\n";
    }

Deleting a Recording
--------------------

To delete a recording, get the sid of the recording, and then pass it to the
client.

.. code-block:: php

    $accountSid = 'AC1234567890abcdef1234567890a';
    $authToken = 'abcdef1234567890abcdefabcde9';
    $client = new Services_Twilio($accountSid, $authToken);

    foreach($client->account->recordings as $recording) {
        $client->account->recordings->delete($recording->sid);
        echo "Deleted recording {$recording->sid}, the first one in the list.";
        break;
    }

