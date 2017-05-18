<?php

final class PhamePostVisibilityTransaction
  extends PhamePostTransactionType {

  const TRANSACTIONTYPE = 'phame.post.visibility';

  public function generateOldValue($object) {
    return $object->getVisibility();
  }

  public function applyInternalEffects($object, $value) {
    if ($value == PhameConstants::VISIBILITY_DRAFT) {
      $object->setDatePublished(0);
    } else if ($value == PhameConstants::VISIBILITY_ARCHIVED) {
      $object->setDatePublished(0);
    } else {
      $object->setDatePublished(PhabricatorTime::getNow());
    }
    $object->setVisibility($value);
  }

  public function getTitle() {
    $new = $this->getNewValue();
    if ($new == PhameConstants::VISIBILITY_DRAFT) {
      return pht(
        '%s marked this post as a draft.',
        $this->renderAuthor());
    } else if ($new == PhameConstants::VISIBILITY_ARCHIVED) {
      return pht(
        '%s archived this post.',
        $this->renderAuthor());
    } else {
      return pht(
      '%s published this post.',
      $this->renderAuthor());
    }
  }

  public function getTitleForFeed() {
    $new = $this->getNewValue();
    if ($new == PhameConstants::VISIBILITY_DRAFT) {
      return pht(
        '%s marked %s as a draft.',
        $this->renderAuthor(),
        $this->renderObject());
    } else if ($new == PhameConstants::VISIBILITY_ARCHIVED) {
      return pht(
        '%s marked %s as archived.',
        $this->renderAuthor(),
        $this->renderObject());
    } else {
      return pht(
        '%s published %s.',
        $this->renderAuthor(),
        $this->renderObject());
    }
  }

  public function getIcon() {
    $new = $this->getNewValue();
    if ($new == PhameConstants::VISIBILITY_PUBLISHED) {
      return 'fa-rss';
    } else if ($new == PhameConstants::VISIBILITY_ARCHIVED) {
      return 'fa-ban';
    } else {
      return 'fa-eye-slash';
    }
  }
}
