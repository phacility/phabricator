<?php

final class PhameBlogEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorPhameApplication';
  }

  public function getEditorObjectsDescription() {
    return pht('Blogs');
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = PhameBlogTransaction::TYPE_NAME;
    $types[] = PhameBlogTransaction::TYPE_DESCRIPTION;
    $types[] = PhameBlogTransaction::TYPE_DOMAIN;
    $types[] = PhameBlogTransaction::TYPE_SKIN;
    $types[] = PhabricatorTransactions::TYPE_VIEW_POLICY;
    $types[] = PhabricatorTransactions::TYPE_EDIT_POLICY;
    $types[] = PhabricatorTransactions::TYPE_JOIN_POLICY;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhameBlogTransaction::TYPE_NAME:
        return $object->getName();
      case PhameBlogTransaction::TYPE_DESCRIPTION:
        return $object->getDescription();
      case PhameBlogTransaction::TYPE_DOMAIN:
        return $object->getDomain();
      case PhameBlogTransaction::TYPE_SKIN:
        return $object->getSkin();
    }
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhameBlogTransaction::TYPE_NAME:
      case PhameBlogTransaction::TYPE_DESCRIPTION:
      case PhameBlogTransaction::TYPE_DOMAIN:
      case PhameBlogTransaction::TYPE_SKIN:
        return $xaction->getNewValue();
    }
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhameBlogTransaction::TYPE_NAME:
        return $object->setName($xaction->getNewValue());
      case PhameBlogTransaction::TYPE_DESCRIPTION:
        return $object->setDescription($xaction->getNewValue());
      case PhameBlogTransaction::TYPE_DOMAIN:
        return $object->setDomain($xaction->getNewValue());
      case PhameBlogTransaction::TYPE_SKIN:
        return $object->setSkin($xaction->getNewValue());
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case PhameBlogTransaction::TYPE_NAME:
      case PhameBlogTransaction::TYPE_DESCRIPTION:
      case PhameBlogTransaction::TYPE_DOMAIN:
      case PhameBlogTransaction::TYPE_SKIN:
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
        break;
      case PhameBlogTransaction::TYPE_DOMAIN:
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
        $duplicate_blog = id(new PhameBlogQuery())
          ->setViewer(PhabricatorUser::getOmnipotentUser())
          ->withDomain($custom_domain)
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
    return false;
  }

  protected function shouldPublishFeedStory(
    PhabricatorLiskDAO $object,
    array $xactions) {
    return false;
  }

  protected function supportsSearch() {
    return false;
  }

}
