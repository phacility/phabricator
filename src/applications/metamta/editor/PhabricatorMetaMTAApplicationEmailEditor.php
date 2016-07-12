<?php

final class PhabricatorMetaMTAApplicationEmailEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return pht('PhabricatorMetaMTAApplication');
  }

  public function getEditorObjectsDescription() {
    return pht('Application Emails');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorMetaMTAApplicationEmailTransaction::TYPE_ADDRESS;
    $types[] = PhabricatorMetaMTAApplicationEmailTransaction::TYPE_CONFIG;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorMetaMTAApplicationEmailTransaction::TYPE_ADDRESS:
        return $object->getAddress();
      case PhabricatorMetaMTAApplicationEmailTransaction::TYPE_CONFIG:
        $key = $xaction->getMetadataValue(
          PhabricatorMetaMTAApplicationEmailTransaction::KEY_CONFIG);
        return $object->getConfigValue($key);
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorMetaMTAApplicationEmailTransaction::TYPE_ADDRESS:
      case PhabricatorMetaMTAApplicationEmailTransaction::TYPE_CONFIG:
        return $xaction->getNewValue();
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $new = $xaction->getNewValue();

    switch ($xaction->getTransactionType()) {
      case PhabricatorMetaMTAApplicationEmailTransaction::TYPE_ADDRESS:
        $object->setAddress($new);
        return;
      case PhabricatorMetaMTAApplicationEmailTransaction::TYPE_CONFIG:
        $key = $xaction->getMetadataValue(
          PhabricatorMetaMTAApplicationEmailTransaction::KEY_CONFIG);
        $object->setConfigValue($key, $new);
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorMetaMTAApplicationEmailTransaction::TYPE_ADDRESS:
      case PhabricatorMetaMTAApplicationEmailTransaction::TYPE_CONFIG:
        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
  }

  protected function validateTransaction(
    PhabricatorLiskDAO $object,
    $type,
    array $xactions) {

    $errors = parent::validateTransaction($object, $type, $xactions);

    switch ($type) {
      case PhabricatorMetaMTAApplicationEmailTransaction::TYPE_ADDRESS:
        foreach ($xactions as $xaction) {
          $email = $xaction->getNewValue();
          if (!strlen($email)) {
            // We'll deal with this below.
            continue;
          }

          if (!PhabricatorUserEmail::isValidAddress($email)) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              pht('Email address is not formatted properly.'));
          }
        }

        $missing = $this->validateIsEmptyTextField(
          $object->getAddress(),
          $xactions);

        if ($missing) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            pht('You must provide an email address.'),
            nonempty(last($xactions), null));

          $error->setIsMissingFieldError(true);
          $errors[] = $error;
        }
        break;
    }

    return $errors;
  }

  protected function didCatchDuplicateKeyException(
    PhabricatorLiskDAO $object,
    array $xactions,
    Exception $ex) {

    $errors = array();
    $errors[] = new PhabricatorApplicationTransactionValidationError(
      PhabricatorMetaMTAApplicationEmailTransaction::TYPE_ADDRESS,
      pht('Duplicate'),
      pht('This email address is already in use.'),
      null);

    throw new PhabricatorApplicationTransactionValidationException($errors);
  }


}
