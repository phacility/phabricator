<?php

/**
 * A subscription bills users regularly.
 */
final class PhortuneSubscription extends PhortuneDAO
  implements PhabricatorPolicyInterface {

  const STATUS_ACTIVE = 'active';
  const STATUS_CANCELLED = 'cancelled';

  protected $accountPHID;
  protected $merchantPHID;
  protected $triggerPHID;
  protected $authorPHID;
  protected $subscriptionClassKey;
  protected $subscriptionClass;
  protected $subscriptionRefKey;
  protected $subscriptionRef;
  protected $status;
  protected $metadata = array();

  private $merchant = self::ATTACHABLE;
  private $account = self::ATTACHABLE;
  private $implementation = self::ATTACHABLE;
  private $trigger = self::ATTACHABLE;

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'metadata' => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'subscriptionClassKey' => 'bytes12',
        'subscriptionClass' => 'text128',
        'subscriptionRefKey' => 'bytes12',
        'subscriptionRef' => 'text128',
        'status' => 'text32',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_subscription' => array(
          'columns' => array('subscriptionClassKey', 'subscriptionRefKey'),
          'unique' => true,
        ),
        'key_account' => array(
          'columns' => array('accountPHID'),
        ),
        'key_merchant' => array(
          'columns' => array('merchantPHID'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhortuneSubscriptionPHIDType::TYPECONST);
  }

  public static function initializeNewSubscription() {
    return id(new PhortuneSubscription());
  }

  public function attachImplementation(
    PhortuneSubscriptionImplementation $impl) {
    $this->implementation = $impl;
  }

  public function getImplementation() {
    return $this->assertAttached($this->implementation);
  }

  public function save() {
    $this->subscriptionClassKey = PhabricatorHash::digestForIndex(
      $this->subscriptionClass);

    $this->subscriptionRefKey = PhabricatorHash::digestForIndex(
      $this->subscriptionRef);

    return parent::save();
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    // NOTE: Both view and edit use the account's edit policy. We punch a hole
    // through this for merchants, below.
    return $this
      ->getAccount()
      ->getPolicy(PhabricatorPolicyCapability::CAN_EDIT);
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    if ($this->getAccount()->hasAutomaticCapability($capability, $viewer)) {
      return true;
    }

    // If the viewer controls the merchant this subscription bills to, they can
    // view the subscription.
    if ($capability == PhabricatorPolicyCapability::CAN_VIEW) {
      $can_admin = PhabricatorPolicyFilter::hasCapability(
        $viewer,
        $this->getMerchant(),
        PhabricatorPolicyCapability::CAN_EDIT);
      if ($can_admin) {
        return true;
      }
    }

    return false;
  }

  public function describeAutomaticCapability($capability) {
    return array(
      pht('Subscriptions inherit the policies of the associated account.'),
      pht(
        'The merchant you are subscribed with can review and manage the '.
        'subscription.'),
    );
  }
}
