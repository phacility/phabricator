<?php

use \Mockery as m;

class ShortCodesTest extends PHPUnit_Framework_TestCase {

    function testShortcodeResource() {
        $http = m::mock(new Services_Twilio_TinyHttp);
        $http->shouldReceive('get')->once()
            ->with('/2010-04-01/Accounts/AC123/SMS/ShortCodes/SC123.json')
            ->andReturn(array(200, array('Content-Type' => 'application/json'),
                json_encode(array('sid' => 'SC123', 'short_code' => '1234'))
            ));
        $client = new Services_Twilio('AC123', '123', '2010-04-01', $http);
        $sms = $client->account->short_codes->get('SC123');
        $this->assertSame('1234', $sms->short_code);
    }
}

