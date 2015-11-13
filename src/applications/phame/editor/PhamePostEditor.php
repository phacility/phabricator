<?php

final class PhamePostEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorPhameApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Phame Posts');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhamePostTransaction::TYPE_TITLE;
    $types[] = PhamePostTransaction::TYPE_PHAME_TITLE;
    $types[] = PhamePostTransaction::TYPE_BODY;
    $types[] = PhamePostTransaction::TYPE_VISIBILITY;
    $types[] = PhabricatorTransactions::TYPE_COMMENT;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhamePostTransaction::TYPE_TITLE:
        return $object->getTitle();
      case PhamePostTransaction::TYPE_PHAME_TITLE:
        return $object->getPhameTitle();
      case PhamePostTransaction::TYPE_BODY:
        return $object->getBody();
      case PhamePostTransaction::TYPE_VISIBILITY:
        return $object->getVisibility();
    }
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhamePostTransaction::TYPE_TITLE:
      case PhamePostTransaction::TYPE_PHAME_TITLE:
      case PhamePostTransaction::TYPE_BODY:
      case PhamePostTransaction::TYPE_VISIBILITY:
        return $xaction->getNewValue();
    }
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhamePostTransaction::TYPE_TITLE:
        return $object->setTitle($xaction->getNewValue());
      case PhamePostTransaction::TYPE_PHAME_TITLE:
        return $object->setPhameTitle($xaction->getNewValue());
      case PhamePostTransaction::TYPE_BODY:
        return $object->setBody($xaction->getNewValue());
      case PhamePostTransaction::TYPE_VISIBILITY:
        if ($xaction->getNewValue() == PhameConstants::VISIBILITY_DRAFT) {
          $object->setDatePublished(0);
        } else {
          $object->setDatePublished(time());
        }
        return $object->setVisibility($xaction->getNewValue());
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhamePostTransaction::TYPE_TITLE:
      case PhamePostTransaction::TYPE_PHAME_TITLE:
      case PhamePostTransaction::TYPE_BODY:
      case PhamePostTransaction::TYPE_VISIBILITY:
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
      case PhamePostTransaction::TYPE_TITLE:
        $missing = $this->validateIsEmptyTextField(
          $object->getTitle(),
          $xactions);

        if ($missing) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            pht('Title is required.'),
            nonempty(last($xactions), null));

          $error->setIsMissingFieldError(true);
          $errors[] = $error;
        }
        break;
      case PhamePostTransaction::TYPE_PHAME_TITLE:
        if (!$xactions) {
          continue;
        }
        $missing = $this->validateIsEmptyTextField(
          $object->getPhameTitle(),
          $xactions);
        $phame_title = last($xactions)->getNewValue();

        if ($missing || $phame_title == '/') {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            pht('Phame title is required.'),
            nonempty(last($xactions), null));

          $error->setIsMissingFieldError(true);
          $errors[] = $error;
        }

        $duplicate_post = id(new PhamePostQuery())
          ->setViewer(PhabricatorUser::getOmnipotentUser())
          ->withPhameTitles(array($phame_title))
          ->executeOne();
        if ($duplicate_post && $duplicate_post->getID() != $object->getID()) {
          $error_text = pht(
            'Phame title must be unique; another post already has this phame '.
            'title.');
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Not Unique'),
            $error_text,
            nonempty(last($xactions), null));
          $errors[] = $error;
        }

        break;
    }
    return $errors;
  }

  protected function shouldSendMail(
    PhabricatorLiskDAO $object,
    array $xactions) {
    if ($object->isDraft()) {
      return false;
    }
    return true;
  }

  protected function shouldPublishFeedStory(
    PhabricatorLiskDAO $object,
    array $xactions) {
    if ($object->isDraft()) {
      return false;
    }
    return true;
  }

  protected function getMailTo(PhabricatorLiskDAO $object) {
    $phids = array();
    $phids[] = $object->getBloggerPHID();
    $phids[] = $this->requireActor()->getPHID();

    $blog_phid = $object->getBlogPHID();
    if ($blog_phid) {
      $cc_phids = PhabricatorSubscribersQuery::loadSubscribersForPHID(
        $blog_phid);
      foreach ($cc_phids as $cc) {
        $phids[] = $cc;
      }
    }
    return $phids;
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $phid = $object->getPHID();
    $title = $object->getTitle();

    return id(new PhabricatorMetaMTAMail())
      ->setSubject($title)
      ->addHeader('Thread-Topic', $phid);
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    return id(new PhamePostReplyHandler())
      ->setMailReceiver($object);
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $body = parent::buildMailBody($object, $xactions);

    if ($this->getIsNewObject()) {
      $body->addRemarkupSection(null, $object->getBody());
    }

    $body->addLinkSection(
      pht('POST DETAIL'),
      PhabricatorEnv::getProductionURI($object->getViewURI()));

    return $body;
  }

  public function getMailTagsMap() {
    return array(
      PhamePostTransaction::MAILTAG_CONTENT =>
        pht("A post's content changes."),
      PhamePostTransaction::MAILTAG_COMMENT =>
        pht('Someone comments on a post.'),
      PhamePostTransaction::MAILTAG_OTHER =>
        pht('Other post activity not listed above occurs.'),
    );
  }

  protected function getMailSubjectPrefix() {
    return '[Phame]';
  }

  protected function supportsSearch() {
    return false;
  }

}
