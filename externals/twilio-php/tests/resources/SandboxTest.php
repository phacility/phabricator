<?php

use \Mockery as m;

class SandboxTest extends PHPUnit_Framework_TestCase {
    protected $formHeaders = array('Content-Type' => 'application/x-www-form-urlencoded');
    function testUpdateVoiceUrl()
    {
        $http = m::mock(new Services_Twilio_TinyHttp);
        $http->shouldReceive('post')->once()
            ->with('/2010-04-01/Accounts/AC123/Sandbox.json', $this->formHeaders, 'VoiceUrl=foo')
            ->andReturn(array(200, array('Content-Type' => 'application/json'),
                json_encode(array('voice_url' => 'foo'))
            ));
        $client = new Services_Twilio('AC123', '123', '2010-04-01', $http);
        $client->account->sandbox->update('VoiceUrl', 'foo');
        $this->assertEquals('foo', $client->account->sandbox->voice_url);
    }

    function tearDown() {
        m::close();
    }
}
