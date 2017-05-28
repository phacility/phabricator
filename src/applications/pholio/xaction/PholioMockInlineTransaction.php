<?php

final class PholioMockInlineTransaction
  extends PholioMockTransactionType {

  const TRANSACTIONTYPE = 'inline';

  public function generateOldValue($object) {
    return null;
  }

  public function getTitle() {
    return pht(
      '%s added inline comment(s).',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return pht(
      '%s added an inline comment to %s.',
      $this->renderAuthor(),
      $this->renderObject());
  }

  public function getIcon() {
    return 'fa-comment';
  }

  public function getTransactionHasEffect($object, $old, $new) {
    return true;
  }

}
