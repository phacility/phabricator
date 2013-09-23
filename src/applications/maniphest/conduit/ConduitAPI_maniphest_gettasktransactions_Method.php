<?php

/**
 * @group maniphest
 */
final class ConduitAPI_maniphest_gettasktransactions_Method
  extends ConduitAPI_maniphest_Method {

  public function getMethodDescription() {
    return "Retrieve Maniphest Task Transactions.";
  }

  public function defineParamTypes() {
    return array(
      'ids' => 'required list<int>',
    );
  }

  public function defineReturnType() {
    return 'nonempty list<dict<string, wild>>';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {
    $results = array();
    $task_ids = $request->getValue('ids');

    if (!$task_ids) {
      return $results;
    }

    $tasks = id(new ManiphestTaskQuery())
      ->setViewer($request->getUser())
      ->withIDs($task_ids)
      ->execute();

    $transactions = ManiphestLegacyTransactionQuery::loadByTasks(
      $request->getUser(),
      $tasks);

    foreach ($transactions as $transaction) {
      $task_id = $transaction->getTaskID();
      if (!array_key_exists($task_id, $results)) {
        $results[$task_id] = array();
      }
      $results[$task_id][] = array(
        'taskID'  => $task_id,
        'transactionType'  => $transaction->getTransactionType(),
        'oldValue'  => $transaction->getOldValue(),
        'newValue'  => $transaction->getNewValue(),
        'comments'      => $transaction->getComments(),
        'authorPHID'  => $transaction->getAuthorPHID(),
        'dateCreated' => $transaction->getDateCreated(),
      );
    }

    return $results;
  }
}
