<?php

final class DiffusionMercurialWireSSHTestCase extends PhabricatorTestCase {

  public function testMercurialClientWireProtocolParser() {
    $data = dirname(__FILE__).'/hgwiredata/';
    $dir = Filesystem::listDirectory($data, $include_hidden = false);
    foreach ($dir as $file) {
      $raw = Filesystem::readFile($data.$file);
      $raw = explode("\n~~~~~~~~~~\n", $raw, 2);
      $this->assertEqual(2, count($raw));
      $expect = phutil_json_decode($raw[1]);
      $this->assertTrue(is_array($expect), $file);

      $this->assertParserResult($expect, $raw[0], $file);
    }
  }

  private function assertParserResult(array $expect, $input, $file) {
    list($x, $y) = PhutilSocketChannel::newChannelPair();
    $xp = new DiffusionMercurialWireClientSSHProtocolChannel($x);

    $y->write($input);
    $y->flush();
    $y->closeWriteChannel();

    $messages = array();
    for ($ii = 0; $ii < count($expect); $ii++) {
      try {
        $messages[] = $xp->waitForMessage();
      } catch (Exception $ex) {
        // This is probably the parser not producing as many messages as
        // we expect. Log the exception, but continue to the assertion below
        // since that will often be easier to diagnose.
        phlog($ex);
        break;
      }
    }

    $this->assertEqual($expect, $messages, $file);

    // Now, make sure the channel doesn't have *more* messages than we expect.
    // Specifically, it should throw when we try to read another message.
    $caught = null;
    try {
      $xp->waitForMessage();
    } catch (Exception $ex) {
      $caught = $ex;
    }

    $this->assertTrue(
      ($caught instanceof Exception),
      "No extra messages for '{$file}'.");
  }

}
