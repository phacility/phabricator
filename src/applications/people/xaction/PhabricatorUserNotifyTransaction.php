<?php

final class PhabricatorUserNotifyTransaction
  extends PhabricatorUserTransactionType {

  const TRANSACTIONTYPE = 'notify';

  public function generateOldValue($object) {
    return null;
  }

  public function generateNewValue($object, $value) {
    return $value;
  }

  public function getTitle() {
    return pht(
      '%s sent this user a test notification.',
      $this->renderAuthor());
  }

  public function getTitleForFeed() {
    return $this->getNewValue();
  }

  public function shouldHideForNotifications() {
    return false;
  }

  public function shouldHideForFeed() {
    return true;
  }

  public function shouldHideForMail() {
    return true;
  }

}
