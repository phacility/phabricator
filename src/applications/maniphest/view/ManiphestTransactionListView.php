<?php

/**
 * @group maniphest
 */
final class ManiphestTransactionListView extends ManiphestView {

  private $transactions;
  private $handles;
  private $markupEngine;
  private $preview;
  private $auxiliaryFields;

  public function setTransactions(array $transactions) {
    assert_instances_of($transactions, 'ManiphestTransaction');
    $this->transactions = $transactions;
    return $this;
  }

  public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }

  public function setMarkupEngine(PhabricatorMarkupEngine $engine) {
    $this->markupEngine = $engine;
    return $this;
  }

  public function setPreview($preview) {
    $this->preview = $preview;
    return $this;
  }

  public function setAuxiliaryFields(array $fields) {
    assert_instances_of($fields, 'ManiphestAuxiliaryFieldSpecification');
    $this->auxiliaryFields = $fields;
    return $this;
  }

  private function getAuxiliaryFields() {
    if (empty($this->auxiliaryFields)) {
      return array();
    }
    return $this->auxiliaryFields;
  }

  public function render() {

    $views = array();


    $last = null;
    $group = array();
    $groups = array();
    $has_description_transaction = false;
    foreach ($this->transactions as $transaction) {
      if ($transaction->getTransactionType() ==
          ManiphestTransactionType::TYPE_DESCRIPTION) {
        $has_description_transaction = true;
      }
      if ($last === null) {
        $last = $transaction;
        $group[] = $transaction;
        continue;
      } else if ($last->canGroupWith($transaction)) {
        $group[] = $transaction;
        if ($transaction->hasComments()) {
          $last = $transaction;
        }
      } else {
        $groups[] = $group;
        $last = $transaction;
        $group = array($transaction);
      }
    }
    if ($group) {
      $groups[] = $group;
    }

    if ($has_description_transaction) {
      require_celerity_resource('differential-changeset-view-css');
      require_celerity_resource('syntax-highlighting-css');
      $whitespace_mode = DifferentialChangesetParser::WHITESPACE_SHOW_ALL;
      Javelin::initBehavior('differential-show-more', array(
        'uri'         => '/maniphest/task/descriptionchange/',
        'whitespace'  => $whitespace_mode,
      ));
    }

    $sequence = 1;
    foreach ($groups as $group) {
      $view = new ManiphestTransactionDetailView();
      $view->setUser($this->user);
      $view->setAuxiliaryFields($this->getAuxiliaryFields());
      $view->setTransactionGroup($group);
      $view->setHandles($this->handles);
      $view->setMarkupEngine($this->markupEngine);
      $view->setPreview($this->preview);
      $view->setCommentNumber($sequence++);
      $views[] = $view->render();
    }

    return phutil_tag(
      'div',
      array('class' => 'maniphest-transaction-list-view'),
      $views);
  }

}
