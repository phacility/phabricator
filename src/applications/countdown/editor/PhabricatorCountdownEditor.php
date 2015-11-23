<?php

final class PhabricatorCountdownEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorCountdownApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Countdown');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhabricatorCountdownTransaction::TYPE_TITLE;
    $types[] = PhabricatorCountdownTransaction::TYPE_EPOCH;
    $types[] = PhabricatorCountdownTransaction::TYPE_DESCRIPTION;

    $types[] = PhabricatorTransactions::TYPE_EDGE;
    $types[] = PhabricatorTransactions::TYPE_SPACE;
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;
    $types[] = PhabricatorTransactions::TYPE_COMMENT;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case PhabricatorCountdownTransaction::TYPE_TITLE:
        return $object->getTitle();
      case PhabricatorCountdownTransaction::TYPE_DESCRIPTION:
        return $object->getDescription();
      case PhabricatorCountdownTransaction::TYPE_EPOCH:
        return $object->getEpoch();
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhabricatorCountdownTransaction::TYPE_TITLE:
        return $xaction->getNewValue();
      case PhabricatorCountdownTransaction::TYPE_DESCRIPTION:
        return $xaction->getNewValue();
      case PhabricatorCountdownTransaction::TYPE_EPOCH:
        return $xaction->getNewValue()->getEpoch();
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $type = $xaction->getTransactionType();
    switch ($type) {
      case PhabricatorCountdownTransaction::TYPE_TITLE:
        $object->setTitle($xaction->getNewValue());
        return;
      case PhabricatorCountdownTransaction::TYPE_DESCRIPTION:
        $object->setDescription($xaction->getNewValue());
        return;
      case PhabricatorCountdownTransaction::TYPE_EPOCH:
        $object->setEpoch($xaction->getNewValue());
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    $type = $xaction->getTransactionType();
    switch ($type) {
      case PhabricatorCountdownTransaction::TYPE_TITLE:
        return;
      case PhabricatorCountdownTransaction::TYPE_DESCRIPTION:
        return;
      case PhabricatorCountdownTransaction::TYPE_EPOCH:
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
      case PhabricatorCountdownTransaction::TYPE_TITLE:
        $missing = $this->validateIsEmptyTextField(
          $object->getTitle(),
          $xactions);

        if ($missing) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            pht('You must give the countdown a name.'),
            nonempty(last($xactions), null));

          $error->setIsMissingFieldError(true);
          $errors[] = $error;
        }
      break;
      case PhabricatorCountdownTransaction::TYPE_EPOCH:
        $date_value = AphrontFormDateControlValue::newFromEpoch(
          $this->requireActor(),
          $object->getEpoch());
        if (!$date_value->isValid()) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Invalid'),
            pht('You must give the countdown a valid end date.'),
            nonempty(last($xactions), null));

          $error->setIsMissingFieldError(true);
          $errors[] = $error;
        }
      break;
    }

    return $errors;
  }

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  public function getMailTagsMap() {
    return array(
      PhabricatorCountdownTransaction::MAILTAG_DETAILS =>
        pht('Someone changes the countdown details.'),
      PhabricatorCountdownTransaction::MAILTAG_COMMENT =>
        pht('Someone comments on a countdown.'),
      PhabricatorCountdownTransaction::MAILTAG_OTHER =>
        pht('Other countdown activity not listed above occurs.'),
    );
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $monogram = $object->getMonogram();
    $name = $object->getTitle();

    return id(new PhabricatorMetaMTAMail())
      ->setSubject("{$monogram}: {$name}")
      ->addHeader('Thread-Topic', $monogram);
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $body = parent::buildMailBody($object, $xactions);
    $description = $object->getDescription();

    if (strlen($description)) {
      $body->addRemarkupSection(
        pht('COUNTDOWN DESCRIPTION'),
        $object->getDescription());
    }

    $body->addLinkSection(
      pht('COUNTDOWN DETAIL'),
      PhabricatorEnv::getProductionURI('/'.$object->getMonogram()));

    return $body;
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    return array(
      $object->getAuthorPHID(),
      $this->requireActor()->getPHID(),
    );
  }
  protected function getMailSubjectPrefix() {
    return '[Countdown]';
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    return id(new PhabricatorCountdownReplyHandler())
      ->setMailReceiver($object);
  }

  protected function shouldPublishFeedStory(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function supportsSearch() {
    return true;
  }

}
