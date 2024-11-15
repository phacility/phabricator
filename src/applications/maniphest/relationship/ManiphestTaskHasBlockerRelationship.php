<?php

final class ManiphestTaskHasBlockerRelationship
  extends ManiphestTaskRelationship {

  const RELATIONSHIPKEY = 'task.has-blocker';

  public function getEdgeConstant() {
    return ManiphestTaskBlockerEdgeType::EDGECONST;
  }

  protected function getActionName() {
    return pht('Blocked by.. (Edit)');
  }

  protected function getActionIcon() {
    return 'fa-chevron-circle-down';
  }

  public function canRelateObjects($src, $dst) {
    return ($dst instanceof ManiphestTask);
  }

  public function shouldAppearInActionMenu() {
    return false;
  }

  public function getDialogTitleText() {
    return pht('Edit Blockers');
  }

  public function getDialogHeaderText() {
    return pht('Current Blockers');
  }

  public function getDialogButtonText() {
    return pht('Save Blockers');
  }

  protected function newRelationshipSource() {
    return id(new ManiphestTaskRelationshipSource())
      ->setSelectedFilter('open');
  }

}
