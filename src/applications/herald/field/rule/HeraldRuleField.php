<?php

abstract class HeraldRuleField
  extends HeraldField {

  public function getFieldGroupKey() {
    return ManiphestTaskHeraldFieldGroup::FIELDGROUPKEY;
  }

  public function supportsObject($object) {
    return ($object instanceof HeraldRule);
  }

}
