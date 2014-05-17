=============
Usage Records
=============

Twilio offers a Usage Record API so you can better measure how much you've been
using Twilio. Here are some examples of how you can use PHP to access the usage
API.

Retrieve All Usage Records
==========================

.. code-block:: php

    $client = new Services_Twilio('AC123', '456bef');
    foreach ($client->account->usage_records as $record) {
        echo "Record: $record";
    }

Retrieve Usage Records For A Time Interval
==========================================

UsageRecords support `several convenience subresources
<http://www.twilio.com/docs/api/rest/usage-records#list-subresources>`_ that
can be accessed as properties on the `record` object.

.. code-block:: php

    $client = new Services_Twilio('AC123', '456bef');
    foreach ($client->account->usage_records->last_month as $record) {
        echo "Record: $record";
    }

Retrieve All Time Usage for A Usage Category
============================================

By default, Twilio will return your all-time usage for a given usage category.

.. code-block:: php

    $client = new Services_Twilio('AC123', '456bef');
    $callRecord = $client->account->usage_records->getCategory('calls');
    echo $callRecord->usage;

Retrieve All Usage for a Given Time Period
==========================================

You can filter your UsageRecord list by providing `StartDate` and `EndDate`
parameters.

.. code-block:: php

    $client = new Services_Twilio('AC123', '456bef');
    foreach ($client->account->usage_records->getIterator(0, 50, array(
        'StartDate' => '2012-08-01',
        'EndDate'   => '2012-08-31',
    )) as $record) {
        echo $record->description . "\n";
        echo $record->usage . "\n";
    }

Retrieve Today's SMS Usage
==========================

You can use the `today` record subresource, and then retrieve the record
directly with the `getCategory` function.

.. code-block:: php

    $client = new Services_Twilio('AC123', '456bef');
    // You can substitute 'yesterday', 'all_time' for 'today' below
    $smsRecord = $client->account->usage_records->today->getCategory('sms');
    echo $smsRecord->usage;

Retrieve Daily Usage Over a One-Month Period
=============================================

The code below will retrieve daily summaries of recordings usage for August
2012. To retrieve all categories of usage, remove the 'Category' filter from
the `getIterator` array.

.. code-block:: php

    $client = new Services_Twilio('AC123', '456bef');
    foreach ($client->account->usage_records->daily->getIterator(0, 50, array(
        'StartDate' => '2012-08-01',
        'EndDate'   => '2012-08-31',
        'Category'  => 'recordings',
    )) as $record) {
        echo $record->usage;
    }

