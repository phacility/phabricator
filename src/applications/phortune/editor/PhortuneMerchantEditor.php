<?php

final class PhortuneMerchantEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorPhortuneApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Phortune Merchants');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhortuneMerchantTransaction::TYPE_NAME;
    $types[] = PhortuneMerchantTransaction::TYPE_DESCRIPTION;
    $types[] = PhortuneMerchantTransaction::TYPE_CONTACTINFO;
    $types[] = PhortuneMerchantTransaction::TYPE_PICTURE;
    $types[] = PhortuneMerchantTransaction::TYPE_INVOICEEMAIL;
    $types[] = PhortuneMerchantTransaction::TYPE_INVOICEFOOTER;
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDGE;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhortuneMerchantTransaction::TYPE_NAME:
        return $object->getName();
      case PhortuneMerchantTransaction::TYPE_DESCRIPTION:
        return $object->getDescription();
      case PhortuneMerchantTransaction::TYPE_CONTACTINFO:
        return $object->getContactInfo();
      case PhortuneMerchantTransaction::TYPE_INVOICEEMAIL:
        return $object->getInvoiceEmail();
      case PhortuneMerchantTransaction::TYPE_INVOICEFOOTER:
        return $object->getInvoiceFooter();
      case PhortuneMerchantTransaction::TYPE_PICTURE:
        return $object->getProfileImagePHID();
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhortuneMerchantTransaction::TYPE_NAME:
      case PhortuneMerchantTransaction::TYPE_DESCRIPTION:
      case PhortuneMerchantTransaction::TYPE_CONTACTINFO:
      case PhortuneMerchantTransaction::TYPE_INVOICEEMAIL:
      case PhortuneMerchantTransaction::TYPE_INVOICEFOOTER:
      case PhortuneMerchantTransaction::TYPE_PICTURE:
        return $xaction->getNewValue();
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhortuneMerchantTransaction::TYPE_NAME:
        $object->setName($xaction->getNewValue());
        return;
      case PhortuneMerchantTransaction::TYPE_DESCRIPTION:
        $object->setDescription($xaction->getNewValue());
        return;
      case PhortuneMerchantTransaction::TYPE_CONTACTINFO:
        $object->setContactInfo($xaction->getNewValue());
        return;
      case PhortuneMerchantTransaction::TYPE_INVOICEEMAIL:
        $object->setInvoiceEmail($xaction->getNewValue());
        return;
      case PhortuneMerchantTransaction::TYPE_INVOICEFOOTER:
        $object->setInvoiceFooter($xaction->getNewValue());
        return;
      case PhortuneMerchantTransaction::TYPE_PICTURE:
        $object->setProfileImagePHID($xaction->getNewValue());
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhortuneMerchantTransaction::TYPE_NAME:
      case PhortuneMerchantTransaction::TYPE_DESCRIPTION:
      case PhortuneMerchantTransaction::TYPE_CONTACTINFO:
      case PhortuneMerchantTransaction::TYPE_INVOICEEMAIL:
      case PhortuneMerchantTransaction::TYPE_INVOICEFOOTER:
      case PhortuneMerchantTransaction::TYPE_PICTURE:
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
      case PhortuneMerchantTransaction::TYPE_NAME:
        $missing = $this->validateIsEmptyTextField(
          $object->getName(),
          $xactions);

        if ($missing) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            pht('Merchant name is required.'),
            nonempty(last($xactions), null));

          $error->setIsMissingFieldError(true);
          $errors[] = $error;
        }
       break;
      case PhortuneMerchantTransaction::TYPE_INVOICEEMAIL:
        $new_email = null;
        foreach ($xactions as $xaction) {
          switch ($xaction->getTransactionType()) {
            case PhortuneMerchantTransaction::TYPE_INVOICEEMAIL:
              $new_email = $xaction->getNewValue();
              break;
          }
        }
        if (strlen($new_email)) {
          $email = new PhutilEmailAddress($new_email);
          $domain = $email->getDomainName();

          if (!$domain) {
            $error = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              pht('%s is not a valid email.', $new_email),
              nonempty(last($xactions), null));

            $errors[] = $error;
          }
        }
        break;
    }

    return $errors;
  }

}
