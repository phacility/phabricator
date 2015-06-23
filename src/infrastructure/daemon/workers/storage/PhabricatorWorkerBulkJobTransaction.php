<?php

final class PhabricatorWorkerBulkJobTransaction
  extends PhabricatorApplicationTransaction {

  const TYPE_STATUS = 'bulkjob.status';

  public function getApplicationName() {
    return 'worker';
  }

  public function getApplicationTransactionType() {
    return PhabricatorWorkerBulkJobPHIDType::TYPECONST;
  }

  public function getTitle() {
    $author_phid = $this->getAuthorPHID();

    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $type = $this->getTransactionType();
    switch ($type) {
      case self::TYPE_STATUS:
        if ($old === null) {
          return pht(
            '%s created this bulk job.',
            $this->renderHandleLink($author_phid));
        } else {
          switch ($new) {
            case PhabricatorWorkerBulkJob::STATUS_WAITING:
              return pht(
                '%s confirmed this job.',
                $this->renderHandleLink($author_phid));
            case PhabricatorWorkerBulkJob::STATUS_RUNNING:
              return pht(
                '%s marked this job as running.',
                $this->renderHandleLink($author_phid));
            case PhabricatorWorkerBulkJob::STATUS_COMPLETE:
              return pht(
                '%s marked this job complete.',
                $this->renderHandleLink($author_phid));
          }
        }
        break;
    }

    return parent::getTitle();
  }

}
