<?php

final class PhabricatorToken extends PhabricatorTokenDAO
  implements PhabricatorPolicyInterface {

  protected $phid;
  protected $name;
  protected $filePHID;

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
      self::CONFIG_NO_TABLE => true,
    ) + parent::getConfiguration();
  }

  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return PhabricatorPolicies::getMostOpenPolicy();
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

  public function describeAutomaticCapability($capability) {
    return null;
  }

  public function renderIcon() {
    // TODO: Maybe move to a View class?

    require_celerity_resource('sprite-tokens-css');
    require_celerity_resource('tokens-css');

    $sprite = substr($this->getPHID(), 10);

    return id(new PHUIIconView())
      ->setSpriteSheet(PHUIIconView::SPRITE_TOKENS)
      ->setSpriteIcon($sprite);

  }

}
