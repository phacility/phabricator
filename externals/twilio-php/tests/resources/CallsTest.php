<?php

use \Mockery as m;

class CallsTest extends PHPUnit_Framework_TestCase {
    /**
     * @dataProvider sidProvider
     */
    function testApplicationSid($sid, $expected)
    {
        $result = Services_Twilio_Rest_Calls::isApplicationSid($sid);
        $this->assertEquals($expected, $result);
    }

    function sidProvider()
    {
        return array(
            array("AP2a0747eba6abf96b7e3c3ff0b4530f6e", true),
            array("CA2a0747eba6abf96b7e3c3ff0b4530f6e", false),
            array("AP2a0747eba6abf96b7e3c3ff0b4530f", false),
            array("http://www.google.com/asdfasdfAP", false),
        );
    }
}

