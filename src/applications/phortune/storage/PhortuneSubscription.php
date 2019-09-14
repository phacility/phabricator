<?php

/**
 * A subscription bills users regularly.
 */
final class PhortuneSubscription
  extends PhortuneDAO
  implements
    PhabricatorPolicyInterface,
    PhabricatorExtendedPolicyInterface,
    PhabricatorPolicyCodexInterface,
    PhabricatorApplicationTransactionInterface {

  const STATUS_ACTIVE = 'active';
  const STATUS_CANCELLED = 'cancelled';

  protected $accountPHID;
  protected $merchantPHID;
  protected $triggerPHID;
  protected $authorPHID;
  protected $defaultPaymentMethodPHID;
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
        'defaultPaymentMethodPHID' => 'phid?',
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

  public function getPHIDType() {
    return PhortuneSubscriptionPHIDType::TYPECONST;
  }

  public static function initializeNewSubscription(
    PhortuneAccount $account,
    PhortuneMerchant $merchant,
    PhabricatorUser $author,
    PhortuneSubscriptionImplementation $implementation,
    PhabricatorTriggerClock $clock) {

    $trigger = id(new PhabricatorWorkerTrigger())
      ->setClock($clock);

    return id(new PhortuneSubscription())
      ->setStatus(self::STATUS_ACTIVE)
      ->setAccountPHID($account->getPHID())
      ->attachAccount($account)
      ->setMerchantPHID($merchant->getPHID())
      ->attachMerchant($merchant)
      ->setAuthorPHID($author->getPHID())
      ->setSubscriptionClass(get_class($implementation))
      ->setSubscriptionRef($implementation->getRef())
      ->attachImplementation($implementation)
      ->attachTrigger($trigger);
  }

  public function attachImplementation(
    PhortuneSubscriptionImplementation $impl) {
    $this->implementation = $impl;
    return $this;
  }

  public function getImplementation() {
    return $this->assertAttached($this->implementation);
  }

  public function attachAccount(PhortuneAccount $account) {
    $this->account = $account;
    return $this;
  }

  public function getAccount() {
    return $this->assertAttached($this->account);
  }

  public function attachMerchant(PhortuneMerchant $merchant) {
    $this->merchant = $merchant;
    return $this;
  }

  public function getMerchant() {
    return $this->assertAttached($this->merchant);
  }

  public function attachTrigger(PhabricatorWorkerTrigger $trigger) {
    $this->trigger = $trigger;
    return $this;
  }

  public function getTrigger() {
    return $this->assertAttached($this->trigger);
  }

  public function save() {
    $this->subscriptionClassKey = PhabricatorHash::digestForIndex(
      $this->subscriptionClass);

    $this->subscriptionRefKey = PhabricatorHash::digestForIndex(
      $this->subscriptionRef);

    $is_new = (!$this->getID());

    $this->openTransaction();

      // If we're saving this subscription for the first time, we're also
      // going to set up the trigger for it.
      if ($is_new) {
        $trigger_phid = PhabricatorPHID::generateNewPHID(
          PhabricatorWorkerTriggerPHIDType::TYPECONST);
        $this->setTriggerPHID($trigger_phid);
      }

      $result = parent::save();

      if ($is_new) {
        $trigger_action = new PhabricatorScheduleTaskTriggerAction(
          array(
            'class' => 'PhortuneSubscriptionWorker',
            'data' => array(
              'subscriptionPHID' => $this->getPHID(),
            ),
            'options' => array(
              'objectPHID' => $this->getPHID(),
              'priority' => PhabricatorWorker::PRIORITY_BULK,
            ),
          ));

        $trigger = $this->getTrigger();
        $trigger->setPHID($trigger_phid);
        $trigger->setAction($trigger_action);
        $trigger->save();
      }
    $this->saveTransaction();

    $account = $this->getAccount();
    $merchant = $this->getMerchant();
    $account->writeMerchantEdge($merchant);

    return $result;
  }

  public function getSubscriptionName() {
    return $this->getImplementation()->getName($this);
  }

  public function getSubscriptionFullName() {
    return $this->getImplementation()->getFullName($this);
  }

  public function getSubscriptionCrumbName() {
    return $this->getImplementation()->getCrumbName($this);
  }

  public function getCartName(PhortuneCart $cart) {
    return $this->getImplementation()->getCartName($this, $cart);
  }

  public function getURI() {
    return urisprintf(
      '/phortune/account/%d/subscriptions/%d/',
      $this->getAccount()->getID(),
      $this->getID());
  }

  public function getEditURI() {
    $account_id = $this->getAccount()->getID();
    $id = $this->getID();

    return "/phortune/{$account_id}/subscription/edit/{$id}/";
  }

  public function getMerchantURI() {
    $merchant_id = $this->getMerchant()->getID();
    $id = $this->getID();
    return "/phortune/merchant/{$merchant_id}/subscription/view/{$id}/";
  }

  public function getCostForBillingPeriodAsCurrency($start_epoch, $end_epoch) {
    return $this->getImplementation()->getCostForBillingPeriodAsCurrency(
      $this,
      $start_epoch,
      $end_epoch);
  }

  public function shouldInvoiceForBillingPeriod($start_epoch, $end_epoch) {
    return $this->getImplementation()->shouldInvoiceForBillingPeriod(
      $this,
      $start_epoch,
      $end_epoch);
  }

  public function getPurchaseName(
    PhortuneProduct $product,
    PhortunePurchase $purchase) {
    return $this->getImplementation()->getPurchaseName(
      $this,
      $product,
      $purchase);
  }

  public function getPurchaseURI(
    PhortuneProduct $product,
    PhortunePurchase $purchase) {
    return $this->getImplementation()->getPurchaseURI(
      $this,
      $product,
      $purchase);
  }

  public function didPurchaseProduct(
    PhortuneProduct $product,
    PhortunePurchase $purchase) {
    return $this->getImplementation()->didPurchaseProduct(
      $this,
      $product,
      $purchase);
  }

/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhortuneSubscriptionEditor();
  }

  public function getApplicationTransactionTemplate() {
    return new PhortuneSubscriptionTransaction();
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
    // See T13366. If you can edit the merchant associated with this
    // subscription, you can view the subscription.
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

    // See T13366. For blanket view and edit permissions on all subscriptions,
    // you must be able to edit the associated account.
    return array(
      array(
        $this->getAccount(),
        PhabricatorPolicyCapability::CAN_EDIT,
      ),
    );
  }


/* -(  PhabricatorPolicyCodexInterface  )------------------------------------ */


  public function newPolicyCodex() {
    return new PhortuneSubscriptionPolicyCodex();
  }

}
