<?php

final class PhabricatorProjectTestDataGenerator
  extends PhabricatorTestDataGenerator {

  private $xactions = array();

  public function generate() {
    $title = $this->generateTitle();
    $author = $this->loadPhabrictorUser();
    $author_phid = $author->getPHID();
    $project = id(new PhabricatorProject())
      ->setName($title)
      ->setAuthorPHID($author_phid);

    $this->addTransaction(
      PhabricatorProjectTransaction::TYPE_NAME,
      $title);
    $this->addTransaction(
      PhabricatorProjectTransaction::TYPE_MEMBERS,
      $this->loadMembersWithAuthor($author_phid));
    $this->addTransaction(
      PhabricatorProjectTransaction::TYPE_STATUS,
      $this->generateProjectStatus());
    $this->addTransaction(
      PhabricatorTransactions::TYPE_VIEW_POLICY,
      PhabricatorPolicies::POLICY_PUBLIC);
    $this->addTransaction(
      PhabricatorTransactions::TYPE_EDIT_POLICY,
      PhabricatorPolicies::POLICY_PUBLIC);
    $this->addTransaction(
      PhabricatorTransactions::TYPE_JOIN_POLICY,
      PhabricatorPolicies::POLICY_PUBLIC);

    $editor = id(new PhabricatorProjectTransactionEditor())
      ->setActor($author)
      ->setContentSource(PhabricatorContentSource::newConsoleSource())
      ->applyTransactions($project, $this->xactions);

    return $project->save();
  }

  private function addTransaction($type, $value) {
    $this->xactions[] = id(new PhabricatorProjectTransaction())
      ->setTransactionType($type)
      ->setNewValue($value);
  }


  public function loadMembersWithAuthor($author) {
    $members = array($author);
    for ($i = 0; $i < rand(10, 20);$i++) {
      $members[] = $this->loadPhabrictorUserPHID();
    }
    return $members;
  }

  public function generateTitle() {
    return id(new PhutilLipsumContextFreeGrammar())
      ->generate();
  }

  public function generateDescription() {
    return id(new PhutilLipsumContextFreeGrammar())
      ->generateSeveral(rand(30, 40));
  }

  public function generateProjectStatus() {
    $statuses = array_keys(PhabricatorProjectStatus::getStatusMap());
    // Make sure 4/5th of all generated Projects are active
    $random = rand(0, 4);
    if ($random != 0) {
      return $statuses[0];
    } else {
      return $statuses[1];
    }
  }
}
