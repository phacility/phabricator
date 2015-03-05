<?php

final class ConpherenceColumnViewController extends
  ConpherenceController {

  public function handleRequest(AphrontRequest $request) {
    $user = $request->getUser();

    $conpherence = null;
    if ($request->getInt('id')) {
      $conpherence = id(new ConpherenceThreadQuery())
        ->setViewer($user)
        ->withIDs(array($request->getInt('id')))
        ->needTransactions(true)
        ->setTransactionLimit(ConpherenceThreadQuery::TRANSACTION_LIMIT)
        ->executeOne();
    } else {
      // TODO - should be pulling more data than this to build the
      // icon bar, etc, kind of always
      $latest_participant = id(new ConpherenceParticipantQuery())
        ->withParticipantPHIDs(array($user->getPHID()))
        ->setLimit(1)
        ->execute();
      $participant = head($latest_participant);
      $conpherence = id(new ConpherenceThreadQuery())
        ->setViewer($user)
        ->withPHIDs(array($participant->getConpherencePHID()))
        ->needTransactions(true)
        ->setTransactionLimit(ConpherenceThreadQuery::TRANSACTION_LIMIT)
        ->executeOne();
    }

    if (!$conpherence) {
      return new Aphront404Response();
    }
    $this->setConpherence($conpherence);

    $participant = $conpherence->getParticipant($user->getPHID());
    $transactions = $conpherence->getTransactions();
    $latest_transaction = head($transactions);
    $write_guard = AphrontWriteGuard::beginScopedUnguardedWrites();
    $participant->markUpToDate($conpherence, $latest_transaction);
    unset($write_guard);

    $durable_column = id(new ConpherenceDurableColumnView())
      ->setUser($user)
      ->setSelectedConpherence($conpherence)
      ->setStyle(null);

    $response = array(
      'content' => hsprintf('%s', $durable_column),
      'threadID' => $conpherence->getID(),
      'threadPHID' => $conpherence->getPHID(),
      'latestTransactionID' => $latest_transaction->getID(),);

    return id(new AphrontAjaxResponse())->setContent($response);
  }

}
