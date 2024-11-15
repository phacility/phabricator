<?php

final class ManiphestTaskBlockedTransaction
  extends ManiphestTaskTransactionType {

  const TRANSACTIONTYPE = 'blocked';

  public function generateOldValue($object) {
    return null;
  }

  public function shouldHide() {
    return true;
  }

  public function applyExternalEffects($object, $value) {
    $parent_phid = $value;
    $parent_type = ManiphestTaskBlockerEdgeType::EDGECONST;
    $task_phid = $object->getPHID();

    id(new PhabricatorEdgeEditor())
      ->addEdge($parent_phid, $parent_type, $task_phid)
      ->save();
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $with_effect = array();
    foreach ($xactions as $xaction) {
      $task_phid = $xaction->getNewValue();
      if (!$task_phid) {
        continue;
      }

      $with_effect[] = $xaction;

      $task = id(new ManiphestTaskQuery())
        ->setViewer($this->getActor())
        ->withPHIDs(array($task_phid))
        ->executeOne();
      if (!$task) {
        $errors[] = $this->newInvalidError(
          pht(
            'Blocked task identifier "%s" does not identify a visible '.
            'task.',
            $task_phid));
      }
    }

    if ($with_effect && !$this->isNewObject()) {
      $errors[] = $this->newInvalidError(
        pht(
          'You can only select a blocked task when creating a '.
          'transaction for the first time.'));
    }

    return $errors;
  }
}
