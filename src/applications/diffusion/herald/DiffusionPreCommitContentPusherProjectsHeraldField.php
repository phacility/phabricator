<?php

final class DiffusionPreCommitContentPusherProjectsHeraldField
  extends DiffusionPreCommitContentHeraldField {

  const FIELDCONST = 'diffusion.pre.content.pusher.projects';

  public function getHeraldFieldName() {
    return pht('Pusher projects');
  }

  public function getHeraldFieldValue($object) {
    return $this->getAdapter()
      ->getHookEngine()
      ->loadViewerProjectPHIDsForHerald();
  }

  protected function getHeraldFieldStandardConditions() {
    return HeraldField::STANDARD_LIST;
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
