<?php

final class AphrontHTTPSinkTestCase extends PhabricatorTestCase {

  public function testHTTPSinkBasics() {
    $sink = new AphrontIsolatedHTTPSink();
    $sink->writeHTTPStatus(200);
    $sink->writeHeaders(array(array('X-Test', 'test')));
    $sink->writeData('test');

    $this->assertEqual(200, $sink->getEmittedHTTPStatus());
    $this->assertEqual(
      array(array('X-Test', 'test')),
      $sink->getEmittedHeaders());
    $this->assertEqual('test', $sink->getEmittedData());
  }

  public function testHTTPSinkStatusCode() {
    $input = $this->tryTestCaseMap(
      array(
        200     => true,
        '201'   => true,
        1       => false,
        1000    => false,
        'apple' => false,
        ''      => false,
      ),
      array($this, 'tryHTTPSinkStatusCode'));
  }

  protected function tryHTTPSinkStatusCode($input) {
    $sink = new AphrontIsolatedHTTPSink();
    $sink->writeHTTPStatus($input);
  }

  public function testHTTPSinkResponseSplitting() {
    $input = $this->tryTestCaseMap(
      array(
        'test'      => true,
        "test\nx"   => false,
        "test\rx"   => false,
        "test\0x"   => false,
      ),
      array($this, 'tryHTTPSinkResponseSplitting'));
  }

  protected function tryHTTPSinkResponseSplitting($input) {
    $sink = new AphrontIsolatedHTTPSink();
    $sink->writeHeaders(array(array('X-Test', $input)));
  }

  public function testHTTPHeaderNames() {
    $input = $this->tryTestCaseMap(
      array(
        'test'  => true,
        'test:' => false,
      ),
      array($this, 'tryHTTPHeaderNames'));
  }

  protected function tryHTTPHeaderNames($input) {
    $sink = new AphrontIsolatedHTTPSink();
    $sink->writeHeaders(array(array($input, 'value')));
  }

  public function testJSONContentSniff() {
    $response = id(new AphrontJSONResponse())
      ->setContent(
        array(
          'x' => '<iframe>',
        ));
    $sink = new AphrontIsolatedHTTPSink();
    $sink->writeResponse($response);

    $this->assertEqual(
      'for (;;);{"x":"\u003ciframe\u003e"}',
      $sink->getEmittedData(),
      pht(
        '%s should prevent content-sniffing attacks.',
        'JSONResponse'));
  }


}
