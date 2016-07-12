<?php

abstract class ConpherenceTestCase extends PhabricatorTestCase {

  protected function addParticipants(
    PhabricatorUser $actor,
    ConpherenceThread $conpherence,
    array $participant_phids) {

    $xactions = array(
      id(new ConpherenceTransaction())
        ->setTransactionType(ConpherenceTransaction::TYPE_PARTICIPANTS)
        ->setNewValue(array('+' => $participant_phids)),
    );
    $editor = id(new ConpherenceEditor())
      ->setActor($actor)
      ->setContentSource($this->newContentSource())
      ->applyTransactions($conpherence, $xactions);

  }

  protected function removeParticipants(
    PhabricatorUser $actor,
    ConpherenceThread $conpherence,
    array $participant_phids) {

    $xactions = array(
      id(new ConpherenceTransaction())
        ->setTransactionType(ConpherenceTransaction::TYPE_PARTICIPANTS)
        ->setNewValue(array('-' => $participant_phids)),
    );
    $editor = id(new ConpherenceEditor())
      ->setActor($actor)
      ->setContentSource($this->newContentSource())
      ->applyTransactions($conpherence, $xactions);
  }

  protected function addMessageWithFile(
    PhabricatorUser $actor,
    ConpherenceThread $conpherence) {

    $file = $this->generateTestFile($actor);
    $message = Filesystem::readRandomCharacters(64).
      sprintf(' {%s} ', $file->getMonogram());

    $editor = id(new ConpherenceEditor())
      ->setActor($actor)
      ->setContentSource($this->newContentSource());

    $xactions = $editor->generateTransactionsFromText(
      $actor,
      $conpherence,
      $message);

    return $editor->applyTransactions($conpherence, $xactions);
  }

  private function generateTestFile(PhabricatorUser $actor) {
    $engine = new PhabricatorTestStorageEngine();
    $data = Filesystem::readRandomCharacters(64);

    $params = array(
      'name' => 'test.'.$actor->getPHID(),
      'viewPolicy' => $actor->getPHID(),
      'authorPHID' => $actor->getPHID(),
      'storageEngines' => array(
        $engine,
      ),
    );

    $file = PhabricatorFile::newFromFileData($data, $params);
    $file->save();

    return $file;
  }

}
