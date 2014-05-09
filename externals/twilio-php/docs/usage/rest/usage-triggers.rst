==============
Usage Triggers
==============

Twilio offers a Usage Trigger API so you can get notifications when your Twilio
usage exceeds a given level. Here are some examples of how you can
use PHP to create new usage triggers or modify existing triggers.

Retrieve A Usage Trigger's Properties
=====================================

If you know the Sid of your usage trigger, retrieving it is easy.

.. code-block:: php

    $client = new Services_Twilio('AC123', '456bef');
    $usageSid = 'UT123';
    $usageTrigger = $client->account->usage_triggers->get($usageSid);
    echo $usageTrigger->usage_category;

Update Properties on a UsageTrigger
===================================

.. code-block:: php

    $client = new Services_Twilio('AC123', '456bef');
    $usageSid = 'UT123';
    $usageTrigger = $client->account->usage_triggers->get($usageSid);
    $usageTrigger->update(array(
        'FriendlyName' => 'New usage trigger friendly name',
        'CallbackUrl'  => 'http://example.com/new-trigger-url',
    ));

Retrieve All Triggers
=====================

.. code-block:: php

    $client = new Services_Twilio('AC123', '456bef');
    foreach ($client->account->usage_triggers as $trigger) {
        echo "Category: {$trigger->usage_category}\nTriggerValue: {$trigger->trigger_value}\n";
    }

Filter Trigger List By Category
===============================

Pass filters to the `getIterator` function to create a filtered list.

.. code-block:: php

    $client = new Services_Twilio('AC123', '456bef');
    foreach ($client->account->usage_triggers->getIterator(
        0, 50, array(
            'UsageCategory' => 'sms',
        )) as $trigger
    ) {
        echo "Value: " . $trigger->trigger_value . "\n";
    }

Create a New Trigger
====================

Pass a usage category, a value and a callback URL to the `create` method.

.. code-block:: php

    $client = new Services_Twilio('AC123', '456bef');
    $trigger = $client->account->usage_triggers->create(
        'totalprice',
        '250.75',
        'http://example.com/usage'
    );

Create a Recurring Trigger
==========================

To have your trigger reset once every day, month, or year, pass the
`Recurring` key as part of the params array. A list of optional
trigger parameters can be found in the `Usage Triggers Documentation
<http://www.twilio.com/docs/api/rest/usage-triggers#list-post-optional-paramete
rs>`_.

.. code-block:: php

    $client = new Services_Twilio('AC123', '456bef');
    $trigger = $client->account->usage_triggers->create(
        'totalprice',
        '250.75',
        'http://example.com/usage',
        array('Recurring' => 'monthly', 'TriggerBy' => 'price')
    );

