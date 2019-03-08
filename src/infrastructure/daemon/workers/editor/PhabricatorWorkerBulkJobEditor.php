<?php

final class PhabricatorWorkerBulkJobEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorDaemonsApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Bulk Jobs');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorWorkerBulkJobTransaction::TYPE_STATUS;
    $types[] = PhabricatorTransactions::TYPE_EDGE;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorWorkerBulkJobTransaction::TYPE_STATUS:
        return $object->getStatus();
    }
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorWorkerBulkJobTransaction::TYPE_STATUS:
        return $xaction->getNewValue();
    }
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $type = $xaction->getTransactionType();
    $new = $xaction->getNewValue();

    switch ($type) {
      case PhabricatorWorkerBulkJobTransaction::TYPE_STATUS:
        $object->setStatus($xaction->getNewValue());
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $type = $xaction->getTransactionType();
    $new = $xaction->getNewValue();

    switch ($type) {
      case PhabricatorWorkerBulkJobTransaction::TYPE_STATUS:
        switch ($new) {
          case PhabricatorWorkerBulkJob::STATUS_WAITING:
            PhabricatorWorker::scheduleTask(
              'PhabricatorWorkerBulkJobCreateWorker',
              array(
                'jobID' => $object->getID(),
              ),
              array(
                'priority' => PhabricatorWorker::PRIORITY_BULK,
              ));
            break;
        }
        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
  }



}
