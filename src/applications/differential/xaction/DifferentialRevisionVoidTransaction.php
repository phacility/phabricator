<?php

/**
 * This is an internal transaction type used to void reviews.
 *
 * For example, "Request Review" voids any open accepts, so they no longer
 * act as current accepts.
 */
final class DifferentialRevisionVoidTransaction
  extends DifferentialRevisionTransactionType {

  const TRANSACTIONTYPE = 'differential.revision.void';

  public function generateOldValue($object) {
    return false;
  }

  public function generateNewValue($object, $value) {
    $table = new DifferentialReviewer();
    $table_name = $table->getTableName();
    $conn = $table->establishConnection('w');

    $rows = queryfx_all(
      $conn,
      'SELECT reviewerPHID FROM %T
        WHERE revisionPHID = %s
          AND voidedPHID IS NULL
          AND reviewerStatus = %s',
      $table_name,
      $object->getPHID(),
      DifferentialReviewerStatus::STATUS_ACCEPTED);

    return ipull($rows, 'reviewerPHID');
  }

  public function getTransactionHasEffect($object, $old, $new) {
    return (bool)$new;
  }

  public function applyExternalEffects($object, $value) {
    $table = new DifferentialReviewer();
    $table_name = $table->getTableName();
    $conn = $table->establishConnection('w');

    queryfx(
      $conn,
      'UPDATE %T SET voidedPHID = %s
        WHERE revisionPHID = %s
          AND voidedPHID IS NULL
          AND reviewerStatus = %s',
      $table_name,
      $this->getActingAsPHID(),
      $object->getPHID(),
      DifferentialReviewerStatus::STATUS_ACCEPTED);
  }

  public function shouldHide() {
    // This is an internal transaction, so don't show it in feeds or
    // transaction logs.
    return true;
  }

}
