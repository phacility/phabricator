<?php

use \Mockery as m;

class AvailablePhoneNumbersTest extends PHPUnit_Framework_TestCase {
    function testPartialApplication() {
        $http = m::mock(new Services_Twilio_TinyHttp);
        $http->shouldReceive('get')->once()
            ->with('/2010-04-01/Accounts/AC123/AvailablePhoneNumbers/US/Local.json?AreaCode=510')
            ->andReturn(array(200, array('Content-Type' => 'application/json'),
                json_encode(array('available_phone_numbers' => array(
                    'friendly_name' => '(510) 564-7903'
                )))
            ));
        $client = new Services_Twilio('AC123', '123', '2010-04-01', $http);
        $nums = $client->account->available_phone_numbers->getLocal('US');
        $numsList = $nums->getList(array('AreaCode' => '510'));
        foreach ($numsList as $num) {
            $this->assertEquals('(510) 564-7903', $num->friendly_name);
        }
    }

    function testPagePhoneNumberResource() {
        $http = m::mock(new Services_Twilio_TinyHttp);
        $http->shouldReceive('get')->once()
            ->with('/2010-04-01/Accounts/AC123/AvailablePhoneNumbers.json?Page=0&PageSize=50')
            ->andReturn(array(200, array('Content-Type' => 'application/json'),
                json_encode(array(
                    'total' => 1,
                    'countries' => array(array('country_code' => 'CA'))
                ))
            ));
        $client = new Services_Twilio('AC123', '123', '2010-04-01', $http);
        $page = $client->account->available_phone_numbers->getPage('0');
        $this->assertEquals('CA', $page->countries[0]->country_code);
    }

    function testGetMobile() {
        $http = m::mock(new Services_Twilio_TinyHttp);
        $http->shouldReceive('get')->once()
            ->with('/2010-04-01/Accounts/AC123/AvailablePhoneNumbers/GB/Mobile.json')
            ->andReturn(array(200, array('Content-Type' => 'application/json'),
                json_encode(array('available_phone_numbers' => array(
                    'friendly_name' => '(510) 564-7903'
                )))
            ));
        $client = new Services_Twilio('AC123', '123', '2010-04-01', $http);
        $nums = $client->account->available_phone_numbers->getMobile('GB')->getList();
        foreach ($nums as $num) {
            $this->assertEquals('(510) 564-7903', $num->friendly_name);
        }
    }

    function tearDown() {
        m::close();
    }
}
