<?php

/**
 * @group conpherence
 */
abstract class ConpherenceController extends PhabricatorController {
  private $conpherences;

  public function buildApplicationMenu() {
    $nav = new PhabricatorMenuView();

    $nav->newLink(
      pht('New Message'),
      $this->getApplicationURI('new/'));

    return $nav;
  }

  public function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs
      ->addAction(
        id(new PhabricatorMenuItemView())
        ->setName(pht('New Message'))
        ->setHref($this->getApplicationURI('new/'))
        ->setIcon('create')
        ->setWorkflow(true))
      ->addAction(
        id(new PhabricatorMenuItemView())
        ->setName(pht('Thread'))
        ->setHref('#')
        ->setIcon('action-menu')
        ->setStyle('display: none;')
        ->addClass('device-widgets-selector')
        ->addSigil('device-widgets-selector'));
    return $crumbs;
  }

  protected function buildHeaderPaneContent(ConpherenceThread $conpherence) {
    $crumbs = $this->buildApplicationCrumbs();
    if ($conpherence->getTitle()) {
      $title = $conpherence->getTitle();
    } else {
      $title = pht('Conpherence');
    }
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
      ->setName($title)
      ->setHref($this->getApplicationURI('update/'.$conpherence->getID().'/'))
      ->setWorkflow(true));

    return $crumbs;
  }

  protected function renderConpherenceTransactions(
    ConpherenceThread $conpherence) {

    $user = $this->getRequest()->getUser();
    $transactions = $conpherence->getTransactions();
    $oldest_transaction_id = 0;
    $too_many = ConpherenceThreadQuery::TRANSACTION_LIMIT + 1;
    if (count($transactions) == $too_many) {
      $last_transaction = end($transactions);
      unset($transactions[$last_transaction->getID()]);
      $oldest_transaction = end($transactions);
      $oldest_transaction_id = $oldest_transaction->getID();
    }
    $transactions = array_reverse($transactions);
    $handles = $conpherence->getHandles();
    $rendered_transactions = array();
    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($user);
    foreach ($transactions as $key => $transaction) {
      if ($transaction->shouldHide()) {
        unset($transactions[$key]);
        continue;
      }
      if ($transaction->getComment()) {
        $engine->addObject(
          $transaction->getComment(),
          PhabricatorApplicationTransactionComment::MARKUP_FIELD_COMMENT);
      }
    }
    $engine->process();
    foreach ($transactions as $transaction) {
      $rendered_transactions[] = id(new ConpherenceTransactionView())
        ->setUser($user)
        ->setConpherenceTransaction($transaction)
        ->setHandles($handles)
        ->setMarkupEngine($engine)
        ->render();
    }
    $latest_transaction_id = $transaction->getID();

    return array(
      'transactions' => $rendered_transactions,
      'latest_transaction_id' => $latest_transaction_id,
      'oldest_transaction_id' => $oldest_transaction_id
    );

  }
}
