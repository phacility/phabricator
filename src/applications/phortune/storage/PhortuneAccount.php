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

  private $memberPHIDs = self::ATTACHABLE;

  public static function initializeNewAccount(PhabricatorUser $actor) {
    return id(new self())
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
    return '/phortune/'.$this->getID().'/';
  }


/* -(  PhabricatorApplicationTransactionInterface  )------------------------- */


  public function getApplicationTransactionEditor() {
    return new PhortuneAccountEditor();
  }

  public function getApplicationTransactionObject() {
    return $this;
  }

  public function getApplicationTransactionTemplate() {
    return new PhortuneAccountTransaction();
  }

  public function willRenderTimeline(
    PhabricatorApplicationTransactionView $timeline,
    AphrontRequest $request) {

    return $timeline;
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
    $members = array_fuse($this->getMemberPHIDs());
    if (isset($members[$viewer->getPHID()])) {
      return true;
    }

    // If the viewer is acting on behalf of a merchant, they can see
    // payment accounts.
    if ($capability == PhabricatorPolicyCapability::CAN_VIEW) {
      foreach ($viewer->getAuthorities() as $authority) {
        if ($authority instanceof PhortuneMerchant) {
          return true;
        }
      }
    }

    return false;
  }

  public function describeAutomaticCapability($capability) {
    return pht('Members of an account can always view and edit it.');
  }


}
