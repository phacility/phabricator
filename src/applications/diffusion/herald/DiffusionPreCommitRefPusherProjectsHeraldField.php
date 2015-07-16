<?php

final class DiffusionPreCommitRefPusherProjectsHeraldField
  extends DiffusionPreCommitRefHeraldField {

  const FIELDCONST = 'diffusion.pre.ref.pusher.projects';

  public function getHeraldFieldName() {
    return pht('Pusher projects');
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()
      ->getHookEngine()
      ->loadViewerProjectPHIDsForHerald();
  }

  protected function getHeraldFieldStandardType() {
    return HeraldField::STANDARD_PHID_LIST;
  }

  public function getHeraldFieldValueType($condition) {
    switch ($condition) {
      case HeraldAdapter::CONDITION_EXISTS:
      case HeraldAdapter::CONDITION_NOT_EXISTS:
        return HeraldAdapter::VALUE_NONE;
      default:
        return HeraldAdapter::VALUE_PROJECT;
    }
  }
}
