<?php

final class PhabricatorOAuthServerEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorOAuthServerApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('OAuth Applications');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorOAuthServerTransaction::TYPE_NAME;
    $types[] = PhabricatorOAuthServerTransaction::TYPE_REDIRECT_URI;
    $types[] = PhabricatorOAuthServerTransaction::TYPE_DISABLED;

    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorOAuthServerTransaction::TYPE_NAME:
        return $object->getName();
      case PhabricatorOAuthServerTransaction::TYPE_REDIRECT_URI:
        return $object->getRedirectURI();
      case PhabricatorOAuthServerTransaction::TYPE_DISABLED:
        return $object->getIsDisabled();
    }
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorOAuthServerTransaction::TYPE_NAME:
      case PhabricatorOAuthServerTransaction::TYPE_REDIRECT_URI:
        return $xaction->getNewValue();
      case PhabricatorOAuthServerTransaction::TYPE_DISABLED:
        return (int)$xaction->getNewValue();
    }
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorOAuthServerTransaction::TYPE_NAME:
        $object->setName($xaction->getNewValue());
        return;
      case PhabricatorOAuthServerTransaction::TYPE_REDIRECT_URI:
        $object->setRedirectURI($xaction->getNewValue());
        return;
      case PhabricatorOAuthServerTransaction::TYPE_DISABLED:
        $object->setIsDisabled($xaction->getNewValue());
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorOAuthServerTransaction::TYPE_NAME:
      case PhabricatorOAuthServerTransaction::TYPE_REDIRECT_URI:
      case PhabricatorOAuthServerTransaction::TYPE_DISABLED:
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
      case PhabricatorOAuthServerTransaction::TYPE_NAME:
        $missing = $this->validateIsEmptyTextField(
          $object->getName(),
          $xactions);

        if ($missing) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            pht('OAuth applications must have a name.'),
            nonempty(last($xactions), null));

          $error->setIsMissingFieldError(true);
          $errors[] = $error;
        }
        break;
      case PhabricatorOAuthServerTransaction::TYPE_REDIRECT_URI:
        $missing = $this->validateIsEmptyTextField(
          $object->getRedirectURI(),
          $xactions);
        if ($missing) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            pht('OAuth applications must have a valid redirect URI.'),
            nonempty(last($xactions), null));

          $error->setIsMissingFieldError(true);
          $errors[] = $error;
        } else {
          foreach ($xactions as $xaction) {
            $redirect_uri = $xaction->getNewValue();

            try {
              $server = new PhabricatorOAuthServer();
              $server->assertValidRedirectURI($redirect_uri);
            } catch (Exception $ex) {
              $errors[] = new PhabricatorApplicationTransactionValidationError(
                $type,
                pht('Invalid'),
                $ex->getMessage(),
                $xaction);
            }
          }
        }
        break;
    }

    return $errors;
  }

}
