<?php

final class PhabricatorManiphestTaskTestDataGenerator
  extends PhabricatorTestDataGenerator {

  public function generate() {
    $authorPHID = $this->loadPhabrictorUserPHID();
    $author = id(new PhabricatorUser())
          ->loadOneWhere('phid = %s', $authorPHID);
    $task = id(new ManiphestTask())
      ->setSubPriority($this->generateTaskSubPriority())
      ->setAuthorPHID($authorPHID)
      ->setTitle($this->generateTitle());
    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_UNKNOWN,
      array());
    $template = id(new ManiphestTransaction())
      ->setAuthorPHID($authorPHID)
      ->setContentSource($content_source);
    // Accumulate Transactions
    $changes = array();
    $changes[ManiphestTransactionType::TYPE_TITLE] =
      $this->generateTitle();
    $changes[ManiphestTransactionType::TYPE_DESCRIPTION] =
      $this->generateDescription();
    $changes[ManiphestTransactionType::TYPE_OWNER] =
      $this->loadOwnerPHID();
    $changes[ManiphestTransactionType::TYPE_STATUS] =
      $this->generateTaskStatus();
    $changes[ManiphestTransactionType::TYPE_PRIORITY] =
      $this->generateTaskPriority();
    $changes[ManiphestTransactionType::TYPE_CCS] =
      $this->getCCPHIDs();
    $changes[ManiphestTransactionType::TYPE_PROJECTS] =
      $this->getProjectPHIDs();
    $transactions = array();
    foreach ($changes as $type => $value) {
      $transaction = clone $template;
      $transaction->setTransactionType($type);
      $transaction->setNewValue($value);
      $transactions[] = $transaction;
    }
    // Accumulate Auxiliary Transactions
    $aux_fields = id(ManiphestTaskExtensions::newExtensions())
      ->loadFields($task, $author);
    if ($aux_fields) {
      foreach ($aux_fields as $aux_field) {
        $transaction = clone $template;
        $transaction->setTransactionType(
          ManiphestTransactionType::TYPE_AUXILIARY);
        $aux_key = $aux_field->getAuxiliaryKey();
        $transaction->setMetadataValue('aux:key', $aux_key);
        $transaction->setNewValue($aux_field->getValueForStorage());
        $transactions[] = $transaction;
      }
    }
    // Apply Transactions
    $editor = id(new ManiphestTransactionEditor())
      ->setActor($author)
      ->applyTransactions($task, $transactions);
    return $task->save();
  }

  public function getCCPHIDs() {
    $ccs = array();
    for ($i = 0; $i < rand(1, 4);$i++) {
      $ccs[] = $this->loadPhabrictorUserPHID();
    }
    return $ccs;
  }

  public function getProjectPHIDs() {
    $projects = array();
    for ($i = 0; $i < rand(1, 4);$i++) {
      $projects[] = $this->loadOneRandom("PhabricatorProject")->getPHID();
    }
    return $projects;
  }

  public function loadOwnerPHID() {
    if (rand(0, 3) == 0) {
      return null;
    } else {
      return $this->loadPhabrictorUserPHID();
    }
  }

  public function generateTitle() {
    return id(new PhutilLipsumContextFreeGrammar())
      ->generate();
  }

  public function generateDescription() {
    return id(new PhutilLipsumContextFreeGrammar())
      ->generateSeveral(rand(30, 40));
  }

  public function generateTaskPriority() {
    return array_rand(ManiphestTaskPriority::getTaskPriorityMap());
  }

  public function generateTaskSubPriority() {
    return rand(2 << 16, 2 << 32);
  }

  public function generateTaskStatus() {
    $statuses = array_keys(ManiphestTaskStatus::getTaskStatusMap());
    // Make sure 4/5th of all generated Tasks are open
    $random = rand(0, 4);
    if ($random != 0) {
      return ManiphestTaskStatus::STATUS_OPEN;
    } else {
      return array_rand($statuses);
    }
  }


}
