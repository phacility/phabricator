<?php

final class PhortuneAddPaymentMethodAction
  extends PhabricatorSystemAction {

  const TYPECONST = 'phortune.payment-method.add';

  public function getScoreThreshold() {
    return 60 / phutil_units('1 hour in seconds');
  }

  public function getLimitExplanation() {
    return pht(
      'You are making too many attempts to add payment methods in a short '.
      'period of time.');
  }

}
