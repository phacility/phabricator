<?php

final class ConpherenceRoomTestCase extends ConpherenceTestCase {

  protected function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  public function testOneUserRoomCreate() {
    $creator = $this->generateNewTestUser();
    $participant_phids = array($creator->getPHID());

    $conpherence = $this->createRoom($creator, $participant_phids);

    $this->assertTrue((bool)$conpherence->getID());
    $this->assertEqual(1, count($conpherence->getParticipants()));
  }

  public function testNUserRoomCreate() {
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

    $conpherence = $this->createRoom($creator, $participant_phids);

    $this->assertTrue((bool)$conpherence->getID());
    $this->assertEqual(4, count($conpherence->getParticipants()));
  }

  public function testRoomParticipantAddition() {
    $creator = $this->generateNewTestUser();
    $friend_1 = $this->generateNewTestUser();
    $friend_2 = $this->generateNewTestUser();
    $friend_3 = $this->generateNewTestUser();

    $participant_phids = array(
      $creator->getPHID(),
      $friend_1->getPHID(),
    );

    $conpherence = $this->createRoom($creator, $participant_phids);

    $this->assertTrue((bool)$conpherence->getID());
    $this->assertEqual(2, count($conpherence->getParticipants()));

    // test add by creator
    $participant_phids[] = $friend_2->getPHID();
    $this->addParticipants($creator, $conpherence, array($friend_2->getPHID()));
    $this->assertEqual(3, count($conpherence->getParticipants()));

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
    $this->assertEqual(4, count($conpherence->getParticipants()));
  }

  public function testRoomParticipantDeletion() {
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

    $conpherence = $this->createRoom(
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

  private function createRoom(
    PhabricatorUser $creator,
    array $participant_phids) {

    $conpherence = ConpherenceThread::initializeNewRoom($creator);

    $xactions = array();
    $xactions[] = id(new ConpherenceTransaction())
      ->setTransactionType(
        ConpherenceThreadParticipantsTransaction::TRANSACTIONTYPE)
      ->setNewValue(array('+' => $participant_phids));
    $xactions[] = id(new ConpherenceTransaction())
      ->setTransactionType(
        ConpherenceThreadTitleTransaction::TRANSACTIONTYPE)
      ->setNewValue(pht('Test'));

    id(new ConpherenceEditor())
      ->setActor($creator)
      ->setContentSource($this->newContentSource())
      ->setContinueOnNoEffect(true)
      ->applyTransactions($conpherence, $xactions);

    return $conpherence;
  }

  private function changeEditPolicy(
    PhabricatorUser $actor,
    ConpherenceThread $room,
    $policy) {

    $xactions = array();
    $xactions[] = id(new ConpherenceTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_EDIT_POLICY)
      ->setNewValue($policy);

    id(new ConpherenceEditor())
      ->setActor($actor)
      ->setContentSource($this->newContentSource())
      ->setContinueOnNoEffect(true)
      ->applyTransactions($room, $xactions);
  }


}
