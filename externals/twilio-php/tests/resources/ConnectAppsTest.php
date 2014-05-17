<?php

use \Mockery as m;

class ConnectAppsTest extends PHPUnit_Framework_TestCase {

    function testUpdateWithArray() {
        $http = m::mock(new Services_Twilio_TinyHttp);
        $http->shouldReceive('get')->once()
            ->with('/2010-04-01/Accounts/AC123/ConnectApps/CN123.json')
            ->andReturn(array(200, array('Content-Type' => 'application/json'),
                json_encode(array('friendly_name' => 'foo'))
            ));
        $http->shouldReceive('post')->once()
            ->with('/2010-04-01/Accounts/AC123/ConnectApps/CN123.json',
            array('Content-Type' => 'application/x-www-form-urlencoded'),
            'FriendlyName=Bar')
            ->andReturn(array(200, array('Content-Type' => 'application/json'),
                json_encode(array('friendly_name' => 'Bar'))
            ));
        $client = new Services_Twilio('AC123', '123', '2010-04-01', $http);
        $cn = $client->account->connect_apps->get('CN123');
        $this->assertEquals('foo', $cn->friendly_name);
        $cn->update(array('FriendlyName' => 'Bar'));
        $this->assertEquals('Bar', $cn->friendly_name);
    }

    function testUpdateWithOneParam()
    {
        $http = m::mock(new Services_Twilio_TinyHttp);
        $http->shouldReceive('get')->once()
            ->with('/2010-04-01/Accounts/AC123/ConnectApps/CN123.json')
            ->andReturn(array(200, array('Content-Type' => 'application/json'),
                json_encode(array('friendly_name' => 'foo'))
            ));
        $http->shouldReceive('post')->once()
            ->with('/2010-04-01/Accounts/AC123/ConnectApps/CN123.json',
            array('Content-Type' => 'application/x-www-form-urlencoded'),
            'FriendlyName=Bar')
            ->andReturn(array(200, array('Content-Type' => 'application/json'),
                json_encode(array('friendly_name' => 'Bar'))
            ));
        $client = new Services_Twilio('AC123', '123', '2010-04-01', $http);
        $cn = $client->account->connect_apps->get('CN123');
        $this->assertEquals('foo', $cn->friendly_name);
        $cn->update('FriendlyName', 'Bar');
        $this->assertEquals('Bar', $cn->friendly_name);
    }

    function tearDown()
    {
        m::close();
    }
}
