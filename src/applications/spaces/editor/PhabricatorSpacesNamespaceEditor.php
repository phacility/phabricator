<?php

final class PhabricatorSpacesNamespaceEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return pht('PhabricatorSpacesApplication');
  }

  public function getEditorObjectsDescription() {
    return pht('Spaces');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorSpacesNamespaceTransaction::TYPE_NAME;
    $types[] = PhabricatorSpacesNamespaceTransaction::TYPE_DEFAULT;

    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorSpacesNamespaceTransaction::TYPE_NAME:
        $name = $object->getNamespaceName();
        if (!strlen($name)) {
          return null;
        }
        return $name;
      case PhabricatorSpacesNamespaceTransaction::TYPE_DEFAULT:
        return $object->getIsDefaultNamespace() ? 1 : null;
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
        return $object->getViewPolicy();
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
        return $object->getEditPolicy();
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorSpacesNamespaceTransaction::TYPE_NAME:
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
        return $xaction->getNewValue();
      case PhabricatorSpacesNamespaceTransaction::TYPE_DEFAULT:
        return $xaction->getNewValue() ? 1 : null;
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $new = $xaction->getNewValue();

    switch ($xaction->getTransactionType()) {
      case PhabricatorSpacesNamespaceTransaction::TYPE_NAME:
        $object->setNamespaceName($new);
        return;
      case PhabricatorSpacesNamespaceTransaction::TYPE_DEFAULT:
        $object->setIsDefaultNamespace($new ? 1 : null);
        return;
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
        $object->setViewPolicy($new);
        return;
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
        $object->setEditPolicy($new);
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorSpacesNamespaceTransaction::TYPE_NAME:
      case PhabricatorSpacesNamespaceTransaction::TYPE_DEFAULT:
      case PhabricatorTransactions::TYPE_VIEW_POLICY:
      case PhabricatorTransactions::TYPE_EDIT_POLICY:
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
      case PhabricatorSpacesNamespaceTransaction::TYPE_NAME:
        $missing = $this->validateIsEmptyTextField(
          $object->getNamespaceName(),
          $xactions);

        if ($missing) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
              pht('Spaces must have a name.'),
            nonempty(last($xactions), null));

          $error->setIsMissingFieldError(true);
          $errors[] = $error;
        }
        break;
    }

    return $errors;
  }

}
