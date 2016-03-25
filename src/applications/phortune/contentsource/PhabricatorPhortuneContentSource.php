<?php

final class PhabricatorPhortuneContentSource
  extends PhabricatorContentSource {

  const SOURCECONST = 'phortune';

  public function getSourceName() {
    return pht('Phortune');
  }

  public function getSourceDescription() {
    return pht('Updates from subscriptions and payment processors.');
  }

}
