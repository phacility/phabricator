<?php

final class ConpherenceTransactionView extends AphrontView {

  private $conpherenceTransaction;
  private $handles;
  private $markupEngine;
  private $showImages = true;
  private $showContentSource = true;

  public function setMarkupEngine(PhabricatorMarkupEngine $markup_engine) {
    $this->markupEngine = $markup_engine;
    return $this;
  }

  public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }

  public function getHandles() {
    return $this->handles;
  }

  public function setConpherenceTransaction(ConpherenceTransaction $tx) {
    $this->conpherenceTransaction = $tx;
    return $this;
  }

  private function getConpherenceTransaction() {
    return $this->conpherenceTransaction;
  }

  public function setShowImages($bool) {
    $this->showImages = $bool;
    return $this;
  }

  private function getShowImages() {
    return $this->showImages;
  }

  public function setShowContentSource($bool) {
    $this->showContentSource = $bool;
    return $this;
  }

  private function getShowContentSource() {
    return $this->showContentSource;
  }

  public function render() {
    $user = $this->getUser();
    $transaction = $this->getConpherenceTransaction();
    switch ($transaction->getTransactionType()) {
      case ConpherenceTransactionType::TYPE_DATE_MARKER:
        return phutil_tag(
          'div',
          array(
            'class' => 'date-marker',
          ),
          array(
            phutil_tag(
              'span',
              array(
                'class' => 'date',
              ),
              phabricator_format_local_time(
                $transaction->getDateCreated(),
                $user,
              'M jS, Y')),
          ));
        break;
    }

    $handles = $this->getHandles();
    $transaction->setHandles($handles);
    $author = $handles[$transaction->getAuthorPHID()];
    $transaction_view = id(new PhabricatorTransactionView())
      ->setUser($user)
      ->setEpoch($transaction->getDateCreated())
      ->setTimeOnly(true);
    if ($this->getShowContentSource()) {
      $transaction_view->setContentSource($transaction->getContentSource());
    }

    $content = null;
    $content_class = null;
    $content = null;
    switch ($transaction->getTransactionType()) {
      case ConpherenceTransactionType::TYPE_FILES:
        $content = $transaction->getTitle();
        break;
      case ConpherenceTransactionType::TYPE_TITLE:
      case ConpherenceTransactionType::TYPE_PARTICIPANTS:
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
      case PhabricatorTransactions::TYPE_JOIN_POLICY:
        $content = $transaction->getTitle();
        $transaction_view->addClass('conpherence-edited');
        break;
      case PhabricatorTransactions::TYPE_COMMENT:
        $transaction_view->addClass('conpherence-comment');
        $comment = $transaction->getComment();
        $content = $this->markupEngine->getOutput(
          $comment,
          PhabricatorApplicationTransactionComment::MARKUP_FIELD_COMMENT);
        $content_class = 'conpherence-message phabricator-remarkup';
        if ($this->getShowImages()) {
          $transaction_view->setImageURI($author->getImageURI());
        }
        $transaction_view->setActions(array($author->renderLink()));
        break;
    }

    $transaction_view->appendChild(
      phutil_tag(
        'div',
        array(
          'class' => $content_class,
        ),
        $content));

    return $transaction_view->render();
  }

  public static function renderTransactions(
    PhabricatorUser $user,
    ConpherenceThread $conpherence,
    $full_display = true) {

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
      ->setViewer($user)
      ->setContextObject($conpherence);
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
      ->setShowImages($full_display)
      ->setShowContentSource($full_display)
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
        ->setShowImages($full_display)
        ->setShowContentSource($full_display)
        ->render();
      $previous_transaction = $transaction;
    }
    $latest_transaction_id = $transaction->getID();

    return array(
      'transactions' => $rendered_transactions,
      'latest_transaction' => $transaction,
      'latest_transaction_id' => $latest_transaction_id,
      'oldest_transaction_id' => $oldest_transaction_id,
    );
  }

  public static function renderMessagePaneContent(
    array $transactions,
    $oldest_transaction_id) {

    $scrollbutton = '';
    if ($oldest_transaction_id) {
      $scrollbutton = javelin_tag(
        'a',
        array(
          'href' => '#',
          'mustcapture' => true,
          'sigil' => 'show-older-messages',
          'class' => 'conpherence-show-older-messages',
          'meta' => array(
            'oldest_transaction_id' => $oldest_transaction_id,
          ),
        ),
        pht('Show Older Messages'));
    }

    return hsprintf('%s%s', $scrollbutton, $transactions);
  }

}
