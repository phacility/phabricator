<?php

/**
 * An account represents a purchasing entity. An account may have multiple users
 * on it (e.g., several employees of a company have access to the company
 * account), and a user may have several accounts (e.g., a company account and
 * a personal account).
 */
final class PhortuneAccount extends PhortuneDAO
  implements
    PhabricatorApplicationTransactionInterface,
    PhabricatorPolicyInterface {

  protected $name;
  protected $billingName;
  protected $billingAddress;

  private $memberPHIDs = self::ATTACHABLE;
  private $merchantPHIDs = self::ATTACHABLE;

  public static function initializeNewAccount(PhabricatorUser $actor) {
    return id(new self())
      ->setBillingName('')
      ->setBillingAddress('')
      ->attachMerchantPHIDs(array())
      ->attachMemberPHIDs(array());
  }

  public static function createNewAccount(
    PhabricatorUser $actor,
    PhabricatorContentSource $content_source) {

    $account = self::initializeNewAccount($actor);

    $xactions = array();
    $xactions[] = id(new PhortuneAccountTransaction())
      ->setTransactionType(PhortuneAccountNameTransaction::TRANSACTIONTYPE)
      ->setNewValue(pht('Default Account'));

    $xactions[] = id(new PhortuneAccountTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
      ->setMetadataValue(
        'edge:type',
        PhortuneAccountHasMemberEdgeType::EDGECONST)
      ->setNewValue(
        array(
          '=' => array($actor->getPHID() => $actor->getPHID()),
        ));

    $editor = id(new PhortuneAccountEditor())
      ->setActor($actor)
      ->setContentSource($content_source);

    // We create an account for you the first time you visit Phortune.
    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

      $editor->applyTransactions($account, $xactions);

    unset($unguarded);

    return $account;
  }

  public function newCart(
    PhabricatorUser $actor,
    PhortuneCartImplementation $implementation,
    PhortuneMerchant $merchant) {

    $cart = PhortuneCart::initializeNewCart($actor, $this, $merchant);

    $cart->setCartClass(get_class($implementation));
    $cart->attachImplementation($implementation);

    $implementation->willCreateCart($actor, $cart);

    return $cart->save();
  }

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_COLUMN_SCHEMA => array(
        'name' => 'text255',
        'billingName' => 'text255',
        'billingAddress' => 'text',
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhortuneAccountPHIDType::TYPECONST);
  }

  public function getMemberPHIDs() {
    return $this->assertAttached($this->memberPHIDs);
  }

  public function attachMemberPHIDs(array $phids) {
    $this->memberPHIDs = $phids;
    return $this;
  }

  public function getURI() {
    return urisprintf(
      '/phortune/account/%d/',
      $this->getID());
  }

  public function getDetailsURI() {
    return urisprintf(
      '/phortune/account/%d/details/',
      $this->getID());
  }

  public function getOrdersURI() {
    return urisprintf(
      '/phortune/account/%d/orders/',
      $this->getID());
  }

  public function getOrderListURI($path = '') {
    return urisprintf(
      '/phortune/account/%d/orders/list/%s',
      $this->getID(),
      $path);
  }

  public function getSubscriptionsURI() {
    return urisprintf(
      '/phortune/account/%d/subscriptions/',
      $this->getID());
  }

  public function getEmailAddressesURI() {
    return urisprintf(
      '/phortune/account/%d/addresses/',
      $this->getID());
  }

  public function getPaymentMethodsURI() {
    return urisprintf(
      '/phortune/account/%d/methods/',
      $this->getID());
  }

  public function getChargesURI() {
    return urisprintf(
      '/phortune/account/%d/charges/',
      $this->getID());
  }

  public function getChargeListURI($path = '') {
    return urisprintf(
      '/phortune/account/%d/charges/list/%s',
      $this->getID(),
      $path);
  }

  public function attachMerchantPHIDs(array $merchant_phids) {
    $this->merchantPHIDs = $merchant_phids;
    return $this;
  }

  public function getMerchantPHIDs() {
    return $this->assertAttached($this->merchantPHIDs);
  }

  public function writeMerchantEdge(PhortuneMerchant $merchant) {
    $edge_src = $this->getPHID();
    $edge_type = PhortuneAccountHasMerchantEdgeType::EDGECONST;
    $edge_dst = $merchant->getPHID();

    id(new PhabricatorEdgeEditor())
      ->addEdge($edge_src, $edge_type, $edge_dst)
      ->save();

    return $this;
  }

  public function isUserAccountMember(PhabricatorUser $user) {
    $user_phid = $user->getPHID();
    if (!$user_phid) {
      return null;
    }

    $member_map = array_fuse($this->getMemberPHIDs());

    return isset($member_map[$user_phid]);
  }

/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhortuneAccountEditor();
  }

  public function getApplicationTransactionTemplate() {
    return new PhortuneAccountTransaction();
  }


/* -(  PhabricatorPolicyInterface  )----------------------------------------- */


  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
      PhabricatorPolicyCapability::CAN_EDIT,
    );
  }

  public function getPolicy($capability) {
    switch ($capability) {
      case PhabricatorPolicyCapability::CAN_VIEW:
      case PhabricatorPolicyCapability::CAN_EDIT:
        if ($this->getPHID() === null) {
          // Allow a user to create an account for themselves.
          return PhabricatorPolicies::POLICY_USER;
        } else {
          return PhabricatorPolicies::POLICY_NOONE;
        }
    }
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    if ($this->isUserAccountMember($viewer)) {
      return true;
    }

    // See T13366. If the viewer can edit any merchant that this payment
    // account has a relationship with, they can see the payment account.
    if ($capability == PhabricatorPolicyCapability::CAN_VIEW) {
      $viewer_phids = array($viewer->getPHID());
      $merchant_phids = $this->getMerchantPHIDs();

      $any_edit = PhortuneMerchantQuery::canViewersEditMerchants(
        $viewer_phids,
        $merchant_phids);

      if ($any_edit) {
        return true;
      }
    }

    return false;
  }

  public function describeAutomaticCapability($capability) {
    return array(
      pht('Members of an account can always view and edit it.'),
      pht('Merchants an account has established a relationship can view it.'),
    );
  }


}
