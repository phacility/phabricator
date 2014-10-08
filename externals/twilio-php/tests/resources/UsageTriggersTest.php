<?php

use \Mockery as m;

class UsageTriggersTest extends PHPUnit_Framework_TestCase {
    function testRetrieveTrigger() {
        $http = m::mock(new Services_Twilio_TinyHttp);
        $http->shouldReceive('get')->once()
            ->with('/2010-04-01/Accounts/AC123/Usage/Triggers/UT123.json')
            ->andReturn(array(200, array('Content-Type' => 'application/json'),
                json_encode(array(
                    'sid' => 'UT123',
                    'date_created' => 'Tue, 09 Oct 2012 19:27:24 +0000',
                    'recurring' => null,
                    'usage_category' => 'totalprice',
                ))
            ));
        $client = new Services_Twilio('AC123', '456bef', '2010-04-01', $http);
        $usageSid = 'UT123';
        $usageTrigger = $client->account->usage_triggers->get($usageSid);
        $this->assertSame('totalprice', $usageTrigger->usage_category);
    }

    protected $formHeaders = array('Content-Type' => 'application/x-www-form-urlencoded');

    function testUpdateTrigger() {
        $http = m::mock(new Services_Twilio_TinyHttp);
        $usageSid = 'UT123';
        $http->shouldReceive('post')->once()
            ->with('/2010-04-01/Accounts/AC123/Usage/Triggers/UT123.json',
                $this->formHeaders, 'FriendlyName=new')
            ->andReturn(array(200, array('Content-Type' => 'application/json'),
                json_encode(array(
                    'friendly_name' => 'new',
                    'sid' => 'UT123',
                    'uri' => '/2010-04-01/Accounts/AC123/Usage/Triggers/UT123.json'
                ))
            ));
        $http->shouldReceive('get')->once()
            ->with('/2010-04-01/Accounts/AC123/Usage/Triggers/UT123.json')
            ->andReturn(array(200, array('Content-Type' => 'application/json'),
                json_encode(array(
                    'sid' => 'UT123',
                    'friendly_name' => 'new',
                ))
            ));
        $client = new Services_Twilio('AC123', '456bef', '2010-04-01', $http);
        $usageTrigger = $client->account->usage_triggers->get($usageSid);
        $usageTrigger->update(array(
            'FriendlyName' => 'new',
        ));
        $usageTrigger2 = $client->account->usage_triggers->get($usageSid);
        $this->assertSame('new', $usageTrigger2->friendly_name);
    }

    function testFilterTriggerList() {
        $http = m::mock(new Services_Twilio_TinyHttp);
        $params = 'Page=0&PageSize=50&UsageCategory=sms';
        $http->shouldReceive('get')->once()
            ->with('/2010-04-01/Accounts/AC123/Usage/Triggers.json?' . $params)
            ->andReturn(array(200, array('Content-Type' => 'application/json'),
                json_encode(array('usage_triggers' => array(
                    array(
                        'usage_category' => 'sms',
                        'current_value' => '4',
                        'trigger_value' => '100.30',
                    ),
                    array(
                        'usage_category' => 'sms',
                        'current_value' => '4',
                        'trigger_value' => '400.30',
                    )),
                    'next_page_uri' => '/2010-04-01/Accounts/AC123/Usage/Triggers.json?UsageCategory=sms&Page=1&PageSize=50',
                ))
            ));
        $params = 'UsageCategory=sms&Page=1&PageSize=50';
        $http->shouldReceive('get')->once()
            ->with('/2010-04-01/Accounts/AC123/Usage/Triggers.json?' . $params)
            ->andReturn(array(400, array('Content-Type' => 'application/json'),
                '{"status":400,"message":"foo", "code": "20006"}'
            ));
        $client = new Services_Twilio('AC123', '456bef', '2010-04-01', $http);
        foreach ($client->account->usage_triggers->getIterator(
            0, 50, array(
                'UsageCategory' => 'sms',
            )) as $trigger
        ) {
            $this->assertSame($trigger->current_value, "4");
        }
    }

    function testCreateTrigger() {
        $http = m::mock(new Services_Twilio_TinyHttp);
        $params = 'UsageCategory=sms&TriggerValue=100&CallbackUrl=foo';
        $http->shouldReceive('post')->once()
            ->with('/2010-04-01/Accounts/AC123/Usage/Triggers.json',
                $this->formHeaders, $params)
            ->andReturn(array(201, array('Content-Type' => 'application/json'),
                json_encode(array(
                    'usage_category' => 'sms',
                    'sid' => 'UT123',
                    'uri' => '/2010-04-01/Accounts/AC123/Usage/Triggers/UT123.json'
                ))
            ));
        $client = new Services_Twilio('AC123', '456bef', '2010-04-01', $http);
        $trigger = $client->account->usage_triggers->create(
            'sms',
            '100',
            'foo'
        );
        $this->assertSame('sms', $trigger->usage_category);
    }
}

