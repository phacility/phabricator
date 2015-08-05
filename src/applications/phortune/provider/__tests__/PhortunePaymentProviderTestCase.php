<?php

final class PhortunePaymentProviderTestCase extends PhabricatorTestCase {

  public function testGetAllProviders() {
    PhortunePaymentProvider::getAllProviders();
    $this->assertTrue(true);
  }

}
