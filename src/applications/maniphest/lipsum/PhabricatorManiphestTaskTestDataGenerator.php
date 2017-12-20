<?php

final class PhabricatorManiphestTaskTestDataGenerator
  extends PhabricatorTestDataGenerator {

  const GENERATORKEY = 'tasks';

  public function getGeneratorName() {
    return pht('Maniphest Tasks');
  }

  public function generateObject() {
    $author_phid = $this->loadPhabricatorUserPHID();
    $author = id(new PhabricatorUser())
      ->loadOneWhere('phid = %s', $author_phid);
    $task = ManiphestTask::initializeNewTask($author)
      ->setSubPriority($this->generateTaskSubPriority())
      ->setTitle($this->generateTitle());

    $content_source = $this->getLipsumContentSource();

    $template = new ManiphestTransaction();
    // Accumulate Transactions
    $changes = array();
    $changes[ManiphestTaskTitleTransaction::TRANSACTIONTYPE] =
      $this->generateTitle();
    $changes[ManiphestTaskDescriptionTransaction::TRANSACTIONTYPE] =
      $this->generateDescription();
    $changes[ManiphestTaskOwnerTransaction::TRANSACTIONTYPE] =
      $this->loadOwnerPHID();
    $changes[ManiphestTaskStatusTransaction::TRANSACTIONTYPE] =
      $this->generateTaskStatus();
    $changes[ManiphestTaskPriorityTransaction::TRANSACTIONTYPE] =
      $this->generateTaskPriority();
    $changes[PhabricatorTransactions::TYPE_SUBSCRIBERS] =
      array('=' => $this->getCCPHIDs());
    $transactions = array();
    foreach ($changes as $type => $value) {
      $transaction = clone $template;
      $transaction->setTransactionType($type);
      $transaction->setNewValue($value);
      $transactions[] = $transaction;
    }

    $transactions[] = id(new ManiphestTransaction())
        ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
        ->setMetadataValue(
          'edge:type',
          PhabricatorProjectObjectHasProjectEdgeType::EDGECONST)
        ->setNewValue(
          array(
            '=' => array_fuse($this->getProjectPHIDs()),
          ));

    // Apply Transactions
    $editor = id(new ManiphestTransactionEditor())
      ->setActor($author)
      ->setContentSource($content_source)
      ->setContinueOnNoEffect(true)
      ->setContinueOnMissingFields(true)
      ->applyTransactions($task, $transactions);
    return $task;
  }

  public function getCCPHIDs() {
    $ccs = array();
    for ($i = 0; $i < rand(1, 4);$i++) {
      $ccs[] = $this->loadPhabricatorUserPHID();
    }
    return $ccs;
  }

  public function getProjectPHIDs() {
    $projects = array();
    for ($i = 0; $i < rand(1, 4);$i++) {
      $project = $this->loadOneRandom('PhabricatorProject');
      if ($project) {
        $projects[] = $project->getPHID();
      }
    }
    return $projects;
  }

  public function loadOwnerPHID() {
    if (rand(0, 3) == 0) {
      return null;
    } else {
      return $this->loadPhabricatorUserPHID();
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
    $pri = array_rand(ManiphestTaskPriority::getTaskPriorityMap());
    $keyword_map = ManiphestTaskPriority::getTaskPriorityKeywordsMap();
    $keyword = head(idx($keyword_map, $pri));
    return $keyword;
  }

  public function generateTaskSubPriority() {
    return rand(2 << 16, 2 << 32);
  }

  public function generateTaskStatus() {
    $statuses = array_keys(ManiphestTaskStatus::getTaskStatusMap());
    // Make sure 4/5th of all generated Tasks are open
    $random = rand(0, 4);
    if ($random != 0) {
      return ManiphestTaskStatus::getDefaultStatus();
    } else {
      return array_rand($statuses);
    }
  }


}
