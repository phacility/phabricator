<?php

use \Mockery as m;

class MediaTest extends PHPUnit_Framework_TestCase {

    function testUseSpecialListKey() {
        $http = m::mock(new Services_Twilio_TinyHttp);
        $http->shouldReceive('get')->once()
            ->with('/2010-04-01/Accounts/AC123/Messages/MM123/Media.json?Page=0&PageSize=50')
            ->andReturn(array(200, array('Content-Type' => 'application/json'),
                json_encode(array(
                    'end' => '0',
                    'total' => '2',
                    'media_list' => array(
                        array('sid' => 'ME123'),
                        array('sid' => 'ME456')
                    ),
                    'next_page_uri' => 'null',
                    'start' => 0
                ))
            ));
        $client = new Services_Twilio('AC123', '123', '2010-04-01', $http);
        $media_list = $client->account->messages->get('MM123')->media->getPage()->getItems();
        $this->assertEquals(count($media_list), 2);
    }

}
