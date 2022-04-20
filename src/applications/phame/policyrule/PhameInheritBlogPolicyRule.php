<?php

final class PhameInheritBlogPolicyRule
  extends PhabricatorPolicyRule {

  public function getObjectPolicyKey() {
    return 'phame.blog';
  }

  public function getObjectPolicyName() {
    return pht('Same as Blog');
  }

  public function getPolicyExplanation() {
    return pht('Use the same policy as the parent blog.');
  }

  public function getRuleDescription() {
    return pht('inherit from blog');
  }

  public function getObjectPolicyIcon() {
    return 'fa-feed';
  }

  public function canApplyToObject(PhabricatorPolicyInterface $object) {
    return ($object instanceof PhamePost);
  }

  public function applyRule(
    PhabricatorUser $viewer,
    $value,
    PhabricatorPolicyInterface $object) {

    // TODO: This is incorrect in the general case, but: "PolicyRule" currently
    // does not know which capability it is evaluating (so we can't test for
    // the correct capability); and "PhamePost" currently has immutable view
    // and edit policies (so we can only arrive here when evaluating the
    // interact policy).

    return PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $object->getBlog(),
      PhabricatorPolicyCapability::CAN_INTERACT);
  }

  public function getValueControlType() {
    return self::CONTROL_TYPE_NONE;
  }

}
