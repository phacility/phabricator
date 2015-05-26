<?php

final class PassphraseCredentialTransactionEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorPassphraseApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Passphrase Credentials');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    $types[] = PassphraseCredentialTransaction::TYPE_NAME;
    $types[] = PassphraseCredentialTransaction::TYPE_DESCRIPTION;
    $types[] = PassphraseCredentialTransaction::TYPE_USERNAME;
    $types[] = PassphraseCredentialTransaction::TYPE_SECRET_ID;
    $types[] = PassphraseCredentialTransaction::TYPE_DESTROY;
    $types[] = PassphraseCredentialTransaction::TYPE_LOOKEDATSECRET;
    $types[] = PassphraseCredentialTransaction::TYPE_LOCK;
    $types[] = PassphraseCredentialTransaction::TYPE_CONDUIT;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PassphraseCredentialTransaction::TYPE_NAME:
        if ($this->getIsNewObject()) {
          return null;
        }
        return $object->getName();
      case PassphraseCredentialTransaction::TYPE_DESCRIPTION:
        return $object->getDescription();
      case PassphraseCredentialTransaction::TYPE_USERNAME:
        return $object->getUsername();
      case PassphraseCredentialTransaction::TYPE_SECRET_ID:
        return $object->getSecretID();
      case PassphraseCredentialTransaction::TYPE_DESTROY:
        return (int)$object->getIsDestroyed();
      case PassphraseCredentialTransaction::TYPE_LOCK:
        return (int)$object->getIsLocked();
      case PassphraseCredentialTransaction::TYPE_CONDUIT:
        return (int)$object->getAllowConduit();
      case PassphraseCredentialTransaction::TYPE_LOOKEDATSECRET:
        return null;
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PassphraseCredentialTransaction::TYPE_NAME:
      case PassphraseCredentialTransaction::TYPE_DESCRIPTION:
      case PassphraseCredentialTransaction::TYPE_USERNAME:
      case PassphraseCredentialTransaction::TYPE_SECRET_ID:
      case PassphraseCredentialTransaction::TYPE_LOOKEDATSECRET:
        return $xaction->getNewValue();
      case PassphraseCredentialTransaction::TYPE_DESTROY:
      case PassphraseCredentialTransaction::TYPE_LOCK:
        return (int)$xaction->getNewValue();
      case PassphraseCredentialTransaction::TYPE_CONDUIT:
        return (int)$xaction->getNewValue();
    }
    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PassphraseCredentialTransaction::TYPE_NAME:
        $object->setName($xaction->getNewValue());
        return;
      case PassphraseCredentialTransaction::TYPE_DESCRIPTION:
        $object->setDescription($xaction->getNewValue());
        return;
      case PassphraseCredentialTransaction::TYPE_USERNAME:
        $object->setUsername($xaction->getNewValue());
        return;
      case PassphraseCredentialTransaction::TYPE_SECRET_ID:
        $old_id = $object->getSecretID();
        if ($old_id) {
          $this->destroySecret($old_id);
        }
        $object->setSecretID($xaction->getNewValue());
        return;
      case PassphraseCredentialTransaction::TYPE_DESTROY:
        // When destroying a credential, wipe out its secret.
        $is_destroyed = $xaction->getNewValue();
        $object->setIsDestroyed($is_destroyed);
        if ($is_destroyed) {
          $secret_id = $object->getSecretID();
          if ($secret_id) {
            $this->destroySecret($secret_id);
            $object->setSecretID(null);
          }
        }
        return;
      case PassphraseCredentialTransaction::TYPE_LOOKEDATSECRET:
        return;
      case PassphraseCredentialTransaction::TYPE_LOCK:
        $object->setIsLocked((int)$xaction->getNewValue());
        return;
      case PassphraseCredentialTransaction::TYPE_CONDUIT:
        $object->setAllowConduit((int)$xaction->getNewValue());
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PassphraseCredentialTransaction::TYPE_NAME:
      case PassphraseCredentialTransaction::TYPE_DESCRIPTION:
      case PassphraseCredentialTransaction::TYPE_USERNAME:
      case PassphraseCredentialTransaction::TYPE_SECRET_ID:
      case PassphraseCredentialTransaction::TYPE_DESTROY:
      case PassphraseCredentialTransaction::TYPE_LOOKEDATSECRET:
      case PassphraseCredentialTransaction::TYPE_LOCK:
      case PassphraseCredentialTransaction::TYPE_CONDUIT:
        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
  }

  private function destroySecret($secret_id) {
    $table = new PassphraseSecret();
    queryfx(
      $table->establishConnection('w'),
      'DELETE FROM %T WHERE id = %d',
      $table->getTableName(),
      $secret_id);
  }

  protected function validateTransaction(
    PhabricatorLiskDAO $object,
    $type,
    array $xactions) {

    $errors = parent::validateTransaction($object, $type, $xactions);

    switch ($type) {
      case PassphraseCredentialTransaction::TYPE_NAME:
        $missing = $this->validateIsEmptyTextField(
          $object->getName(),
          $xactions);

        if ($missing) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            pht('Credential name is required.'),
            nonempty(last($xactions), null));

          $error->setIsMissingFieldError(true);
          $errors[] = $error;
        }
        break;
      case PassphraseCredentialTransaction::TYPE_USERNAME:
        $missing = $this->validateIsEmptyTextField(
          $object->getUsername(),
          $xactions);

        if ($missing) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            pht('Username is required.'),
            nonempty(last($xactions), null));

          $error->setIsMissingFieldError(true);
          $errors[] = $error;
        }
        break;
    }

    return $errors;
  }

  protected function supportsSearch() {
    return true;
  }
}
