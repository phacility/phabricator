<?php

final class PhameBlogParentSiteTransaction
  extends PhameBlogTransactionType {

  const TRANSACTIONTYPE = 'phame.blog.parent.site';

  public function generateOldValue($object) {
    return $object->getParentSite();
  }

  public function applyInternalEffects($object, $value) {
    $object->setParentSite($value);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    if (!strlen($old)) {
      return pht(
        '%s set this blog\'s parent site to %s.',
        $this->renderAuthor(),
        $this->renderNewValue());
    } else {
      return pht(
        '%s updated the blog\'s parent site from %s to %s.',
        $this->renderAuthor(),
        $this->renderOldValue(),
        $this->renderNewValue());
    }
  }

  public function getTitleForFeed() {
    $old = $this->getOldValue();
    if (!strlen($old)) {
      return pht(
        '%s set %s blog\'s parent site to %s.',
        $this->renderAuthor(),
        $this->renderObject(),
        $this->renderNewValue());
    } else {
      return pht(
        '%s updated %s blog\'s parent site from %s to %s.',
        $this->renderAuthor(),
        $this->renderObject(),
        $this->renderOldValue(),
        $this->renderNewValue());
    }
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $max_length = $object->getColumnMaximumByteLength('parentSite');
    foreach ($xactions as $xaction) {
      $new_value = $xaction->getNewValue();
      $new_length = strlen($new_value);
      if ($new_length > $max_length) {
        $errors[] = $this->newInvalidError(
          pht('The parent site can be no longer than %s characters.',
          new PhutilNumber($max_length)));
      }
    }

    return $errors;
  }
}
