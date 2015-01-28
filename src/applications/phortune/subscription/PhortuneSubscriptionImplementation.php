<?php

abstract class PhortuneSubscriptionImplementation {

  abstract public function loadImplementationsForRefs(
    PhabricatorUser $viewer,
    array $refs);

  abstract public function getRef();
  abstract public function getName(PhortuneSubscription $subscription);

  protected function getContentSource() {
    return PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_PHORTUNE,
      array());
  }

}
