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
          ->setIcon('create'))
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName(pht('Conpherence')));

    return $crumbs;
  }

  protected function buildHeaderPaneContent(ConpherenceThread $conpherence) {
    $user = $this->getRequest()->getUser();
    $display_data = $conpherence->getDisplayData(
      $user,
      ConpherenceImageData::SIZE_HEAD);
    $edit_href = $this->getApplicationURI('update/'.$conpherence->getID().'/');
    $class_mod = $display_data['image_class'];

    return array(
      phutil_tag(
        'div',
        array(
          'class' => 'upload-photo'
        ),
        pht('Drop photo here to change this Conpherence photo.')),
      javelin_tag(
        'a',
        array(
          'class' => 'edit',
          'href' => $edit_href,
          'sigil' => 'conpherence-edit-metadata',
          'meta' => array(
            'action' => 'metadata'
          )
        ),
        ''),
      phutil_tag(
        'div',
        array(
          'class' => $class_mod.'header-image',
          'style' => 'background-image: url('.$display_data['image'].');'
        ),
        ''),
      phutil_tag(
        'div',
        array(
          'class' => $class_mod.'title',
        ),
        $display_data['title']),
      phutil_tag(
        'div',
        array(
          'class' => $class_mod.'subtitle',
        ),
        $display_data['subtitle']),
    );
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
