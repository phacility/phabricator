<?php

final class PhortuneBalancedPaymentProvider extends PhortunePaymentProvider {

  const BALANCED_MARKETPLACE_ID   = 'balanced.marketplace-id';
  const BALANCED_SECRET_KEY       = 'balanced.secret-key';

  public function isAcceptingLivePayments() {
    return !preg_match('/-test-/', $this->getSecretKey());
  }

  public function getName() {
    return pht('Balanced Payments');
  }

  public function getConfigureName() {
    return pht('Add Balanced Payments Account');
  }

  public function getConfigureDescription() {
    return pht(
      'Allows you to accept credit or debit card payments with a '.
      'balancedpayments.com account.');
  }

  public function getConfigureProvidesDescription() {
    return pht(
      'This merchant accepts credit and debit cards via Balanced Payments.');
  }

  public function getConfigureInstructions() {
    return pht(
      "To configure Balacned, register or log in to an existing account on ".
      "[[https://balancedpayments.com | balancedpayments.com]]. Once logged ".
      "in:\n\n".
      "  - Choose a marketplace.\n".
      "  - Find the **Marketplace ID** in {nav My Marketplace > Settings} and ".
      "    copy it into the field above.\n".
      "  - On the same screen, under **API keys**, choose **Add a key**, then ".
      "    **Show key secret**. Copy the value into the field above.\n\n".
      "You can either use a test marketplace to add this provider in test ".
      "mode, or use a live marketplace to accept live payments.");
  }

  public function getAllConfigurableProperties() {
    return array(
      self::BALANCED_MARKETPLACE_ID,
      self::BALANCED_SECRET_KEY,
    );
  }

  public function getAllConfigurableSecretProperties() {
    return array(
      self::BALANCED_SECRET_KEY,
    );
  }

  public function processEditForm(
    AphrontRequest $request,
    array $values) {

    $errors = array();
    $issues = array();

    if (!strlen($values[self::BALANCED_MARKETPLACE_ID])) {
      $errors[] = pht('Balanced Marketplace ID is required.');
      $issues[self::BALANCED_MARKETPLACE_ID] = pht('Required');
    }

    if (!strlen($values[self::BALANCED_SECRET_KEY])) {
      $errors[] = pht('Balanced Secret Key is required.');
      $issues[self::BALANCED_SECRET_KEY] = pht('Required');
    }

    return array($errors, $issues, $values);
  }

  public function extendEditForm(
    AphrontRequest $request,
    AphrontFormView $form,
    array $values,
    array $issues) {

    $form
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName(self::BALANCED_MARKETPLACE_ID)
          ->setValue($values[self::BALANCED_MARKETPLACE_ID])
          ->setError(idx($issues, self::BALANCED_MARKETPLACE_ID, true))
          ->setLabel(pht('Balanced Marketplace ID')))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName(self::BALANCED_SECRET_KEY)
          ->setValue($values[self::BALANCED_SECRET_KEY])
          ->setError(idx($issues, self::BALANCED_SECRET_KEY, true))
          ->setLabel(pht('Balanced Secret Key')));

  }

  public function canRunConfigurationTest() {
    return true;
  }

  public function runConfigurationTest() {
    $this->loadBalancedAPILibraries();

    // TODO: This only tests that the secret key is correct. It's not clear
    // how to test that the marketplace is correct.

    try {
      Balanced\Settings::$api_key = $this->getSecretKey();
      Balanced\APIKey::query()->first();
    } catch (RESTful\Exceptions\HTTPError $error) {
      // NOTE: This exception doesn't print anything meaningful if it escapes
      // to top level. Replace it with something slightly readable.
      throw new Exception($error->response->body->description);
    }
  }

  public function getPaymentMethodDescription() {
    return pht('Add Credit or Debit Card');
  }

  public function getPaymentMethodIcon() {
    return 'Balanced';
  }

  public function getPaymentMethodProviderDescription() {
    return pht('Processed by Balanced');
  }

  public function getDefaultPaymentMethodDisplayName(
    PhortunePaymentMethod $method) {
    return pht('Credit/Debit Card');
  }

  protected function executeCharge(
    PhortunePaymentMethod $method,
    PhortuneCharge $charge) {
    $this->loadBalancedAPILibraries();

    $price = $charge->getAmountAsCurrency();

    // Build the string which will appear on the credit card statement.
    $charge_as = new PhutilURI(PhabricatorEnv::getProductionURI('/'));
    $charge_as = $charge_as->getDomain();
    $charge_as = id(new PhutilUTF8StringTruncator())
      ->setMaximumBytes(22)
      ->setTerminator('')
      ->truncateString($charge_as);

    try {
      Balanced\Settings::$api_key = $this->getSecretKey();
      $card = Balanced\Card::get($method->getMetadataValue('balanced.cardURI'));
      $debit = $card->debit($price->getValueInUSDCents(), $charge_as);
    } catch (RESTful\Exceptions\HTTPError $error) {
      // NOTE: This exception doesn't print anything meaningful if it escapes
      // to top level. Replace it with something slightly readable.
      throw new Exception($error->response->body->description);
    }

    $expect_status = 'succeeded';
    if ($debit->status !== $expect_status) {
      throw new Exception(
        pht(
          'Debit failed, expected "%s", got "%s".',
          $expect_status,
          $debit->status));
    }

    $charge->setMetadataValue('balanced.debitURI', $debit->uri);
    $charge->save();
  }

  protected function executeRefund(
    PhortuneCharge $charge,
    PhortuneCharge $refund) {
    $this->loadBalancedAPILibraries();

    $debit_uri = $charge->getMetadataValue('balanced.debitURI');
    if (!$debit_uri) {
      throw new Exception(pht('No Balanced debit URI!'));
    }

    $refund_cents = $refund
      ->getAmountAsCurrency()
      ->negate()
      ->getValueInUSDCents();

    $params = array(
      'amount' => $refund_cents,
    );

    try {
      Balanced\Settings::$api_key = $this->getSecretKey();
      $balanced_debit = Balanced\Debit::get($debit_uri);
      $balanced_refund = $balanced_debit->refunds->create($params);
    } catch (RESTful\Exceptions\HTTPError $error) {
      throw new Exception($error->response->body->description);
    }

    $refund->setMetadataValue('balanced.refundURI', $balanced_refund->uri);
    $refund->save();
  }

  public function updateCharge(PhortuneCharge $charge) {
    $this->loadBalancedAPILibraries();

    $debit_uri = $charge->getMetadataValue('balanced.debitURI');
    if (!$debit_uri) {
      throw new Exception(pht('No Balanced debit URI!'));
    }

    try {
      Balanced\Settings::$api_key = $this->getSecretKey();
      $balanced_debit = Balanced\Debit::get($debit_uri);
    } catch (RESTful\Exceptions\HTTPError $error) {
      throw new Exception($error->response->body->description);
    }

    // TODO: Deal with disputes / chargebacks / surprising refunds.
  }

  private function getMarketplaceID() {
    return $this
      ->getProviderConfig()
      ->getMetadataValue(self::BALANCED_MARKETPLACE_ID);
  }

  private function getSecretKey() {
    return $this
      ->getProviderConfig()
      ->getMetadataValue(self::BALANCED_SECRET_KEY);
  }

  private function getMarketplaceURI() {
    return '/v1/marketplaces/'.$this->getMarketplaceID();
  }


/* -(  Adding Payment Methods  )--------------------------------------------- */


  public function canCreatePaymentMethods() {
    return true;
  }

  public function validateCreatePaymentMethodToken(array $token) {
    return isset($token['balancedMarketplaceURI']);
  }


  /**
   * @phutil-external-symbol class Balanced\Card
   * @phutil-external-symbol class Balanced\Debit
   * @phutil-external-symbol class Balanced\Settings
   * @phutil-external-symbol class Balanced\Marketplace
   * @phutil-external-symbol class Balanced\APIKey
   * @phutil-external-symbol class RESTful\Exceptions\HTTPError
   */
  public function createPaymentMethodFromRequest(
    AphrontRequest $request,
    PhortunePaymentMethod $method,
    array $token) {
    $this->loadBalancedAPILibraries();

    $errors = array();

    $account_phid = $method->getAccountPHID();
    $author_phid = $method->getAuthorPHID();
    $description = $account_phid.':'.$author_phid;

    try {
      Balanced\Settings::$api_key = $this->getSecretKey();

      $card = Balanced\Card::get($token['balancedMarketplaceURI']);

      $buyer = Balanced\Marketplace::mine()->createBuyer(
        null,
        $card->uri,
        array(
          'description' => $description,
        ));

    } catch (RESTful\Exceptions\HTTPError $error) {
      // NOTE: This exception doesn't print anything meaningful if it escapes
      // to top level. Replace it with something slightly readable.
      throw new Exception($error->response->body->description);
    }

    $method
      ->setBrand($card->brand)
      ->setLastFourDigits($card->last_four)
      ->setExpires($card->expiration_year, $card->expiration_month)
      ->setMetadata(
        array(
          'type' => 'balanced.account',
          'balanced.accountURI' => $buyer->uri,
          'balanced.cardURI' => $card->uri,
        ));

    return $errors;
  }

  public function renderCreatePaymentMethodForm(
    AphrontRequest $request,
    array $errors) {

    $ccform = id(new PhortuneCreditCardForm())
      ->setUser($request->getUser())
      ->setErrors($errors)
      ->addScript('https://js.balancedpayments.com/v1/balanced.js');

    Javelin::initBehavior(
      'balanced-payment-form',
      array(
        'balancedMarketplaceURI' => $this->getMarketplaceURI(),
        'formID'                 => $ccform->getFormID(),
      ));

    return $ccform->buildForm();
  }

  private function getBalancedShortErrorCode($error_code) {
    $prefix = 'cc:balanced:';
    if (strncmp($error_code, $prefix, strlen($prefix))) {
      return null;
    }
    return substr($error_code, strlen($prefix));
  }

  public function translateCreatePaymentMethodErrorCode($error_code) {
    $short_code = $this->getBalancedShortErrorCode($error_code);

    if ($short_code) {
      static $map = array(
      );

      if (isset($map[$short_code])) {
        return $map[$short_code];
      }
    }

    return $error_code;
  }

  public function getCreatePaymentMethodErrorMessage($error_code) {
    $short_code = $this->getBalancedShortErrorCode($error_code);
    if (!$short_code) {
      return null;
    }

    switch ($short_code) {

      default:
        break;
    }


    return null;
  }

  private function loadBalancedAPILibraries() {
    $root = dirname(phutil_get_library_root('phabricator'));
    require_once $root.'/externals/httpful/bootstrap.php';
    require_once $root.'/externals/restful/bootstrap.php';
    require_once $root.'/externals/balanced-php/bootstrap.php';
  }

}
