<?php

final class ConpherenceTransactionRenderer {

  public static function renderTransactions(
    PhabricatorUser $user,
    ConpherenceThread $conpherence,
    $full_display = true,
    $marker_type = 'older') {

    $transactions = $conpherence->getTransactions();

    $oldest_transaction_id = 0;
    $newest_transaction_id = 0;
    $too_many = ConpherenceThreadQuery::TRANSACTION_LIMIT + 1;
    if (count($transactions) == $too_many) {
      if ($marker_type == 'olderandnewer') {
        $last_transaction = end($transactions);
        $first_transaction = reset($transactions);
        unset($transactions[$last_transaction->getID()]);
        unset($transactions[$first_transaction->getID()]);
        $oldest_transaction_id = $last_transaction->getID();
        $newest_transaction_id = $first_transaction->getID();
      } else if ($marker_type == 'newer') {
        $first_transaction = reset($transactions);
        unset($transactions[$first_transaction->getID()]);
        $newest_transaction_id = $first_transaction->getID();
      } else if ($marker_type == 'older') {
        $last_transaction = end($transactions);
        unset($transactions[$last_transaction->getID()]);
        $oldest_transaction = end($transactions);
        $oldest_transaction_id = $oldest_transaction->getID();
      }
    // we need **at least** the newer marker in this mode even if
    // we didn't get a full set of transactions
    } else if ($marker_type == 'olderandnewer') {
      $first_transaction = reset($transactions);
      unset($transactions[$first_transaction->getID()]);
      $newest_transaction_id = $first_transaction->getID();
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
      ->setMarkupEngine($engine)
      ->setFullDisplay($full_display);

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
          $date_marker_transaction->setID($previous_transaction->getID());
          $rendered_transactions[] = $date_marker_transaction_view->render();
        }
      }
      $transaction_view = id(clone $transaction_view_template)
        ->setConpherenceTransaction($transaction);

      $rendered_transactions[] = $transaction_view->render();
      $previous_transaction = $transaction;
    }
    $latest_transaction_id = $transaction->getID();

    return array(
      'transactions' => $rendered_transactions,
      'latest_transaction' => $transaction,
      'latest_transaction_id' => $latest_transaction_id,
      'oldest_transaction_id' => $oldest_transaction_id,
      'newest_transaction_id' => $newest_transaction_id,
    );
  }

  public static function renderMessagePaneContent(
    array $transactions,
    $oldest_transaction_id,
    $newest_transaction_id) {

    $oldscrollbutton = '';
    if ($oldest_transaction_id) {
      $oldscrollbutton = javelin_tag(
        'a',
        array(
          'href' => '#',
          'mustcapture' => true,
          'sigil' => 'show-older-messages',
          'class' => 'conpherence-show-more-messages',
          'meta' => array(
            'oldest_transaction_id' => $oldest_transaction_id,
          ),
        ),
        pht('Show Older Messages'));
      $oldscrollbutton = javelin_tag(
        'div',
        array(
          'sigil' => 'conpherence-transaction-view',
          'meta' => array(
            'id' => $oldest_transaction_id - 0.5,
          ),
        ),
        $oldscrollbutton);
    }

    $newscrollbutton = '';
    if ($newest_transaction_id) {
      $newscrollbutton = javelin_tag(
        'a',
        array(
          'href' => '#',
          'mustcapture' => true,
          'sigil' => 'show-newer-messages',
          'class' => 'conpherence-show-more-messages',
          'meta' => array(
            'newest_transaction_id' => $newest_transaction_id,
          ),
        ),
        pht('Show Newer Messages'));
      $newscrollbutton = javelin_tag(
        'div',
        array(
          'sigil' => 'conpherence-transaction-view',
          'meta' => array(
            'id' => $newest_transaction_id + 0.5,
          ),
        ),
        $newscrollbutton);
    }

    return hsprintf(
      '%s%s%s',
      $oldscrollbutton,
      $transactions,
      $newscrollbutton);
  }

}
