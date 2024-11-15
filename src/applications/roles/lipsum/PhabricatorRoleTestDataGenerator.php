<?php

final class PhabricatorRoleTestDataGenerator
  extends PhabricatorTestDataGenerator {

  const GENERATORKEY = 'roles';

  public function getGeneratorName() {
    return pht('Roles');
  }

  public function generateObject() {
    $author = $this->loadRandomUser();
    $role = PhabricatorRole::initializeNewRole($author);

    $xactions = array();

    $xactions[] = $this->newTransaction(
      PhabricatorRoleNameTransaction::TRANSACTIONTYPE,
      $this->newRoleTitle());

    $xactions[] = $this->newTransaction(
      PhabricatorRoleStatusTransaction::TRANSACTIONTYPE,
      $this->newRoleStatus());

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
        'edge:type' => PhabricatorRoleRoleHasMemberEdgeType::EDGECONST,
      ));

    $editor = id(new PhabricatorRoleTransactionEditor())
      ->setActor($author)
      ->setContentSource($this->getLipsumContentSource())
      ->setContinueOnNoEffect(true)
      ->applyTransactions($role, $xactions);

    return $role;
  }

  protected function newEmptyTransaction() {
    return new PhabricatorRoleTransaction();
  }

  public function newRoleTitle() {
    return id(new PhabricatorRoleNameContextFreeGrammar())
      ->generate();
  }

  public function newRoleStatus() {
    if ($this->roll(1, 20) > 5) {
      return PhabricatorRoleStatus::STATUS_ACTIVE;
    } else {
      return PhabricatorRoleStatus::STATUS_ARCHIVED;
    }
  }
}
