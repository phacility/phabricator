<?php

final class PhabricatorUserDisableTransaction
  extends PhabricatorUserTransactionType {

  const TRANSACTIONTYPE = 'user.disable';

  public function generateOldValue($object) {
    return (bool)$object->getIsDisabled();
  }

  public function generateNewValue($object, $value) {
    return (bool)$value;
  }

  public function applyInternalEffects($object, $value) {
    $object->setIsDisabled((int)$value);
  }

  public function getTitle() {
    $new = $this->getNewValue();
    if ($new) {
      return pht(
        '%s disabled this user.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s enabled this user.',
        $this->renderAuthor());
    }
  }

  public function shouldHideForFeed() {
    // Don't publish feed stories about disabling users, since this can be
    // a sensitive action.
    return true;
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    foreach ($xactions as $xaction) {
      $is_disabled = (bool)$object->getIsDisabled();

      if ((bool)$xaction->getNewValue() === $is_disabled) {
        continue;
      }

      // You must have the "Can Disable Users" permission to disable a user.
      $this->requireApplicationCapability(
        PeopleDisableUsersCapability::CAPABILITY);

      if ($this->getActingAsPHID() === $object->getPHID()) {
        $errors[] = $this->newInvalidError(
          pht('You can not enable or disable your own account.'));
      }
    }

    return $errors;
  }

  public function getRequiredCapabilities(
    $object,
    PhabricatorApplicationTransaction $xaction) {

    // You do not need to be able to edit users to disable them. Instead, this
    // requirement is replaced with a requirement that you have the "Can
    // Disable Users" permission.

    return null;
  }
}
