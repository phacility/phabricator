<?php

use \Mockery as m;

class QueuesTest extends PHPUnit_Framework_TestCase {

    protected $formHeaders = array('Content-Type' => 'application/x-www-form-urlencoded');

    function testCreate() {
        $http = m::mock(new Services_Twilio_TinyHttp);
        $http->shouldReceive('post')->once()
            ->with('/2010-04-01/Accounts/AC123/Queues.json', $this->formHeaders,
                'FriendlyName=foo&MaxSize=123')
            ->andReturn(array(200, array('Content-Type' => 'application/json'),
                json_encode(array('sid' => 'QQ123', 'average_wait_time' => 0))
            ));
        $client = new Services_Twilio('AC123', '123', '2010-04-01', $http);
        $queue = $client->account->queues->create('foo',
            array('MaxSize' => 123));
        $this->assertSame($queue->sid, 'QQ123');
        $this->assertSame($queue->average_wait_time, 0);
    }

    function tearDown() {
        m::close();
    }
}

