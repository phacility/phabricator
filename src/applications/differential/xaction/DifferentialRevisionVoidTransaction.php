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
    $reviewers = id(new DifferentialReviewer())->loadAllWhere(
      'revisionPHID = %s
        AND voidedPHID IS NULL
        AND reviewerStatus IN (%Ls)',
      $object->getPHID(),
      $value);

    $must_downgrade = $this->getMetadataValue('void.force', array());
    $must_downgrade = array_fuse($must_downgrade);

    $default = PhabricatorEnv::getEnvConfig('differential.sticky-accept');
    foreach ($reviewers as $key => $reviewer) {
      $status = $reviewer->getReviewerStatus();

      // If this void is forced, always downgrade. For example, this happens
      // when an author chooses "Request Review": existing reviews are always
      // voided, even if they're sticky.
      if (isset($must_downgrade[$status])) {
        continue;
      }

      // Otherwise, if this is a sticky accept, don't void it. Accepts may be
      // explicitly sticky or unsticky, or they'll use the default value if
      // no value is specified.
      $is_sticky = $reviewer->getOption('sticky');
      $is_sticky = coalesce($is_sticky, $default);

      if ($status === DifferentialReviewerStatus::STATUS_ACCEPTED) {
        if ($is_sticky) {
          unset($reviewers[$key]);
          continue;
        }
      }
    }


    return mpull($reviewers, 'getReviewerPHID');
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
