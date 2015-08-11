<?php

final class PhabricatorPhurlURLEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorPhurlApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Phurl');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorPhurlURLTransaction::TYPE_NAME;
    $types[] = PhabricatorPhurlURLTransaction::TYPE_URL;
    $types[] = PhabricatorPhurlURLTransaction::TYPE_DESCRIPTION;

    $types[] = PhabricatorTransactions::TYPE_COMMENT;
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhabricatorPhurlURLTransaction::TYPE_NAME:
        return $object->getName();
      case PhabricatorPhurlURLTransaction::TYPE_URL:
        return $object->getLongURL();
      case PhabricatorPhurlURLTransaction::TYPE_DESCRIPTION:
        return $object->getDescription();
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhabricatorPhurlURLTransaction::TYPE_NAME:
      case PhabricatorPhurlURLTransaction::TYPE_URL:
      case PhabricatorPhurlURLTransaction::TYPE_DESCRIPTION:
        return $xaction->getNewValue();
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorPhurlURLTransaction::TYPE_NAME:
        $object->setName($xaction->getNewValue());
        return;
      case PhabricatorPhurlURLTransaction::TYPE_URL:
        $object->setLongURL($xaction->getNewValue());
        return;
      case PhabricatorPhurlURLTransaction::TYPE_DESCRIPTION:
        $object->setDescription($xaction->getNewValue());
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorPhurlURLTransaction::TYPE_NAME:
      case PhabricatorPhurlURLTransaction::TYPE_URL:
      case PhabricatorPhurlURLTransaction::TYPE_DESCRIPTION:
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
      case PhabricatorPhurlURLTransaction::TYPE_NAME:
        $missing = $this->validateIsEmptyTextField(
          $object->getName(),
          $xactions);

        if ($missing) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            pht('URL name is required.'),
            nonempty(last($xactions), null));

          $error->setIsMissingFieldError(true);
          $errors[] = $error;
        }
        break;
      case PhabricatorPhurlURLTransaction::TYPE_URL:
        $missing = $this->validateIsEmptyTextField(
          $object->getLongURL(),
          $xactions);

        if ($missing) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            pht('URL path is required.'),
            nonempty(last($xactions), null));

          $error->setIsMissingFieldError(true);
          $errors[] = $error;
        }
        break;
    }

    return $errors;
  }

  protected function shouldPublishFeedStory(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function supportsSearch() {
    return true;
  }

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function getMailSubjectPrefix() {
    return pht('[Phurl]');
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    $phids = array();

    if ($object->getPHID()) {
      $phids[] = $object->getPHID();
    }
    $phids[] = $this->getActingAsPHID();
    $phids = array_unique($phids);

    return $phids;
  }

  public function getMailTagsMap() {
    return array(
      PhabricatorPhurlURLTransaction::MAILTAG_CONTENT =>
        pht(
          "A URL's name or path changes."),
      PhabricatorPhurlURLTransaction::MAILTAG_OTHER =>
        pht('Other event activity not listed above occurs.'),
    );
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $id = $object->getID();
    $name = $object->getName();

    return id(new PhabricatorMetaMTAMail())
      ->setSubject("U{$id}: {$name}")
      ->addHeader('Thread-Topic', "U{$id}: ".$object->getName());
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $description = $object->getDescription();
    $body = parent::buildMailBody($object, $xactions);

    if (strlen($description)) {
      $body->addTextSection(
        pht('URL DESCRIPTION'),
        $object->getDescription());
    }

    $body->addLinkSection(
      pht('URL DETAIL'),
      PhabricatorEnv::getProductionURI('/U'.$object->getID()));


    return $body;
  }


}
