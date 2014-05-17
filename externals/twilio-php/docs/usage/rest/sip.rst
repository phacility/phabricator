=============
Sip In
=============

Getting started with Sip
==========================

If you're unfamiliar with SIP, please see the `SIP API Documentation
<https://www.twilio.com/docs/api/rest/sip>`_ on our website.

Creating a Sip Domain
=========================

The :class:`Domains <Services_Twilio_Rest_Domains>` resource allows you to
create a new domain. To create a new domain, you'll need to choose a unique
domain that lives under sip.twilio.com. For example, doug.sip.twilio.com.

.. code-block:: php

    require('/path/to/twilio-php/Services/Twilio.php');

    $client = new Services_Twilio('AC123', '123');
    $domain = $client->account->sip->domains->create(
      "Doug's Domain", // The FriendlyName for your new domain
      "doug.sip.twilio.com", // The sip domain for your new domain
      array(
        'VoiceUrl' => 'http://example.com/voice',
    ));

    echo $domain->sid;

Creating a new IpAccessControlList
====================================

To control access to your new domain, you'll need to explicitly grant access
to individual ip addresses. To do this, you'll first need to create an
:class:`IpAccessControlList <Services_Twilio_Rest_IpAccessControlList>` to hold
the ip addresses you wish to allow.

.. code-block:: php

    require('/path/to/twilio-php/Services/Twilio.php');

    $client = new Services_Twilio('AC123', '123');
    $ip_access_control_list = $client->account->sip->ip_access_control_lists->create(
      "Doug's IpAccessControlList", // The FriendlyName for your new ip access control list
    );

    echo $ip_access_control_list->sid;

Adding an IpAddress to an IpAccessControlList
==============================================

Now it's time to add an :class:`IpAddress
<Services_Twilio_Rest_IpAddress>` to your new :class:`IpAccessControlList
<Services_Twilio_Rest_IpAccessControlList>`.

.. code-block:: php

    require('/path/to/twilio-php/Services/Twilio.php');

    $client = new Services_Twilio('AC123', '123');
    $ip_address = $client->account->sip->ip_access_control_lists->get('AC123')->ip_addresses->create(
      "Doug's IpAddress", // The FriendlyName for this IpAddress 
      '127.0.0.1', // The ip address for this IpAddress
    );

    echo $ip_address->sid;

Adding an IpAccessControlList to a Domain
===========================================

Once you've created a :class:`Domain <Services_Twilio_Rest_Domain>` and an
:class:`IpAccessControlList <Services_Twilio_Rest_IpAccessControlList>`
you need to associate them. To do this,
create an :class:`IpAccessControlListMapping
<Services_Twilio_Rest_IpAccessControlListMapping>`.

.. code-block:: php

    require('/path/to/twilio-php/Services/Twilio.php');

    $client = new Services_Twilio('AC123', '123');
    $ip_access_control_list_mapping = $client->account->sip->domains->get('SD123')->ip_access_control_list_mappings->create(
      'AL123', // The sid of your IpAccessControlList
    );

    echo $ip_access_control_list_mapping->sid;
