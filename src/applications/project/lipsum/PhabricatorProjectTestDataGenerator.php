<?php

final class PhabricatorProjectTestDataGenerator
  extends PhabricatorTestDataGenerator {

  private $xactions = array();

  public function generate() {
    $title = $this->generateTitle();
    $author = $this->loadPhabrictorUser();
    $authorPHID = $author->getPHID();
    $project = id(new PhabricatorProject())
      ->setName($title)
      ->setAuthorPHID($authorPHID);

    $this->addTransaction(
      PhabricatorProjectTransactionType::TYPE_NAME,
      $title);
    $this->addTransaction(
      PhabricatorProjectTransactionType::TYPE_MEMBERS,
      $this->loadMembersWithAuthor($authorPHID));
    $this->addTransaction(
      PhabricatorProjectTransactionType::TYPE_STATUS,
      $this->generateProjectStatus());
    $this->addTransaction(
      PhabricatorProjectTransactionType::TYPE_CAN_VIEW,
      PhabricatorPolicies::POLICY_PUBLIC);
    $this->addTransaction(
      PhabricatorProjectTransactionType::TYPE_CAN_EDIT,
      PhabricatorPolicies::POLICY_PUBLIC);
    $this->addTransaction(
      PhabricatorProjectTransactionType::TYPE_CAN_JOIN,
      PhabricatorPolicies::POLICY_PUBLIC);

    $editor = id(new PhabricatorProjectEditor($project))
      ->setActor($author)
      ->applyTransactions($this->xactions);

    $profile = id(new PhabricatorProjectProfile())
      ->setBlurb($this->generateDescription())
      ->setProjectPHID($project->getPHID())
      ->save();

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
