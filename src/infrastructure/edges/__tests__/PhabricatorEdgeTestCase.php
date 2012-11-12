<?php

final class PhabricatorEdgeTestCase extends PhabricatorTestCase {

  protected function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  public function testCycleDetection() {

    // The editor should detect that this introduces a cycle and prevent the
    // edit.

    $user = new PhabricatorUser();

    $obj1 = id(new HarbormasterObject())->save();
    $obj2 = id(new HarbormasterObject())->save();
    $phid1 = $obj1->getPHID();
    $phid2 = $obj2->getPHID();

    $editor = id(new PhabricatorEdgeEditor())
      ->setActor($user)
      ->addEdge($phid1, PhabricatorEdgeConfig::TYPE_TEST_NO_CYCLE, $phid2)
      ->addEdge($phid2, PhabricatorEdgeConfig::TYPE_TEST_NO_CYCLE, $phid1);

    $caught = null;
    try {
      $editor->save();
    } catch (Exception $ex) {
      $caught = $ex;
    }

    $this->assertEqual(
      true,
      $caught instanceof Exception);


    // The first edit should go through (no cycle), bu the second one should
    // fail (it introduces a cycle).

    $editor = id(new PhabricatorEdgeEditor())
      ->setActor($user)
      ->addEdge($phid1, PhabricatorEdgeConfig::TYPE_TEST_NO_CYCLE, $phid2)
      ->save();

    $editor = id(new PhabricatorEdgeEditor())
      ->setActor($user)
      ->addEdge($phid2, PhabricatorEdgeConfig::TYPE_TEST_NO_CYCLE, $phid1);

    $caught = null;
    try {
      $editor->save();
    } catch (Exception $ex) {
      $caught = $ex;
    }

    $this->assertEqual(
      true,
      $caught instanceof Exception);
  }


}
