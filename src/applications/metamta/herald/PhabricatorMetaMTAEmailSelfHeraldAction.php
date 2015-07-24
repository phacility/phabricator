<?php

final class PhabricatorMetaMTAEmailSelfHeraldAction
  extends PhabricatorMetaMTAEmailHeraldAction {

  const ACTIONCONST = 'email.self';

  public function getHeraldActionName() {
    return pht('Send me an email');
  }

  public function supportsRuleType($rule_type) {
    return ($rule_type == HeraldRuleTypeConfig::RULE_TYPE_PERSONAL);
  }

  public function applyEffect($object, HeraldEffect $effect) {
    $phid = $effect->getRule()->getAuthorPHID();

    // For personal rules, we'll force delivery of a real email. This effect
    // is stronger than notification preferences, so you get an actual email
    // even if your preferences are set to "Notify" or "Ignore".

    return $this->applyEmail(array($phid), $force = true);
  }

  public function getHeraldActionStandardType() {
    return self::STANDARD_NONE;
  }

  public function renderActionDescription($value) {
    return pht('Send an email to rule author.');
  }

}
