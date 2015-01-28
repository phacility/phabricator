<?php

abstract class ConpherenceController extends PhabricatorController {

  private $conpherences;

  public function buildApplicationMenu() {
    $nav = new PHUIListView();

    $nav->newLink(
      pht('New Message'),
      $this->getApplicationURI('new/'));

    $nav->addMenuItem(
      id(new PHUIListItemView())
      ->setName(pht('Add Participants'))
      ->setType(PHUIListItemView::TYPE_LINK)
      ->setHref('#')
      ->addSigil('conpherence-widget-adder')
      ->setMetadata(array('widget' => 'widgets-people')));

    $nav->addMenuItem(
      id(new PHUIListItemView())
      ->setName(pht('New Calendar Item'))
      ->setType(PHUIListItemView::TYPE_LINK)
      ->setHref('/calendar/event/create/')
      ->addSigil('conpherence-widget-adder')
      ->setMetadata(array('widget' => 'widgets-calendar')));

    return $nav;
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();
    $crumbs->setBorder(true);

    $crumbs
      ->addAction(
        id(new PHUIListItemView())
        ->setName(pht('New Message'))
        ->setHref($this->getApplicationURI('new/'))
        ->setIcon('fa-plus-square')
        ->setWorkflow(true))
      ->addAction(
        id(new PHUIListItemView())
        ->setName(pht('Thread'))
        ->setHref('#')
        ->setIcon('fa-bars')
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
      $title = pht('[No Title]');
    }
    $crumbs->addCrumb(
      id(new PHUICrumbView())
      ->setName($title)
      ->setHref($this->getApplicationURI('update/'.$conpherence->getID().'/'))
      ->setWorkflow(true));

    return hsprintf(
      '%s',
      array(
        phutil_tag(
          'div',
          array(
            'class' => 'header-loading-mask',
          ),
          ''),
        $crumbs,
      ));
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
    // we're going to insert a dummy date marker transaction for breaks
    // between days. some setup required!
    $previous_transaction = null;
    $date_marker_transaction = id(new ConpherenceTransaction())
      ->setTransactionType(ConpherenceTransactionType::TYPE_DATE_MARKER)
      ->makeEphemeral();
    $date_marker_transaction_view = id(new ConpherenceTransactionView())
      ->setUser($user)
      ->setConpherenceTransaction($date_marker_transaction)
      ->setHandles($handles)
      ->setMarkupEngine($engine);
    foreach ($transactions as $transaction) {
      if ($previous_transaction) {
        $previous_day = phabricator_format_local_time(
          $previous_transaction->getDateCreated(),
          $user,
          'Ymd');
        $current_day = phabricator_format_local_time(
          $transaction->getDateCreated(),
          $user,
          'Ymd');
        // date marker transaction time!
        if ($previous_day != $current_day) {
          $date_marker_transaction->setDateCreated(
            $transaction->getDateCreated());
          $rendered_transactions[] = $date_marker_transaction_view->render();
        }
      }
      $rendered_transactions[] = id(new ConpherenceTransactionView())
        ->setUser($user)
        ->setConpherenceTransaction($transaction)
        ->setHandles($handles)
        ->setMarkupEngine($engine)
        ->render();
      $previous_transaction = $transaction;
    }
    $latest_transaction_id = $transaction->getID();

    return array(
      'transactions' => $rendered_transactions,
      'latest_transaction_id' => $latest_transaction_id,
      'oldest_transaction_id' => $oldest_transaction_id,
    );
  }

}
