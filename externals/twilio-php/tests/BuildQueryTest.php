<?php

require_once 'Twilio.php';

class BuildQueryTest extends PHPUnit_Framework_TestCase {

    public function testSimpleQueryString() {
        $data = array(
            'foo' => 'bar',
            'baz' => 'bin',
        );

        $this->assertEquals(Services_Twilio::buildQuery($data), 'foo=bar&baz=bin');
    }

    public function testSameKey() {
        $data = array(
            'foo' => array(
                'bar',
                'baz',
                'bin',
            ),
            'boo' => 'bah',
        );

        $this->assertEquals(Services_Twilio::buildQuery($data),
            'foo=bar&foo=baz&foo=bin&boo=bah');
    }

    public function testKeylessData() {
        $data = array(
            'bar',
            'baz',
            'bin',
        );

        $this->assertEquals(Services_Twilio::buildQuery($data), '0=bar&1=baz&2=bin');
    }

    public function testKeylessDataPrefix() {
        $data = array(
            'bar',
            'baz',
            'bin',
        );

        $this->assertEquals(Services_Twilio::buildQuery($data, 'var'), 'var0=bar&var1=baz&var2=bin');
    }

    public function testQualifiedUserAgent() {
        $expected = Services_Twilio::USER_AGENT . " (php 5.4)";
        $this->assertEquals(Services_Twilio::qualifiedUserAgent("5.4"), $expected);
    }

}

