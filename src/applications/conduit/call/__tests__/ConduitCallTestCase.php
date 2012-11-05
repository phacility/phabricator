<?php

final class ConduitCallTestCase extends PhabricatorTestCase {

  public function testConduitPing() {
    $call = new ConduitCall('conduit.ping', array());
    $result = $call->execute();

    $this->assertEqual(false, empty($result));
  }

  public function testConduitAuth() {
    $call = new ConduitCall('user.whoami', array());

    $caught = null;
    try {
      $result = $call->execute();
    } catch (ConduitException $ex) {
      $caught = $ex;
    }

    $this->assertEqual(
      true,
      ($caught instanceof ConduitException),
      "user.whoami should require authentication");
  }
}
