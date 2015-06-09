<?php

final class PhabricatorApplicationTransactionNoEffectException
  extends Exception {

  private $transactions;
  private $anyEffect;
  private $hasComment;

  public function __construct(array $transactions, $any_effect, $has_comment) {
    assert_instances_of($transactions, 'PhabricatorApplicationTransaction');

    $this->transactions = $transactions;
    $this->anyEffect = $any_effect;
    $this->hasComment = $has_comment;

    $message = array();
    $message[] = pht('Transactions have no effect:');
    foreach ($this->transactions as $transaction) {
      $message[] = '  - '.$transaction->getNoEffectDescription();
    }

    parent::__construct(implode("\n", $message));
  }

  public function getTransactions() {
    return $this->transactions;
  }

  public function hasAnyEffect() {
    return $this->anyEffect;
  }

  public function hasComment() {
    return $this->hasComment;
  }

}
