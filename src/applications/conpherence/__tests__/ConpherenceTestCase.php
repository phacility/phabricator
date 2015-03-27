<?php

abstract class ConpherenceTestCase extends PhabricatorTestCase {

  protected function addParticipants(
    PhabricatorUser $actor,
    ConpherenceThread $conpherence,
    array $participant_phids) {

    $xactions = array(id(new ConpherenceTransaction())
      ->setTransactionType(ConpherenceTransactionType::TYPE_PARTICIPANTS)
      ->setNewValue(array('+' => $participant_phids)),);
    $editor = id(new ConpherenceEditor())
      ->setActor($actor)
      ->setContentSource(PhabricatorContentSource::newConsoleSource())
      ->applyTransactions($conpherence, $xactions);

  }

  protected function removeParticipants(
    PhabricatorUser $actor,
    ConpherenceThread $conpherence,
    array $participant_phids) {

    $xactions = array(id(new ConpherenceTransaction())
      ->setTransactionType(ConpherenceTransactionType::TYPE_PARTICIPANTS)
      ->setNewValue(array('-' => $participant_phids)),);
    $editor = id(new ConpherenceEditor())
      ->setActor($actor)
      ->setContentSource(PhabricatorContentSource::newConsoleSource())
      ->applyTransactions($conpherence, $xactions);
  }


}
