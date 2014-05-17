<?php

use \Mockery as m;

class IncomingPhoneNumbersTest extends PHPUnit_Framework_TestCase {

    protected $apiResponse = array(
        'incoming_phone_numbers' => array(
            array(
                'sid' => 'PN123',
                'sms_fallback_method' => 'POST',
                'voice_method' => 'POST',
                'friendly_name' => '(510) 564-7903',
            )
        ),
    );

    function testGetNumberWithResult() {
        $http = m::mock(new Services_Twilio_TinyHttp);
        $http->shouldReceive('get')->once()
            ->with('/2010-04-01/Accounts/AC123/IncomingPhoneNumbers.json?Page=0&PageSize=1&PhoneNumber=%2B14105551234')
            ->andReturn(array(200, array('Content-Type' => 'application/json'),
                json_encode($this->apiResponse)
            )
        );
        $client = new Services_Twilio('AC123', '123', '2010-04-01', $http);
        $number = $client->account->incoming_phone_numbers->getNumber('+14105551234');
        $this->assertEquals('PN123', $number->sid);
    }

    function testGetNumberNoResults() {
        $http = m::mock(new Services_Twilio_TinyHttp);
        $http->shouldReceive('get')->once()
            ->with('/2010-04-01/Accounts/AC123/IncomingPhoneNumbers.json?Page=0&PageSize=1&PhoneNumber=%2B14105551234')
            ->andReturn(array(200, array('Content-Type' => 'application/json'),
                json_encode(array(
                    'incoming_phone_numbers' => array(),
                    'page' => 0,
                    'page_size' => 1,
                ))
            )
        );
        $client = new Services_Twilio('AC123', '123', '2010-04-01', $http);
        $number = $client->account->incoming_phone_numbers->getNumber('+14105551234');
        $this->assertNull($number);
    }

    function testGetMobile() {
        $http = m::mock(new Services_Twilio_TinyHttp);
        $http->shouldReceive('get')->once()
            ->with('/2010-04-01/Accounts/AC123/IncomingPhoneNumbers/Mobile.json?Page=0&PageSize=50')
            ->andReturn(array(200, array('Content-Type' => 'application/json'),
                json_encode($this->apiResponse)
            ));
        $http->shouldReceive('get')->once()
            ->with('/2010-04-01/Accounts/AC123/IncomingPhoneNumbers/Mobile.json?Page=1&PageSize=50')
            ->andReturn(array(400, array('Content-Type' => 'application/json'),
                '{"status":400,"message":"foo", "code": "20006"}'
            ));
        $client = new Services_Twilio('AC123', '123', '2010-04-01', $http);
        foreach ($client->account->incoming_phone_numbers->mobile as $num) {
            $this->assertEquals('(510) 564-7903', $num->friendly_name);
        }
    }

    function testGetLocal() {
        $http = m::mock(new Services_Twilio_TinyHttp);
        $http->shouldReceive('get')->once()
            ->with('/2010-04-01/Accounts/AC123/IncomingPhoneNumbers/Local.json?Page=0&PageSize=50')
            ->andReturn(array(200, array('Content-Type' => 'application/json'),
                json_encode($this->apiResponse)
            ));
        $http->shouldReceive('get')->once()
            ->with('/2010-04-01/Accounts/AC123/IncomingPhoneNumbers/Local.json?Page=1&PageSize=50')
            ->andReturn(array(400, array('Content-Type' => 'application/json'),
                '{"status":400,"message":"foo", "code": "20006"}'
            ));
        $client = new Services_Twilio('AC123', '123', '2010-04-01', $http);

        foreach ($client->account->incoming_phone_numbers->local as $num) {
            $this->assertEquals('(510) 564-7903', $num->friendly_name);
        }
    }

    function testGetTollFree() {
        $http = m::mock(new Services_Twilio_TinyHttp);
        $http->shouldReceive('get')->once()
            ->with('/2010-04-01/Accounts/AC123/IncomingPhoneNumbers/TollFree.json?Page=0&PageSize=50')
            ->andReturn(array(200, array('Content-Type' => 'application/json'),
                json_encode($this->apiResponse)
            ));
        $http->shouldReceive('get')->once()
            ->with('/2010-04-01/Accounts/AC123/IncomingPhoneNumbers/TollFree.json?Page=1&PageSize=50')
            ->andReturn(array(400, array('Content-Type' => 'application/json'),
                '{"status":400,"message":"foo", "code": "20006"}'
            ));
        $client = new Services_Twilio('AC123', '123', '2010-04-01', $http);
        foreach ($client->account->incoming_phone_numbers->toll_free as $num) {
            $this->assertEquals('(510) 564-7903', $num->friendly_name);
        }
    }

}

