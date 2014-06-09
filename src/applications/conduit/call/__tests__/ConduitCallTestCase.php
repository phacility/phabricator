<?php

final class ConduitCallTestCase extends PhabricatorTestCase {

  public function testConduitPing() {
    $call = new ConduitCall('conduit.ping', array());
    $call->setForceLocal(true);
    $result = $call->execute();

    $this->assertFalse(empty($result));
  }

  public function testConduitAuth() {
    $call = new ConduitCall('user.whoami', array(), true);
    $call->setForceLocal(true);

    $caught = null;
    try {
      $result = $call->execute();
    } catch (ConduitException $ex) {
      $caught = $ex;
    }

    $this->assertTrue(
      ($caught instanceof ConduitException),
      'user.whoami should require authentication');
  }
}
