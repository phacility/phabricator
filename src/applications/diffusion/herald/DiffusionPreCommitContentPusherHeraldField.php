<?php

final class DiffusionPreCommitContentPusherHeraldField
  extends DiffusionPreCommitContentHeraldField {

  const FIELDCONST = 'diffusion.pre.content.pusher';

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
