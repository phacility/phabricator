<?php

final class PhamePostViewsTransaction
  extends PhamePostTransactionType {

  const TRANSACTIONTYPE = 'phame.post.views';

  public function generateOldValue($object) {
    return $object->getViews();
  }

  public function applyInternalEffects($object, $value) {
    $views = $object->getViews();
    $views++;
    $object->setViews($views);
  }

}
