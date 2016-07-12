<?php

abstract class PhabricatorMailEmailHeraldField
  extends HeraldField {

  public function supportsObject($object) {
    return ($object instanceof PhabricatorMetaMTAMail);
  }

  public function getFieldGroupKey() {
    return PhabricatorMailEmailHeraldFieldGroup::FIELDGROUPKEY;
  }

}
