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
          AND reviewerStatus IN (%Ls)',
      $table_name,
      $object->getPHID(),
      $value);

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
          AND reviewerPHID IN (%Ls)',
      $table_name,
      $this->getActingAsPHID(),
      $object->getPHID(),
      $value);
  }

  public function shouldHide() {
    // This is an internal transaction, so don't show it in feeds or
    // transaction logs.
    return true;
  }

  private function getVoidableStatuses() {
    return array(
      DifferentialReviewerStatus::STATUS_ACCEPTED,
      DifferentialReviewerStatus::STATUS_REJECTED,
    );
  }

}
