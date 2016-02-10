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
    $types[] = PhabricatorPhurlURLTransaction::TYPE_ALIAS;
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
      case PhabricatorPhurlURLTransaction::TYPE_ALIAS:
        return $object->getAlias();
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
      case PhabricatorPhurlURLTransaction::TYPE_ALIAS:
        if (!strlen($xaction->getNewValue())) {
          return null;
        }
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
      case PhabricatorPhurlURLTransaction::TYPE_ALIAS:
        $object->setAlias($xaction->getNewValue());
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
      case PhabricatorPhurlURLTransaction::TYPE_ALIAS:
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
      case PhabricatorPhurlURLTransaction::TYPE_ALIAS:
        $overdrawn = $this->validateIsTextFieldTooLong(
          $object->getName(),
          $xactions,
          64);

        if ($overdrawn) {
          $errors[] = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Alias Too Long'),
            pht('The alias can be no longer than 64 characters.'),
            nonempty(last($xactions), null));
        }

        foreach ($xactions as $xaction) {
          if ($xaction->getOldValue() != $xaction->getNewValue()) {
            $new_alias = $xaction->getNewValue();
            if (!preg_match('/[a-zA-Z]/', $new_alias)) {
              $errors[] = new PhabricatorApplicationTransactionValidationError(
                $type,
                pht('Invalid Alias'),
                pht('The alias must contain at least one letter.'),
                $xaction);
            }
            if (preg_match('/[^a-z0-9]/i', $new_alias)) {
              $errors[] = new PhabricatorApplicationTransactionValidationError(
                $type,
                pht('Invalid Alias'),
                pht('The alias may only contain letters and numbers.'),
                $xaction);
            }
          }
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

        foreach ($xactions as $xaction) {
          if ($xaction->getOldValue() != $xaction->getNewValue()) {
            $protocols = PhabricatorEnv::getEnvConfig('uri.allowed-protocols');
            $uri = new PhutilURI($xaction->getNewValue());
            if (!isset($protocols[$uri->getProtocol()])) {
              $errors[] = new PhabricatorApplicationTransactionValidationError(
                $type,
                pht('Invalid URL'),
                pht('The protocol of the URL is invalid.'),
                null);
            }
          }
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
      PhabricatorPhurlURLTransaction::MAILTAG_DETAILS =>
        pht(
          "A URL's details change."),
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
      $body->addRemarkupSection(
        pht('URL DESCRIPTION'),
        $object->getDescription());
    }

    $body->addLinkSection(
      pht('URL DETAIL'),
      PhabricatorEnv::getProductionURI('/U'.$object->getID()));


    return $body;
  }

  protected function didCatchDuplicateKeyException(
    PhabricatorLiskDAO $object,
    array $xactions,
    Exception $ex) {

    $errors = array();
    $errors[] = new PhabricatorApplicationTransactionValidationError(
      PhabricatorPhurlURLTransaction::TYPE_ALIAS,
      pht('Duplicate'),
      pht('This alias is already in use.'),
      null);

    throw new PhabricatorApplicationTransactionValidationException($errors);
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    return id(new PhabricatorPhurlURLReplyHandler())
      ->setMailReceiver($object);
  }

}
