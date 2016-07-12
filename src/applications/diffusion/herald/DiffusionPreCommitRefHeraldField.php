<?php

abstract class DiffusionPreCommitRefHeraldField extends HeraldField {

  public function supportsObject($object) {
    if (!($object instanceof PhabricatorRepositoryPushLog)) {
      return false;
    }

    if (!$this->getAdapter()->isPreCommitRefAdapter()) {
      return false;
    }

    return true;
  }

  public function getFieldGroupKey() {
    return DiffusionPreCommitRefHeraldFieldGroup::FIELDGROUPKEY;
  }

}
