<?php

final class PhortunePaymentProviderTestCase extends PhabricatorTestCase {

  public function getPhabricatorTestCaseConfiguration() {
    return array(
      self::PHABRICATOR_TESTCONFIG_BUILD_STORAGE_FIXTURES => true,
    );
  }

  public function testNoPaymentProvider() {
    $env = PhabricatorEnv::beginScopedEnv();
    $env->overrideEnvConfig('phortune.test.enabled', true);

    $method = id(new PhortunePaymentMethod())
      ->setMetadataValue('type', 'hugs');

    $caught = null;
    try {
      $provider = $method->buildPaymentProvider();
    } catch (Exception $ex) {
      $caught = $ex;
    }

    $this->assertEqual(
      true,
      ($caught instanceof PhortuneNoPaymentProviderException),
      'No provider should accept hugs; they are not a currency.');
  }

  public function testMultiplePaymentProviders() {
    $env = PhabricatorEnv::beginScopedEnv();
    $env->overrideEnvConfig('phortune.test.enabled', true);

   $method = id(new PhortunePaymentMethod())
    ->setMetadataValue('type', 'test.multiple');

    $caught = null;
    try {
      $provider = $method->buildPaymentProvider();
    } catch (Exception $ex) {
      $caught = $ex;
    }

    $this->assertEqual(
      true,
      ($caught instanceof PhortuneMultiplePaymentProvidersException),
      'Expect exception when more than one provider handles a payment method.');
  }



}
