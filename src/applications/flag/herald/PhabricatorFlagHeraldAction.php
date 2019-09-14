<?php

abstract class PhabricatorFlagHeraldAction
  extends HeraldAction {

  public function getActionGroupKey() {
    return HeraldSupportActionGroup::ACTIONGROUPKEY;
  }

  public function supportsObject($object) {
    return ($object instanceof PhabricatorFlaggableInterface);
  }

  public function supportsRuleType($rule_type) {
    return ($rule_type === HeraldRuleTypeConfig::RULE_TYPE_PERSONAL);
  }

}
