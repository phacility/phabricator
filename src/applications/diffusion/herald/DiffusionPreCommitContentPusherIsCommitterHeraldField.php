<?php

final class DiffusionPreCommitContentPusherIsCommitterHeraldField
  extends DiffusionPreCommitContentHeraldField {

  const FIELDCONST = 'diffusion.pre.content.pusher.is-committer';

  public function getHeraldFieldName() {
    return pht('Pusher is committer');
  }

  public function getHeraldFieldValue($object) {
    $pusher = $this->getAdapter()->getHookEngine()->getViewer()->getPHID();
    $committer = $this->getAdapter()->getCommitterPHID();

    return ($pusher === $committer);
  }

  protected function getHeraldFieldStandardType() {
    return HeraldField::STANDARD_BOOL;
  }

}
