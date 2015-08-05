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

  protected function getHeraldFieldStandardType() {
    return HeraldField::STANDARD_PHID;
  }

  protected function getDatasource() {
    return new PhabricatorPeopleDatasource();
  }

}
