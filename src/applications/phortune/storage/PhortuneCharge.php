<?php

/**
 * A charge is a charge (or credit) against an account and represents an actual
 * transfer of funds. Each charge is normally associated with a cart, but a
 * cart may have multiple charges. For example, a product may have a failed
 * charge followed by a successful charge.
 */
final class PhortuneCharge extends PhortuneDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorExtendedPolicyInterface {

  const STATUS_CHARGING   = 'charge:charging';
  const STATUS_CHARGED    = 'charge:charged';
  const STATUS_HOLD       = 'charge:hold';
  const STATUS_FAILED     = 'charge:failed';

  protected $accountPHID;
  protected $authorPHID;
  protected $cartPHID;
  protected $providerPHID;
  protected $merchantPHID;
  protected $paymentMethodPHID;
  protected $amountAsCurrency;
  protected $amountRefundedAsCurrency;
  protected $refundedChargePHID;
  protected $refundingPHID;
  protected $status;
  protected $metadata = array();

  private $account = self::ATTACHABLE;
  private $cart = self::ATTACHABLE;

  public static function initializeNewCharge() {
    return id(new PhortuneCharge())
      ->setStatus(self::STATUS_CHARGING)
      ->setAmountRefundedAsCurrency(PhortuneCurrency::newEmptyCurrency());
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'metadata' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_APPLICATION_SERIALIZERS => array(
        'amountAsCurrency' => new PhortuneCurrencySerializer(),
        'amountRefundedAsCurrency' => new PhortuneCurrencySerializer(),
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'paymentMethodPHID' => 'phid?',
        'refundedChargePHID' => 'phid?',
        'refundingPHID' => 'phid?',
        'amountAsCurrency' => 'text64',
        'amountRefundedAsCurrency' => 'text64',
        'status' => 'text32',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_cart' => array(
          'columns' => array('cartPHID'),
        ),
        'key_account' => array(
          'columns' => array('accountPHID'),
        ),
        'key_merchant' => array(
          'columns' => array('merchantPHID'),
        ),
        'key_provider' => array(
          'columns' => array('providerPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public static function getStatusNameMap() {
    return array(
      self::STATUS_CHARGING => pht('Charging'),
      self::STATUS_CHARGED => pht('Charged'),
      self::STATUS_HOLD => pht('Hold'),
      self::STATUS_FAILED => pht('Failed'),
    );
  }

  public static function getNameForStatus($status) {
    return idx(self::getStatusNameMap(), $status, pht('Unknown'));
  }

  public function isRefund() {
    return $this->getAmountAsCurrency()->negate()->isPositive();
  }

  public function getStatusForDisplay() {
    if ($this->getStatus() == self::STATUS_CHARGED) {
      if ($this->getRefundedChargePHID()) {
        return pht('Refund');
      }

      $refunded = $this->getAmountRefundedAsCurrency();

      if ($refunded->isPositive()) {
        if ($refunded->isEqualTo($this->getAmountAsCurrency())) {
          return pht('Fully Refunded');
        } else {
          return pht('%s Refunded', $refunded->formatForDisplay());
        }
      }
    }

    return self::getNameForStatus($this->getStatus());
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhortuneChargePHIDType::TYPECONST);
  }

  public function getMetadataValue($key, $default = null) {
    return idx($this->metadata, $key, $default);
  }

  public function setMetadataValue($key, $value) {
    $this->metadata[$key] = $value;
    return $this;
  }

  public function getAccount() {
    return $this->assertAttached($this->account);
  }

  public function attachAccount(PhortuneAccount $account) {
    $this->account = $account;
    return $this;
  }

  public function getCart() {
    return $this->assertAttached($this->cart);
  }

  public function attachCart(PhortuneCart $cart = null) {
    $this->cart = $cart;
    return $this;
  }

  public function getAmountRefundableAsCurrency() {
    $amount = $this->getAmountAsCurrency();
    $refunded = $this->getAmountRefundedAsCurrency();

    // We can't refund negative amounts of money, since it does not make
    // sense and is not possible in the various payment APIs.

    $refundable = $amount->subtract($refunded);
    if ($refundable->isPositive()) {
      return $refundable;
    } else {
      return PhortuneCurrency::newEmptyCurrency();
    }
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    return PhabricatorPolicies::getMostOpenPolicy();
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    if ($capability === PhabricatorPolicyCapability::CAN_VIEW) {
      $any_edit = PhortuneMerchantQuery::canViewersEditMerchants(
        array($viewer->getPHID()),
        array($this->getMerchantPHID()));
      if ($any_edit) {
        return true;
      }
    }

    return false;
  }


/* -(  PhabricatorExtendedPolicyInterface  )--------------------------------- */


  public function getExtendedPolicy($capability, PhabricatorUser $viewer) {
    if ($this->hasAutomaticCapability($capability, $viewer)) {
      return array();
    }

    return array(
      array(
        $this->getAccount(),
        PhabricatorPolicyCapability::CAN_EDIT,
      ),
    );
  }

}
