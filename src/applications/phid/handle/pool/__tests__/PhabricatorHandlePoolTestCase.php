<?php

final class PhabricatorHandlePoolTestCase extends PhabricatorTestCase {

  protected function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  public function testHandlePools() {
    // A lot of the batch/just-in-time/cache behavior of handle pools is not
    // observable by design, so these tests don't directly cover it.

    $viewer = $this->generateNewTestUser();
    $viewer_phid = $viewer->getPHID();

    $phids = array($viewer_phid);

    $handles = $viewer->loadHandles($phids);

    // The handle load hasn't happened yet, but we can't directly observe that.

    // Test Countable behaviors.
    $this->assertEqual(1, count($handles));

    // Test ArrayAccess behaviors.
    $this->assertEqual(
      array($viewer_phid),
      array_keys(iterator_to_array($handles)));
    $this->assertEqual(true, $handles[$viewer_phid]->isComplete());
    $this->assertEqual($viewer_phid, $handles[$viewer_phid]->getPHID());
    $this->assertTrue(isset($handles[$viewer_phid]));
    $this->assertFalse(isset($handles['quack']));

    // Test Iterator behaviors.
    foreach ($handles as $key => $handle) {
      $this->assertEqual($viewer_phid, $key);
      $this->assertEqual($viewer_phid, $handle->getPHID());
    }

    // Do this twice to make sure the handle list is rewindable.
    foreach ($handles as $key => $handle) {
      $this->assertEqual($viewer_phid, $key);
      $this->assertEqual($viewer_phid, $handle->getPHID());
    }

    $more_handles = $viewer->loadHandles($phids);

    // This is testing that we got back a reference to the exact same object,
    // which implies the caching behavior is working correctly.
    $this->assertEqual(
      $handles[$viewer_phid],
      $more_handles[$viewer_phid],
      pht('Handles should use viewer handle pool cache.'));
  }

}
