<?php

use \Mockery as m;

class SMSMessagesTest extends PHPUnit_Framework_TestCase {
    protected $formHeaders = array('Content-Type' => 'application/x-www-form-urlencoded');

    function testCreateMessage() {
        $http = m::mock(new Services_Twilio_TinyHttp);
        $http->shouldReceive('post')->once()
            ->with('/2010-04-01/Accounts/AC123/SMS/Messages.json', $this->formHeaders,
                'From=%2B1222&To=%2B44123&Body=Hi+there')
            ->andReturn(array(200, array('Content-Type' => 'application/json'),
                json_encode(array('sid' => 'SM123'))
            ));
        $client = new Services_Twilio('AC123', '123', '2010-04-01', $http);
        $sms = $client->account->sms_messages->create('+1222', '+44123', 'Hi there');
        $this->assertSame('SM123', $sms->sid);
    }

    function testBadMessageThrowsException() {
        $this->setExpectedException('Services_Twilio_RestException');
        $http = m::mock(new Services_Twilio_TinyHttp);
        $http->shouldReceive('post')->once()
            ->with('/2010-04-01/Accounts/AC123/SMS/Messages.json', $this->formHeaders,
                'From=%2B1222&To=%2B44123&Body=' . str_repeat('hi', 81))
            ->andReturn(array(400, array('Content-Type' => 'application/json'),
                json_encode(array(
                    'status' => '400',
                    'message' => 'Too long',
                ))
            ));
        $client = new Services_Twilio('AC123', '123', null, $http);
        $sms = $client->account->sms_messages->create('+1222', '+44123',
            str_repeat('hi', 81));
    }
}

