<?php

final class ConpherenceTransactionRenderer {

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
      ->setConpherenceThread($conpherence)
      ->setHandles($handles)
      ->setMarkupEngine($engine);

    $transaction_view_template = id(new ConpherenceTransactionView())
      ->setUser($user)
      ->setConpherenceThread($conpherence)
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
      $transaction_view = id(clone $transaction_view_template)
        ->setConpherenceTransaction($transaction);
      if ($full_display) {
        $transaction_view
          ->setAnchor(
            $transaction->getID(),
            phabricator_time($transaction->getDateCreated(), $user));
        $transaction_view->setContentSource($transaction->getContentSource());
        $transaction_view->setShowImages(true);
      } else {
        $transaction_view
          ->setEpoch(
            $transaction->getDateCreated(),
            '/'.$conpherence->getMonogram().'#'.$transaction->getID())
            ->setTimeOnly(true);
        $transaction_view->setShowImages(false);
      }

      $rendered_transactions[] = $transaction_view->render();
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
