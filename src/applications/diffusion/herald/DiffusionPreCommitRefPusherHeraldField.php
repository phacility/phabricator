<?php

final class DiffusionPreCommitRefPusherHeraldField
  extends DiffusionPreCommitRefHeraldField {

  const FIELDCONST = 'diffusion.pre.ref.pusher';

  public function getHeraldFieldName() {
    return pht('Pusher');
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()->getHookEngine()->getViewer()->getPHID();
  }

  protected function getHeraldFieldStandardConditions() {
    return HeraldField::STANDARD_PHID;
  }

  public function getHeraldFieldValueType($condition) {
    return HeraldAdapter::VALUE_USER;
  }

}
