<?php

final class PhabricatorProjectTestDataGenerator
  extends PhabricatorTestDataGenerator {

  public function getGeneratorName() {
    return pht('Projects');
  }

  public function generateObject() {
    $author = $this->loadRandomUser();
    $project = PhabricatorProject::initializeNewProject($author);

    $xactions = array();

    $xactions[] = $this->newTransaction(
      PhabricatorProjectTransaction::TYPE_NAME,
      $this->newProjectTitle());

    $xactions[] = $this->newTransaction(
      PhabricatorProjectTransaction::TYPE_STATUS,
      $this->newProjectStatus());

    // Almost always make the author a member.
    $members = array();
    if ($this->roll(1, 20) > 2) {
      $members[] = $author->getPHID();
    }

    // Add a few other members.
    $size = $this->roll(2, 6, -2);
    for ($ii = 0; $ii < $size; $ii++) {
      $members[] = $this->loadRandomUser()->getPHID();
    }

    $xactions[] = $this->newTransaction(
      PhabricatorTransactions::TYPE_EDGE,
      array(
        '+' => array_fuse($members),
      ),
      array(
        'edge:type' => PhabricatorProjectProjectHasMemberEdgeType::EDGECONST,
      ));

    $editor = id(new PhabricatorProjectTransactionEditor())
      ->setActor($author)
      ->setContentSource($this->getLipsumContentSource())
      ->setContinueOnNoEffect(true)
      ->applyTransactions($project, $xactions);

    return $project;
  }

  protected function newEmptyTransaction() {
    return new PhabricatorProjectTransaction();
  }

  public function newProjectTitle() {
    return id(new PhabricatorProjectNameContextFreeGrammar())
      ->generate();
  }

  public function newProjectStatus() {
    if ($this->roll(1, 20) > 5) {
      return PhabricatorProjectStatus::STATUS_ACTIVE;
    } else {
      return PhabricatorProjectStatus::STATUS_ARCHIVED;
    }
  }
}
