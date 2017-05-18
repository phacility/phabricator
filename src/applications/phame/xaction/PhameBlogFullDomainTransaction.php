<?php

final class PhameBlogFullDomainTransaction
  extends PhameBlogTransactionType {

  const TRANSACTIONTYPE = 'phame.blog.full.domain';

  public function generateOldValue($object) {
    return $object->getDomainFullURI();
  }

  public function applyInternalEffects($object, $value) {
    if (strlen($value)) {
      $uri = new PhutilURI($value);
      $domain = $uri->getDomain();
      $object->setDomain($domain);
    } else {
      $object->setDomain(null);
    }
    $object->setDomainFullURI($value);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    if (!strlen($old)) {
      return pht(
        '%s set this blog\'s full domain to %s.',
        $this->renderAuthor(),
        $this->renderNewValue());
    } else {
      return pht(
        '%s updated the blog\'s full domain from %s to %s.',
        $this->renderAuthor(),
        $this->renderOldValue(),
        $this->renderNewValue());
    }
  }

  public function getTitleForFeed() {
    $old = $this->getOldValue();
    if (!strlen($old)) {
      return pht(
        '%s set %s blog\'s full domain to %s.',
        $this->renderAuthor(),
        $this->renderObject(),
        $this->renderNewValue());
    } else {
      return pht(
        '%s updated %s blog\'s full domain from %s to %s.',
        $this->renderAuthor(),
        $this->renderObject(),
        $this->renderOldValue(),
        $this->renderNewValue());
    }
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    if (!$xactions) {
      return $errors;
    }

    $custom_domain = last($xactions)->getNewValue();
    if (empty($custom_domain)) {
      return $errors;
    }

    $error_text = $object->validateCustomDomain($custom_domain);
    if ($error_text) {
      $errors[] = $this->newInvalidError($error_text);
    }

    if ($object->getViewPolicy() != PhabricatorPolicies::POLICY_PUBLIC) {
      $errors[] = $this->newInvalidError(
        pht('For custom domains to work, the blog must have a view policy of '.
            'public. This blog is currently set to "%s".',
            $object->getViewPolicy()));
    }

    $domain = new PhutilURI($custom_domain);
    $domain = $domain->getDomain();
    $duplicate_blog = id(new PhameBlogQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withDomain($domain)
      ->executeOne();
    if ($duplicate_blog && $duplicate_blog->getID() != $object->getID()) {
      $errors[] = $this->newInvalidError(
        pht('Domain must be unique; another blog already has this domain.'));
    }

    return $errors;
  }

}
