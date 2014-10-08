<?php

use \Mockery as m;

require_once 'Twilio/Twiml.php';

class TwimlTest extends PHPUnit_Framework_TestCase {

    function tearDown() {
        m::close();
    }
    
    function testEmptyResponse() {
        $r = new Services_Twilio_Twiml();
        $expected = '<Response></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r,
            "Should be an empty response");
    }
    
    public function testSayBasic() {   
        $r = new Services_Twilio_Twiml();
        $r->say("Hello Monkey");
        $expected = '<Response><Say>Hello Monkey</Say></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }
    
    public function testSayLoopThree() {
        $r = new Services_Twilio_Twiml();
        $r->say("Hello Monkey", array("loop" => 3));
        $expected = '<Response><Say loop="3">Hello Monkey</Say></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }
    
    public function testSayLoopThreeWoman() {
        $r = new Services_Twilio_Twiml();
        $r->say("Hello Monkey", array("loop" => 3, "voice"=>"woman"));
        $expected = '<Response><Say loop="3" voice="woman">'
            . 'Hello Monkey</Say></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }
    
    public function testSayConvienceMethod() {
        $r = new Services_Twilio_Twiml();
        $r->say("Hello Monkey", array("language" => "fr"));
        $expected = '<Response><Say language="fr">'
            . 'Hello Monkey</Say></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }
    
    public function testSayUTF8() {
        $r = new Services_Twilio_Twiml();
        $r->say("é tü & må");
        $expected = '<Response><Say>'
            . '&#xE9; t&#xFC; &amp; m&#xE5;</Say></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }
    
    public function testSayNamedEntities() {
        $r = new Services_Twilio_Twiml();
        $r->say("&eacute; t&uuml; &amp; m&aring;");
        $expected = '<Response><Say>'
            . '&#xE9; t&#xFC; &amp; m&#xE5;</Say></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }
    
    public function testSayNumericEntities() {
        $r = new Services_Twilio_Twiml();
        $r->say("&#xE9; t&#xFC; &amp; m&#xE5;");
        $expected = '<Response><Say>'
            . '&#xE9; t&#xFC; &amp; m&#xE5;</Say></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }
    
    public function testPlayBasic() {   
        $r = new Services_Twilio_Twiml();
        $r->play("hello-monkey.mp3");
        $expected = '<Response><Play>hello-monkey.mp3</Play></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }
    
    public function testPlayLoopThree() {
        $r = new Services_Twilio_Twiml();
        $r->play("hello-monkey.mp3", array("loop" => 3));
        $expected = '<Response><Play loop="3">'
            . 'hello-monkey.mp3</Play></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }
    
    public function testPlayConvienceMethod() {
        $r = new Services_Twilio_Twiml();
        $r->play("hello-monkey.mp3", array("loop" => 3));
        $expected = '<Response><Play loop="3">'
            . 'hello-monkey.mp3</Play></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }

    //Test Record Verb
    public function testRecord() {   
        $r = new Services_Twilio_Twiml();
        $r->record();
        $expected = '<Response><Record></Record></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }
    
    public function testRecordActionMethod() {   
        $r = new Services_Twilio_Twiml();
        $r->record(array("action" => "example.com", "method" => "GET"));
        $expected = '<Response><Record action="example.com" '
            . 'method="GET"></Record></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }

    public function testBooleanBecomesString() {   
        $r = new Services_Twilio_Twiml();
        $r->record(array("transcribe" => true));
        $expected = '<Response><Record transcribe="true" '
            . '></Record></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }
    
    public function testRecordMaxLengthKeyTimeout(){
        $r = new Services_Twilio_Twiml();
        $r->record(array("timeout" => 4, "finishOnKey" => "#", 
            "maxLength" => 30));
        $expected = '<Response><Record timeout="4" finishOnKey="#" '
            . 'maxLength="30"></Record></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }
    
    public function testRecordConvienceMethod(){
        $r = new Services_Twilio_Twiml();
        $r->record(array("transcribeCallback" => "example.com"));
        $expected = '<Response><Record '
            . 'transcribeCallback="example.com"></Record></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }
    
    public function testRecordAddAttribute(){
        $r = new Services_Twilio_Twiml();
        $r->record(array("foo" => "bar"));
        $expected = '<Response><Record foo="bar"></Record></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }
    
    //Test Redirect Verb
    public function testRedirect() {
        $r = new Services_Twilio_Twiml();
        $r->redirect();
        $expected = '<Response><Redirect></Redirect></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }

    public function testAmpersandEscaping() {
        $r = new Services_Twilio_Twiml();
        $test_amp = "test&two&amp;three";
        $r->redirect($test_amp);
        $expected = '<Response><Redirect>' .
            'test&amp;two&amp;three</Redirect></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }

    public function testRedirectConvience() {
        $r = new Services_Twilio_Twiml();
        $r->redirect();
        $expected = '<Response><Redirect></Redirect></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }
    public function testRedirectAddAttribute(){
        $r = new Services_Twilio_Twiml();
        $r->redirect(array("foo" => "bar"));
        $expected = '<Response><Redirect foo="bar"></Redirect></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }

    //Test Hangup Verb
    public function testHangup() {
        $r = new Services_Twilio_Twiml();
        $r->hangup();
        $expected = '<Response><Hangup></Hangup></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }
    
    public function testHangupConvience() {
        $r = new Services_Twilio_Twiml();
        $r->hangup();
        $expected = '<Response><Hangup></Hangup></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }
    
    public function testHangupAddAttribute(){
        $r = new Services_Twilio_Twiml();
        $r->hangup(array("foo" => "bar"));
        $expected = '<Response><Hangup foo="bar"></Hangup></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }
    
    //Test Pause Verb
    public function testPause() {
        $r = new Services_Twilio_Twiml();
        $r->pause();
        $expected = '<Response><Pause></Pause></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }
    
    public function testPauseConvience() {
        $r = new Services_Twilio_Twiml();
        $r->pause();
        $expected = '<Response><Pause></Pause></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }
    
    public function testPauseAddAttribute(){
        $r = new Services_Twilio_Twiml();
        $r->pause(array("foo" => "bar"));
        $expected = '<Response><Pause foo="bar"></Pause></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }
    
    //Test Dial Verb
    public function testDial() {
        $r = new Services_Twilio_Twiml();
        $r->dial("1231231234");
        $expected = '<Response><Dial>1231231234</Dial></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }
    
    public function testDialConvience() {
        $r = new Services_Twilio_Twiml();
        $r->dial();
        $expected = '<Response><Dial></Dial></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }
    
    public function testDialAddNumber() {
        $r = new Services_Twilio_Twiml();
        $d = $r->dial();
        $d->number("1231231234");
        $expected = '<Response><Dial><Number>'
            . '1231231234</Number></Dial></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }
    
    public function testDialAddConference() {
        $r = new Services_Twilio_Twiml();
        $d = $r->dial();
        $d->conference("MyRoom");
        $expected = '<Response><Dial><Conference>'
            . 'MyRoom</Conference></Dial></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }
    
    public function testDialAddConferenceConvience() {
        $r = new Services_Twilio_Twiml();
        $d = $r->dial();
        $d->conference("MyRoom", array("startConferenceOnEnter" => "false"));
        $expected = '<Response><Dial><Conference startConferenceOnEnter='
            . '"false">MyRoom</Conference></Dial></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }
    
    public function testDialAddAttribute() {
        $r = new Services_Twilio_Twiml();
        $r->dial(array("foo" => "bar"));
        $expected = '<Response><Dial foo="bar"></Dial></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }
    
    //Test Gather Verb
    public function testGather() {
        $r = new Services_Twilio_Twiml();
        $r->gather();
        $expected = '<Response><Gather></Gather></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }
    
    public function testGatherMethodAction(){
        $r = new Services_Twilio_Twiml();
        $r->gather(array("action"=>"example.com", "method"=>"GET"));
        $expected = '<Response><Gather action="example.com" '
            . 'method="GET"></Gather></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }
    
    public function testGatherActionWithParams(){
        $r = new Services_Twilio_Twiml(); 
        $r->gather(array("action" => "record.php?action=recordPageNow"
            . "&id=4&page=3")); 
        $expected = '<Response><Gather action="record.php?action='
            . 'recordPageNow&amp;id=4&amp;page=3"></Gather></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }
    
    public function testGatherNestedVerbs(){
        $r = new Services_Twilio_Twiml();
        $g = $r->gather(array("action"=>"example.com", "method"=>"GET"));
        $g->say("Hello World");
        $g->play("helloworld.mp3");
        $g->pause();
        $expected = '
            <Response>
                <Gather action="example.com" method="GET">
                    <Say>Hello World</Say>
                    <Play>helloworld.mp3</Play>
                    <Pause></Pause>
                </Gather>
            </Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }
    
    public function testGatherNestedVerbsConvienceMethods(){
        $r = new Services_Twilio_Twiml();
        $g = $r->gather(array("action"=>"example.com", "method"=>"GET"));
        $g->say("Hello World");
        $g->play("helloworld.mp3");
        $g->pause();
        $expected = '
            <Response>
                <Gather action="example.com" method="GET">
                    <Say>Hello World</Say>
                    <Play>helloworld.mp3</Play>
                    <Pause></Pause>
                </Gather>
            </Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }
    
    public function testGatherAddAttribute(){
        $r = new Services_Twilio_Twiml();
        $r->gather(array("foo" => "bar"));
        $expected = '<Response><Gather foo="bar"></Gather></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }
    
    public function testSms() {
        $r = new Services_Twilio_Twiml();
        $r->sms("Hello World");
        $expected = '<Response><Sms>Hello World</Sms></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }
    
    public function testSmsConvience() {
        $r = new Services_Twilio_Twiml();
        $r->sms("Hello World");
        $expected = '<Response><Sms>Hello World</Sms></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }
    
    public function testSmsAddAttribute() {
        $r = new Services_Twilio_Twiml();
        $r->sms(array("foo" => "bar"));
        $expected = '<Response><Sms foo="bar"></Sms></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }
    
    public function testReject() {
        $r = new Services_Twilio_Twiml();
        $r->reject();
        $expected = '<Response><Reject></Reject></Response>';
        $this->assertXmlStringEqualsXmlString($expected, $r);
    }

    function testGeneration() {

        $r = new Services_Twilio_Twiml();
        $r->say('hello');
        $r->dial()->number('123', array('sendDigits' => '456'));
        $r->gather(array('timeout' => 15));

        $doc = simplexml_load_string($r);
        $this->assertEquals('Response', $doc->getName());
        $this->assertEquals('hello', (string) $doc->Say);
        $this->assertEquals('456', (string) $doc->Dial->Number['sendDigits']);
        $this->assertEquals('123', (string) $doc->Dial->Number);
        $this->assertEquals('15', (string) $doc->Gather['timeout']);
    }

}
