<?php

final class PhameBlogParentDomainTransaction
  extends PhameBlogTransactionType {

  const TRANSACTIONTYPE = 'phame.blog.parent.domain';

  public function generateOldValue($object) {
    return $object->getParentDomain();
  }

  public function applyInternalEffects($object, $value) {
    $object->setParentDomain($value);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    if (!strlen($old)) {
      return pht(
        '%s set this blog\'s parent domain to %s.',
        $this->renderAuthor(),
        $this->renderNewValue());
    } else {
      return pht(
        '%s updated the blog\'s parent domain from %s to %s.',
        $this->renderAuthor(),
        $this->renderOldValue(),
        $this->renderNewValue());
    }
  }

  public function getTitleForFeed() {
    $old = $this->getOldValue();
    if (!strlen($old)) {
      return pht(
        '%s set %s blog\'s parent domain to %s.',
        $this->renderAuthor(),
        $this->renderObject(),
        $this->renderNewValue());
    } else {
      return pht(
        '%s updated %s blog\'s parent domain from %s to %s.',
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

    $parent_domain = last($xactions)->getNewValue();
    if (empty($parent_domain)) {
      return $errors;
    }

    try {
      PhabricatorEnv::requireValidRemoteURIForLink($parent_domain);
    } catch (Exception $ex) {
      $errors[] = $this->newInvalidError(
        pht('Parent Domain must be set to a valid Remote URI.'));
    }

    $max_length = $object->getColumnMaximumByteLength('parentDomain');
    foreach ($xactions as $xaction) {
      $new_value = $xaction->getNewValue();
      $new_length = strlen($new_value);
      if ($new_length > $max_length) {
        $errors[] = $this->newInvalidError(
          pht('The parent domain can be no longer than %s characters.',
          new PhutilNumber($max_length)));
      }
    }

    return $errors;
  }
}
