<?php

final class PhortuneCart extends PhortuneDAO {

  protected $accountPHID;
  protected $ownerPHID;
  protected $metadata;

  private $purchases = self::ATTACHABLE;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_SERIALIZATION => array(
        'metadata' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_CART);
  }

  public function attachPurchases(array $purchases) {
    assert_instances_of($purchases, 'PhortunePurchase');
    $this->purchases = $purchases;
    return $this;
  }

  public function getTotalInCents() {
    return 123;
  }

  public function getPurchases() {
    return $this->assertAttached($this->purchases);
  }

}
