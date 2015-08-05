<?php

final class ConpherenceQueryTransactionConduitAPIMethod
  extends ConpherenceConduitAPIMethod {

  public function getAPIMethodName() {
    return 'conpherence.querytransaction';
  }

  public function getMethodDescription() {
    return pht(
      'Query for transactions for the logged in user within a specific '.
      'Conpherence room. You can specify the room by ID or PHID. '.
      'Otherwise, specify limit and offset to query the most recent '.
      'transactions within the Conpherence room for the logged in user.');
  }

  protected function defineParamTypes() {
    return array(
      'roomID' => 'optional int',
      'roomPHID' => 'optional phid',
      'limit' => 'optional int',
      'offset' => 'optional int',
    );
  }

  protected function defineReturnType() {
    return 'nonempty dict';
  }

  protected function defineErrorTypes() {
    return array(
      'ERR_USAGE_NO_ROOM_ID' => pht(
        'You must specify a room id or room PHID to query transactions '.
        'from.'),
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $user = $request->getUser();
    $room_id = $request->getValue('roomID');
    $room_phid = $request->getValue('roomPHID');
    $limit = $request->getValue('limit');
    $offset = $request->getValue('offset');

    $query = id(new ConpherenceThreadQuery())
      ->setViewer($user);

    if ($room_id) {
      $query->withIDs(array($room_id));
    } else if ($room_phid) {
      $query->withPHIDs(array($room_phid));
    } else {
      throw new ConduitException('ERR_USAGE_NO_ROOM_ID');
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
        'roomID' => $conpherence->getID(),
        'roomPHID' => $conpherence->getPHID(),
      );
    }
    return $data;
  }

}
