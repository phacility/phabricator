<?php

final class PhameBlogSubtitleTransaction
  extends PhameBlogTransactionType {

  const TRANSACTIONTYPE = 'phame.blog.subtitle';

  public function generateOldValue($object) {
    return $object->getSubtitle();
  }

  public function applyInternalEffects($object, $value) {
    $object->setSubtitle($value);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    if ($old === null) {
      return pht(
        '%s set this blog\'s subtitle to "%s".',
        $this->renderAuthor(),
        $this->renderNewValue());
    } else {
      return pht(
        '%s updated the blog\'s subtitle to "%s".',
        $this->renderAuthor(),
        $this->renderNewValue());
    }
  }

  public function getTitleForFeed() {
    $old = $this->getOldValue();
    if ($old === null) {
      return pht(
        '%s set the subtitle for %s.',
        $this->renderAuthor(),
        $this->renderObject());
    } else {
      return pht(
        '%s updated the subtitle for %s.',
        $this->renderAuthor(),
        $this->renderObject());
    }
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $max_length = $object->getColumnMaximumByteLength('subtitle');
    foreach ($xactions as $xaction) {
      $new_value = $xaction->getNewValue();
      $new_length = strlen($new_value);
      if ($new_length > $max_length) {
        $errors[] = $this->newInvalidError(
          pht('The subtitle can be no longer than %s characters.',
          new PhutilNumber($max_length)));
      }
    }

    return $errors;
  }

}
