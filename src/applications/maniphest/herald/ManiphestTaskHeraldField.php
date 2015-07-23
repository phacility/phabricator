<?php

abstract class ManiphestTaskHeraldField extends HeraldField {

  public function getFieldGroupKey() {
    return ManiphestTaskHeraldFieldGroup::FIELDGROUPKEY;
  }

  public function supportsObject($object) {
    return ($object instanceof ManiphestTask);
  }

}
