<?php

require_once 'Twilio/Capability.php';

class CapabilityTest extends PHPUnit_Framework_TestCase {

    public function testNoPermissions() {
        $token = new Services_Twilio_Capability('AC123', 'foo');
        $payload = JWT::decode($token->generateToken(), 'foo');
        $this->assertEquals($payload->iss, "AC123");
        $this->assertEquals($payload->scope, '');
    }

    public function testInboundPermissions() {
        $token = new Services_Twilio_Capability('AC123', 'foo');
        $token->allowClientIncoming("andy");
        $payload = JWT::decode($token->generateToken(), 'foo');

        $eurl = "scope:client:incoming?clientName=andy";
        $this->assertEquals($payload->scope, $eurl);
    }

    public function testOutboundPermissions() {
        $token = new Services_Twilio_Capability('AC123', 'foo');
        $token->allowClientOutgoing("AP123");
        $payload = JWT::decode($token->generateToken(), 'foo');;
        $eurl = "scope:client:outgoing?appSid=AP123";
        $this->assertContains($eurl, $payload->scope);
    }

    public function testOutboundPermissionsParams() {
        $token = new Services_Twilio_Capability('AC123', 'foo');
        $token->allowClientOutgoing("AP123", array("foobar" => 3));
        $payload = JWT::decode($token->generateToken(), 'foo');

        $eurl = "scope:client:outgoing?appSid=AP123&appParams=foobar%3D3";
        $this->assertEquals($payload->scope, $eurl);
    }

    public function testEvents() {
        $token = new Services_Twilio_Capability('AC123', 'foo');
        $token->allowEventStream();
        $payload = JWT::decode($token->generateToken(), 'foo');

        $event_uri = "scope:stream:subscribe?path=%2F2010"
            . "-04-01%2FEvents&params=";
        $this->assertEquals($payload->scope, $event_uri);
    }

    public function testEventsWithFilters() {
        $token = new Services_Twilio_Capability('AC123', 'foo');
        $token->allowEventStream(array("foobar" => "hey"));
        $payload = JWT::decode($token->generateToken(), 'foo');

        $event_uri = "scope:stream:subscribe?path=%2F2010-"
            . "04-01%2FEvents&params=foobar%3Dhey";
        $this->assertEquals($payload->scope, $event_uri);
    }


    public function testDecode() {
        $token = new Services_Twilio_Capability('AC123', 'foo');
        $token->allowClientOutgoing("AP123", array("foobar"=> 3));
        $token->allowClientIncoming("andy");
        $token->allowEventStream();

        $outgoing_uri = "scope:client:outgoing?appSid="
            . "AP123&appParams=foobar%3D3&clientName=andy";
        $incoming_uri = "scope:client:incoming?clientName=andy";
        $event_uri = "scope:stream:subscribe?path=%2F2010-04-01%2FEvents";

        $payload = JWT::decode($token->generateToken(), 'foo');
        $scope = $payload->scope;

        $this->assertContains($outgoing_uri, $scope);
        $this->assertContains($incoming_uri, $scope);
        $this->assertContains($event_uri, $scope);
    }


    function testDecodeWithAuthToken() {
        try {
            $token = new Services_Twilio_Capability('AC123', 'foo');
            $payload = JWT::decode($token->generateToken(), 'foo');
            $this->assertSame($payload->iss, 'AC123');
        } catch (UnexpectedValueException $e) {
            $this->assertTrue(false, "Could not decode with 'foo'");
        }
    }

    function testClientNameValidation() {
        $this->setExpectedException('InvalidArgumentException');
        $token = new Services_Twilio_Capability('AC123', 'foo');
        $token->allowClientIncoming('@');
        $this->fail('exception should have been raised');
    }

    function zeroLengthNameInvalid() {
        $this->setExpectedException('InvalidArgumentException');
        $token = new Services_Twilio_Capability('AC123', 'foo');
        $token->allowClientIncoming("");
        $this->fail('exception should have been raised');
    }


}
