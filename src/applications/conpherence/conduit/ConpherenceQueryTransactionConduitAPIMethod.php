<?php

final class ConpherenceQueryTransactionConduitAPIMethod
  extends ConpherenceConduitAPIMethod {

  public function getAPIMethodName() {
    return 'conpherence.querytransaction';
  }

  public function getMethodDescription() {
    return pht(
      'Query for transactions for the logged in user within a specific '.
      'conpherence thread. You can specify the thread by id or phid. '.
      'Otherwise, specify limit and offset to query the most recent '.
      'transactions within the conpherence for the logged in user.');
  }

  protected function defineParamTypes() {
    return array(
      'threadID' => 'optional int',
      'threadPHID' => 'optional phid',
      'limit' => 'optional int',
      'offset' => 'optional int',
    );
  }

  protected function defineReturnType() {
    return 'nonempty dict';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR_USAGE_NO_THREAD_ID' => pht(
        'You must specify a thread id or thread phid to query transactions '.
        'from.'),
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $user = $request->getUser();
    $thread_id = $request->getValue('threadID');
    $thread_phid = $request->getValue('threadPHID');
    $limit = $request->getValue('limit');
    $offset = $request->getValue('offset');

    $query = id(new ConpherenceThreadQuery())
      ->setViewer($user);

    if ($thread_id) {
      $query->withIDs(array($thread_id));
    } else if ($thread_phid) {
      $query->withPHIDs(array($thread_phid));
    } else {
      throw new ConduitException('ERR_USAGE_NO_THREAD_ID');
    }

    $conpherence = $query->executeOne();

    $query = id(new ConpherenceTransactionQuery())
      ->setViewer($user)
      ->withObjectPHIDs(array($conpherence->getPHID()))
      ->setLimit($limit)
      ->setOffset($offset);

    $transactions = $query->execute();

    $data = array();
    foreach ($transactions as $transaction) {
      $comment = null;
      $comment_obj = $transaction->getComment();
      if ($comment_obj) {
        $comment = $comment_obj->getContent();
      }
      $title = null;
      $title_obj = $transaction->getTitle();
      if ($title_obj) {
        $title = $title_obj->getHTMLContent();
      }
      $id = $transaction->getID();
      $data[$id] = array(
        'transactionID' => $id,
        'transactionType' => $transaction->getTransactionType(),
        'transactionTitle' => $title,
        'transactionComment' => $comment,
        'transactionOldValue' => $transaction->getOldValue(),
        'transactionNewValue' => $transaction->getNewValue(),
        'transactionMetadata' => $transaction->getMetadata(),
        'authorPHID' => $transaction->getAuthorPHID(),
        'dateCreated' => $transaction->getDateCreated(),
        'conpherenceID' => $conpherence->getID(),
        'conpherencePHID' => $conpherence->getPHID(),
      );
    }
    return $data;
  }

}
