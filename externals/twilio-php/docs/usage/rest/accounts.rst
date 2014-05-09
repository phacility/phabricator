==================
Accounts
==================

Updating Account Information
==============================

Updating :class:`Account <Services_Twilio_Rest_Account>` information is really easy:

.. code-block:: php

    $client = new Services_Twilio('AC123', '123');
    $account = $client->account;
    $account->update(array('FriendlyName' => 'My Awesome Account'));

Creating a Subaccount
==============================

.. code-block:: php

    $client = new Services_Twilio('AC123', '123');
    $subaccount = $client->accounts->create(array(
      'FriendlyName' => 'My Awesome SubAccount'
    ));
