<?php

final class PhameBlogEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorPhameApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Phame Blogs');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhameBlogTransaction::TYPE_NAME;
    $types[] = PhameBlogTransaction::TYPE_SUBTITLE;
    $types[] = PhameBlogTransaction::TYPE_DESCRIPTION;
    $types[] = PhameBlogTransaction::TYPE_FULLDOMAIN;
    $types[] = PhameBlogTransaction::TYPE_PARENTSITE;
    $types[] = PhameBlogTransaction::TYPE_PARENTDOMAIN;
    $types[] = PhameBlogTransaction::TYPE_STATUS;
    $types[] = PhameBlogTransaction::TYPE_HEADERIMAGE;
    $types[] = PhameBlogTransaction::TYPE_PROFILEIMAGE;

    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhameBlogTransaction::TYPE_NAME:
        return $object->getName();
      case PhameBlogTransaction::TYPE_SUBTITLE:
        return $object->getSubtitle();
      case PhameBlogTransaction::TYPE_DESCRIPTION:
        return $object->getDescription();
      case PhameBlogTransaction::TYPE_FULLDOMAIN:
        return $object->getDomainFullURI();
      case PhameBlogTransaction::TYPE_PARENTSITE:
        return $object->getParentSite();
      case PhameBlogTransaction::TYPE_PARENTDOMAIN:
        return $object->getParentDomain();
      case PhameBlogTransaction::TYPE_PROFILEIMAGE:
        return $object->getProfileImagePHID();
      case PhameBlogTransaction::TYPE_HEADERIMAGE:
        return $object->getHeaderImagePHID();
      case PhameBlogTransaction::TYPE_STATUS:
        return $object->getStatus();
    }
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhameBlogTransaction::TYPE_NAME:
      case PhameBlogTransaction::TYPE_SUBTITLE:
      case PhameBlogTransaction::TYPE_DESCRIPTION:
      case PhameBlogTransaction::TYPE_STATUS:
      case PhameBlogTransaction::TYPE_PARENTSITE:
      case PhameBlogTransaction::TYPE_PARENTDOMAIN:
      case PhameBlogTransaction::TYPE_PROFILEIMAGE:
      case PhameBlogTransaction::TYPE_HEADERIMAGE:
        return $xaction->getNewValue();
      case PhameBlogTransaction::TYPE_FULLDOMAIN:
        $domain = $xaction->getNewValue();
        if (!strlen($xaction->getNewValue())) {
          return null;
        }
        return $domain;
    }
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhameBlogTransaction::TYPE_NAME:
        return $object->setName($xaction->getNewValue());
      case PhameBlogTransaction::TYPE_SUBTITLE:
        return $object->setSubtitle($xaction->getNewValue());
      case PhameBlogTransaction::TYPE_DESCRIPTION:
        return $object->setDescription($xaction->getNewValue());
      case PhameBlogTransaction::TYPE_FULLDOMAIN:
        $new_value = $xaction->getNewValue();
        if (strlen($new_value)) {
          $uri = new PhutilURI($new_value);
          $domain = $uri->getDomain();
          $object->setDomain($domain);
        } else {
          $object->setDomain(null);
        }
        $object->setDomainFullURI($new_value);
        return;
      case PhameBlogTransaction::TYPE_PROFILEIMAGE:
        return $object->setProfileImagePHID($xaction->getNewValue());
      case PhameBlogTransaction::TYPE_HEADERIMAGE:
        return $object->setHeaderImagePHID($xaction->getNewValue());
      case PhameBlogTransaction::TYPE_STATUS:
        return $object->setStatus($xaction->getNewValue());
      case PhameBlogTransaction::TYPE_PARENTSITE:
        return $object->setParentSite($xaction->getNewValue());
      case PhameBlogTransaction::TYPE_PARENTDOMAIN:
        return $object->setParentDomain($xaction->getNewValue());
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhameBlogTransaction::TYPE_NAME:
      case PhameBlogTransaction::TYPE_SUBTITLE:
      case PhameBlogTransaction::TYPE_DESCRIPTION:
      case PhameBlogTransaction::TYPE_FULLDOMAIN:
      case PhameBlogTransaction::TYPE_PARENTSITE:
      case PhameBlogTransaction::TYPE_PARENTDOMAIN:
      case PhameBlogTransaction::TYPE_HEADERIMAGE:
      case PhameBlogTransaction::TYPE_PROFILEIMAGE:
      case PhameBlogTransaction::TYPE_STATUS:
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
      case PhameBlogTransaction::TYPE_NAME:
        $missing = $this->validateIsEmptyTextField(
          $object->getName(),
          $xactions);

        if ($missing) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Required'),
            pht('Name is required.'),
            nonempty(last($xactions), null));

          $error->setIsMissingFieldError(true);
          $errors[] = $error;
        }

        foreach ($xactions as $xaction) {
          $new = $xaction->getNewValue();
          if (phutil_utf8_strlen($new) > 64) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              pht(
                'The selected blog title is too long. The maximum length '.
                'of a blog title is 64 characters.'),
              $xaction);
          }
        }
        break;
      case PhameBlogTransaction::TYPE_SUBTITLE:
        foreach ($xactions as $xaction) {
          $new = $xaction->getNewValue();
          if (phutil_utf8_strlen($new) > 64) {
            $errors[] = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              pht(
                'The selected blog subtitle is too long. The maximum length '.
                'of a blog subtitle is 64 characters.'),
              $xaction);
          }
        }
        break;
      case PhameBlogTransaction::TYPE_PARENTDOMAIN:
        if (!$xactions) {
          continue;
        }
        $parent_domain = last($xactions)->getNewValue();
        if (empty($parent_domain)) {
          continue;
        }
        try {
          PhabricatorEnv::requireValidRemoteURIForLink($parent_domain);
        } catch (Exception $ex) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Invalid URI'),
            pht('Parent Domain must be set to a valid Remote URI.'),
            nonempty(last($xactions), null));
          $errors[] = $error;
        }
        break;
      case PhameBlogTransaction::TYPE_FULLDOMAIN:
        if (!$xactions) {
          continue;
        }
        $custom_domain = last($xactions)->getNewValue();
        if (empty($custom_domain)) {
          continue;
        }
        list($error_label, $error_text) =
          $object->validateCustomDomain($custom_domain);
        if ($error_label) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            $error_label,
            $error_text,
            nonempty(last($xactions), null));
          $errors[] = $error;
        }
        if ($object->getViewPolicy() != PhabricatorPolicies::POLICY_PUBLIC) {
          $error_text = pht(
            'For custom domains to work, the blog must have a view policy of '.
            'public.');
          $error = new PhabricatorApplicationTransactionValidationError(
            PhabricatorTransactions::TYPE_VIEW_POLICY,
            pht('Invalid Policy'),
            $error_text,
            nonempty(last($xactions), null));
          $errors[] = $error;
        }
        $domain = new PhutilURI($custom_domain);
        $domain = $domain->getDomain();
        $duplicate_blog = id(new PhameBlogQuery())
          ->setViewer(PhabricatorUser::getOmnipotentUser())
          ->withDomain($domain)
          ->executeOne();
        if ($duplicate_blog && $duplicate_blog->getID() != $object->getID()) {
          $error = new PhabricatorApplicationTransactionValidationError(
            $type,
            pht('Not Unique'),
            pht('Domain must be unique; another blog already has this domain.'),
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
    return true;
  }

  protected function shouldPublishFeedStory(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

   protected function getMailTo(PhabricatorLiskDAO $object) {
    $phids = array();
    $phids[] = $this->requireActor()->getPHID();
    $phids[] = $object->getCreatorPHID();

    return $phids;
  }

  protected function buildMailTemplate(PhabricatorLiskDAO $object) {
    $phid = $object->getPHID();
    $name = $object->getName();

    return id(new PhabricatorMetaMTAMail())
      ->setSubject($name)
      ->addHeader('Thread-Topic', $phid);
  }

  protected function buildReplyHandler(PhabricatorLiskDAO $object) {
    return id(new PhameBlogReplyHandler())
      ->setMailReceiver($object);
  }

  protected function buildMailBody(
    PhabricatorLiskDAO $object,
    array $xactions) {

    $body = parent::buildMailBody($object, $xactions);

    $body->addLinkSection(
      pht('BLOG DETAIL'),
      PhabricatorEnv::getProductionURI($object->getViewURI()));

    return $body;
  }

  public function getMailTagsMap() {
    return array(
      PhameBlogTransaction::MAILTAG_DETAILS =>
        pht("A blog's details change."),
      PhameBlogTransaction::MAILTAG_SUBSCRIBERS =>
        pht("A blog's subscribers change."),
      PhameBlogTransaction::MAILTAG_OTHER =>
        pht('Other blog activity not listed above occurs.'),
    );
  }

  protected function getMailSubjectPrefix() {
    return '[Phame]';
  }


  protected function supportsSearch() {
    return true;
  }

  protected function shouldApplyHeraldRules(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return true;
  }

  protected function buildHeraldAdapter(
    PhabricatorLiskDAO $object,
    array $xactions) {

    return id(new HeraldPhameBlogAdapter())
      ->setBlog($object);
  }

}
