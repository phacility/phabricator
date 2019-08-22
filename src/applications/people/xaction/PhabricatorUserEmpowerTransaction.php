<?php

final class PhabricatorUserEmpowerTransaction
  extends PhabricatorUserTransactionType {

  const TRANSACTIONTYPE = 'user.admin';

  public function generateOldValue($object) {
    return (bool)$object->getIsAdmin();
  }

  public function generateNewValue($object, $value) {
    return (bool)$value;
  }

  public function applyInternalEffects($object, $value) {
    $object->setIsAdmin((int)$value);
  }

  public function validateTransactions($object, array $xactions) {
    $user = $object;
    $actor = $this->getActor();

    $errors = array();
    foreach ($xactions as $xaction) {
      $old = $xaction->getOldValue();
      $new = $xaction->getNewValue();

      if ($old === $new) {
        continue;
      }

      if ($user->getPHID() === $actor->getPHID()) {
        $errors[] = $this->newInvalidError(
          pht('After a time, your efforts fail. You can not adjust your own '.
              'status as an administrator.'), $xaction);
      }

      $is_admin = $actor->getIsAdmin();
      $is_omnipotent = $actor->isOmnipotent();

      if (!$is_admin && !$is_omnipotent) {
        $errors[] = $this->newInvalidError(
          pht('You must be an administrator to create administrators.'),
          $xaction);
      }
    }

    return $errors;
  }

  public function getTitle() {
    $new = $this->getNewValue();
    if ($new) {
      return pht(
        '%s empowered this user as an administrator.',
        $this->renderAuthor());
    } else {
      return pht(
        '%s defrocked this user.',
        $this->renderAuthor());
    }
  }

  public function getTitleForFeed() {
    $new = $this->getNewValue();
    if ($new) {
      return pht(
        '%s empowered %s as an administrator.',
        $this->renderAuthor(),
        $this->renderObject());
    } else {
      return pht(
        '%s defrocked %s.',
        $this->renderAuthor(),
        $this->renderObject());
    }
  }

  public function getRequiredCapabilities(
    $object,
    PhabricatorApplicationTransaction $xaction) {

    // Unlike normal user edits, admin promotions require admin
    // permissions, which is enforced by validateTransactions().

    return null;
  }

  public function shouldTryMFA(
    $object,
    PhabricatorApplicationTransaction $xaction) {
    return true;
  }

}
