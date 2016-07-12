<?php


final class PhortuneAccountEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorPhortuneApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Phortune Accounts');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorTransactions::TYPE_EDGE;
    $types[] = PhortuneAccountTransaction::TYPE_NAME;

    return $types;
  }


  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhortuneAccountTransaction::TYPE_NAME:
        return $object->getName();
    }
    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhortuneAccountTransaction::TYPE_NAME:
        return $xaction->getNewValue();
    }
    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhortuneAccountTransaction::TYPE_NAME:
        $object->setName($xaction->getNewValue());
        return;
    }
    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhortuneAccountTransaction::TYPE_NAME:
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
      case PhortuneAccountTransaction::TYPE_NAME:
        $missing = $this->validateIsEmptyTextField(
          $object->getName(),
          $xactions);

        if ($missing) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            pht('Account name is required.'),
            nonempty(last($xactions), null));

          $error->setIsMissingFieldError(true);
          $errors[] = $error;
        }
        break;
      case PhabricatorTransactions::TYPE_EDGE:
        foreach ($xactions as $xaction) {
          switch ($xaction->getMetadataValue('edge:type')) {
            case PhortuneAccountHasMemberEdgeType::EDGECONST:
              // TODO: This is a bit cumbersome, but validation happens before
              // transaction normalization. Maybe provide a cleaner attack on
              // this eventually? There's no way to generate "+" or "-"
              // transactions right now.
              $new = $xaction->getNewValue();
              $set = idx($new, '=', array());

              if (empty($set[$this->requireActor()->getPHID()])) {
                $error = new PhabricatorApplicationTransactionValidationError(
                  $type,
                  pht('Invalid'),
                  pht('You can not remove yourself as an account member.'),
                  $xaction);
                $errors[] = $error;
              }
              break;
          }
        }
        break;
    }

    return $errors;
  }
}
