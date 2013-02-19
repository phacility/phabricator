<?php

final class PhabricatorToken extends PhabricatorTokenDAO
  implements PhabricatorPolicyInterface {

  protected $phid;
  protected $name;
  protected $filePHID;

  public function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID => true,
    ) + parent::getConfiguration();
  }

  public function getCapabilities() {
    return array(
      PhabricatorPolicyCapability::CAN_VIEW,
    );
  }

  public function getPolicy($capability) {
    return PhabricatorPolicies::POLICY_USER;
  }

  public function hasAutomaticCapability($capability, PhabricatorUser $viewer) {
    return false;
  }

  public function renderIcon() {
    // TODO: Maybe move to a View class?

    require_celerity_resource('sprite-tokens-css');
    require_celerity_resource('tokens-css');

    $sprite = substr($this->getPHID(), 10);

    return phutil_tag(
      'div',
      array(
        'class' => 'sprite-tokens token-icon token-'.$sprite,
      ),
      '');
  }

}
