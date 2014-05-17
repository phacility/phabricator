==================
Applications
==================

Creating Applications
==============================

.. code-block:: php
    
    $client = new Services_Twilio('AC123', '123');
    $application = $client->account->applications->create('Application Friendly Name', 
      array(
        'FriendlyName' => 'My Application Name',
        'VoiceUrl' => 'http://foo.com/voice/url',
        'VoiceFallbackUrl' => 'http://foo.com/voice/fallback/url',
        'VoiceMethod' => 'POST',
        'SmsUrl' => 'http://foo.com/sms/url',
        'SmsFallbackUrl' => 'http://foo.com/sms/fallback/url',
        'SmsMethod' => 'POST'
      )
    );

    
Updating An Application
==============================

.. code-block:: php

    $client = new Services_Twilio('AC123', '123');
    $application = $client->account->applications->get('AP123');
    $application->update(array(
      'VoiceUrl' => 'http://foo.com/new/voice/url'
    )); 


Finding an Application by Name
==============================

Find an :class:`Application` by its name (full name match).

.. code-block:: php

    $client = new Services_Twilio('AC123', '123');
    $application = false;
    $params = array(
        'FriendlyName' => 'My Application Name'
      );
    foreach($client->account->applications->getIterator(0, 1, $params) as $_application) {
      $application = $_application;
    }