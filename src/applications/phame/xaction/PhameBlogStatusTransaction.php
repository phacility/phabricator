<?php

final class PhameBlogStatusTransaction
  extends PhameBlogTransactionType {

  const TRANSACTIONTYPE = 'phame.blog.status';

  public function generateOldValue($object) {
    return $object->getStatus();
  }

  public function applyInternalEffects($object, $value) {
    $object->setStatus($value);
  }

  public function getTitle() {
    $new = $this->getNewValue();
    switch ($new) {
      case PhameBlog::STATUS_ACTIVE:
        return pht(
          '%s published this blog.',
          $this->renderAuthor());
      case PhameBlog::STATUS_ARCHIVED:
        return pht(
          '%s archived this blog.',
          $this->renderAuthor());
    }
  }

  public function getTitleForFeed() {
    $new = $this->getNewValue();
    switch ($new) {
      case PhameBlog::STATUS_ACTIVE:
        return pht(
          '%s published the blog %s.',
          $this->renderAuthor(),
          $this->renderObject());
      case PhameBlog::STATUS_ARCHIVED:
        return pht(
          '%s archived the blog %s.',
          $this->renderAuthor(),
          $this->renderObject());
    }
  }

  public function getIcon() {
    $new = $this->getNewValue();
    if ($new == PhameBlog::STATUS_ARCHIVED) {
      return 'fa-ban';
    } else {
      return 'fa-check';
    }
  }

}
