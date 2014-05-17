<?php

use \Mockery as m;

class ApplicationsTest extends PHPUnit_Framework_TestCase {
    protected $formHeaders = array('Content-Type' => 'application/x-www-form-urlencoded');
    function testPost()
    {
        $http = m::mock(new Services_Twilio_TinyHttp);
        $http->shouldReceive('post')->once()
            ->with('/2010-04-01/Accounts/AC123/Applications.json',
                $this->formHeaders, 'FriendlyName=foo&VoiceUrl=bar')
            ->andReturn(array(200, array('Content-Type' => 'application/json'),
                json_encode(array('sid' => 'AP123'))
            ));
        $client = new Services_Twilio('AC123', '123', '2010-04-01', $http);
        $app = $client->account->applications->create('foo', array(
            'VoiceUrl' => 'bar',
        ));
        $this->assertEquals('AP123', $app->sid);
    }

    function tearDown()
    {
        m::close();
    }
}

