<?php

final class ConpherenceThreadTestCase extends ConpherenceTestCase {

  protected function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  public function testOneUserThreadCreate() {
    $creator = $this->generateNewTestUser();
    $participant_phids = array($creator->getPHID());

    $conpherence = $this->createThread($creator, $participant_phids);

    $this->assertTrue((bool)$conpherence->getID());
    $this->assertEqual(1, count($conpherence->getParticipants()));
    $this->assertEqual(
      $participant_phids,
      $conpherence->getRecentParticipantPHIDs());
  }

  public function testNUserThreadCreate() {
    $creator = $this->generateNewTestUser();
    $friend_1 = $this->generateNewTestUser();
    $friend_2 = $this->generateNewTestUser();
    $friend_3 = $this->generateNewTestUser();

    $participant_phids = array(
      $creator->getPHID(),
      $friend_1->getPHID(),
      $friend_2->getPHID(),
      $friend_3->getPHID(),
    );

    $conpherence = $this->createThread($creator, $participant_phids);

    $this->assertTrue((bool)$conpherence->getID());
    $this->assertEqual(4, count($conpherence->getParticipants()));
    $this->assertEqual(
      $participant_phids,
      $conpherence->getRecentParticipantPHIDs());
  }

  public function testThreadParticipantAddition() {
    $creator = $this->generateNewTestUser();
    $friend_1 = $this->generateNewTestUser();
    $friend_2 = $this->generateNewTestUser();
    $friend_3 = $this->generateNewTestUser();

    $participant_phids = array(
      $creator->getPHID(),
      $friend_1->getPHID(),
    );

    $conpherence = $this->createThread($creator, $participant_phids);

    $this->assertTrue((bool)$conpherence->getID());
    $this->assertEqual(2, count($conpherence->getParticipants()));
    $this->assertEqual(
      $participant_phids,
      $conpherence->getRecentParticipantPHIDs());

    // test add by creator
    $participant_phids[] = $friend_2->getPHID();
    $this->addParticipants($creator, $conpherence, array($friend_2->getPHID()));
    $this->assertEqual(
      $participant_phids,
      $conpherence->getRecentParticipantPHIDs());

    // test add by other participant, so recent participation should
    // meaningfully change
    $participant_phids = array(
      $friend_2->getPHID(),  // actor
      $creator->getPHID(),   // last actor
      $friend_1->getPHID(),
      $friend_3->getPHID(),  // new addition
    );
    $this->addParticipants(
      $friend_2,
      $conpherence,
      array($friend_3->getPHID()));
    $this->assertEqual(
      $participant_phids,
      $conpherence->getRecentParticipantPHIDs());
  }

  public function testThreadParticipantDeletion() {
    $creator = $this->generateNewTestUser();
    $friend_1 = $this->generateNewTestUser();
    $friend_2 = $this->generateNewTestUser();
    $friend_3 = $this->generateNewTestUser();

    $participant_map = array(
      $creator->getPHID() => $creator,
      $friend_1->getPHID() => $friend_1,
      $friend_2->getPHID() => $friend_2,
      $friend_3->getPHID() => $friend_3,
    );

    $conpherence = $this->createThread(
      $creator,
      array_keys($participant_map));

    foreach ($participant_map as $phid => $user) {
      $this->removeParticipants($user, $conpherence, array($phid));
      unset($participant_map[$phid]);
      $this->assertEqual(
        count($participant_map),
        count($conpherence->getParticipants()));
    }
  }

  public function testAddMessageWithFileAttachments() {
    $creator = $this->generateNewTestUser();
    $friend_1 = $this->generateNewTestUser();
    $policy_exception_user = $this->generateNewTestUser();

    $participant_map = array(
      $creator->getPHID() => $creator,
      $friend_1->getPHID() => $friend_1,
    );

    $conpherence = $this->createThread(
      $creator,
      array_keys($participant_map));

    foreach ($participant_map as $phid => $user) {
      $xactions = $this->addMessageWithFile($user, $conpherence);
      $this->assertEqual(2, count($xactions), pht('hi'));
    }

    $caught = null;
    try {
      $xactions = $this->addMessageWithFile(
        $policy_exception_user,
        $conpherence);
    } catch (PhabricatorPolicyException $ex) {
      $caught = $ex;
    }
    $this->assertTrue(
      $caught instanceof PhabricatorPolicyException,
      pht(
        'User not participating in thread should get policy exception '.
        'trying to add message.'));
    $this->assertTrue(
      $conpherence->establishConnection('w')->isReadLocking(),
      pht(
        'Conpherence object should still be read locked from policy '.
        'exception.'));
    $conpherence->endReadLocking();
    $conpherence->killTransaction();
  }

  private function createThread(
    PhabricatorUser $creator,
    array $participant_phids) {

    list($errors, $conpherence) = ConpherenceEditor::createThread(
      $creator,
      $participant_phids,
      pht('Test'),
      pht('Test'),
      PhabricatorContentSource::newConsoleSource());
    return $conpherence;
  }

}
