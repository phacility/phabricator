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

    $types[] = PhamePostTransaction::TYPE_BLOG;
    $types[] = PhamePostTransaction::TYPE_TITLE;
    $types[] = PhamePostTransaction::TYPE_BODY;
    $types[] = PhamePostTransaction::TYPE_VISIBILITY;
    $types[] = PhabricatorTransactions::TYPE_COMMENT;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhamePostTransaction::TYPE_BLOG:
        return $object->getBlogPHID();
      case PhamePostTransaction::TYPE_TITLE:
        return $object->getTitle();
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
      case PhamePostTransaction::TYPE_BODY:
      case PhamePostTransaction::TYPE_VISIBILITY:
      case PhamePostTransaction::TYPE_BLOG:
        return $xaction->getNewValue();
    }
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhamePostTransaction::TYPE_TITLE:
        return $object->setTitle($xaction->getNewValue());
      case PhamePostTransaction::TYPE_BODY:
        return $object->setBody($xaction->getNewValue());
      case PhamePostTransaction::TYPE_BLOG:
        return $object->setBlogPHID($xaction->getNewValue());
      case PhamePostTransaction::TYPE_VISIBILITY:
        if ($xaction->getNewValue() == PhameConstants::VISIBILITY_DRAFT) {
          $object->setDatePublished(0);
        } else {
          $object->setDatePublished(PhabricatorTime::getNow());
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
      case PhamePostTransaction::TYPE_BODY:
      case PhamePostTransaction::TYPE_VISIBILITY:
      case PhamePostTransaction::TYPE_BLOG:
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
      case PhamePostTransaction::TYPE_BLOG:
        if ($this->getIsNewObject()) {
          if (!$xactions) {
            $error = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Required'),
              pht(
                'When creating a post, you must specify which blog it '.
                'should belong to.'),
              null);

            $error->setIsMissingFieldError(true);

            $errors[] = $error;
            break;
          }
        }

        foreach ($xactions as $xaction) {
          $new_phid = $xaction->getNewValue();

          $blog = id(new PhameBlogQuery())
            ->setViewer($this->getActor())
            ->withPHIDs(array($new_phid))
            ->requireCapabilities(
              array(
                PhabricatorPolicyCapability::CAN_VIEW,
                PhabricatorPolicyCapability::CAN_EDIT,
              ))
            ->execute();

          if ($blog) {
            continue;
          }

          $errors[] = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Invalid'),
            pht(
              'The specified blog PHID ("%s") is not valid. You can only '.
              'create a post on (or move a post into) a blog which you '.
              'have permission to see and edit.',
              $new_phid),
            $xaction);
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

    // We don't send mail if the object is a draft, and we only want
    // to include the full body of the post on the either the
    // first creation or if it was created as a draft, once it goes live.
    if ($this->getIsNewObject()) {
      $body->addRemarkupSection(null, $object->getBody());
    } else {
      foreach ($xactions as $xaction) {
        switch ($xaction->getTransactionType()) {
          case PhamePostTransaction::TYPE_VISIBILITY:
            if (!$object->isDraft()) {
              $body->addRemarkupSection(null, $object->getBody());
            }
          break;
        }
      }
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
      PhamePostTransaction::MAILTAG_SUBSCRIBERS =>
        pht("A post's subscribers change."),
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

  protected function shouldApplyHeraldRules(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function buildHeraldAdapter(
    PhabricatorLiskDAO $object,
    array $xactions) {

    return id(new HeraldPhamePostAdapter())
      ->setPost($object);
  }

}
