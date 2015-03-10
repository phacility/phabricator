<?php

final class ConpherenceColumnViewController extends
  ConpherenceController {

  public function handleRequest(AphrontRequest $request) {
    $user = $request->getUser();

    $latest_conpherences = array();
    $latest_participant = id(new ConpherenceParticipantQuery())
      ->withParticipantPHIDs(array($user->getPHID()))
      ->setLimit(6)
      ->execute();
    if ($latest_participant) {
      $conpherence_phids = mpull($latest_participant, 'getConpherencePHID');
      $latest_conpherences = id(new ConpherenceThreadQuery())
        ->setViewer($user)
        ->withPHIDs($conpherence_phids)
        ->needParticipantCache(true)
        ->execute();
      $latest_conpherences = mpull($latest_conpherences, null, 'getPHID');
      $latest_conpherences = array_select_keys(
        $latest_conpherences,
        $conpherence_phids);
    }

    $conpherence = null;
    if ($request->getInt('id')) {
      $conpherence = id(new ConpherenceThreadQuery())
        ->setViewer($user)
        ->withIDs(array($request->getInt('id')))
        ->needTransactions(true)
        ->setTransactionLimit(ConpherenceThreadQuery::TRANSACTION_LIMIT)
        ->executeOne();
    } else if ($latest_participant) {
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
      ->setConpherences($latest_conpherences)
      ->setStyle(null);

    $response = array(
      'content' => hsprintf('%s', $durable_column),
      'threadID' => $conpherence->getID(),
      'threadPHID' => $conpherence->getPHID(),
      'latestTransactionID' => $latest_transaction->getID(),);

    return id(new AphrontAjaxResponse())->setContent($response);
  }

}
