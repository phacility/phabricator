<?php

final class ManiphestTaskHasParentRelationship
  extends ManiphestTaskRelationship {

  const RELATIONSHIPKEY = 'task.has-parent';

  public function getEdgeConstant() {
    return ManiphestTaskDependedOnByTaskEdgeType::EDGECONST;
  }

  protected function getActionName() {
    return pht('Edit Parent Tasks');
  }

  protected function getActionIcon() {
    return 'fa-chevron-circle-up';
  }

  public function canRelateObjects($src, $dst) {
    return ($dst instanceof ManiphestTask);
  }

  public function shouldAppearInActionMenu() {
    return false;
  }

  public function getDialogTitleText() {
    return pht('Edit Parent Tasks');
  }

  public function getDialogHeaderText() {
    return pht('Current Parent Tasks');
  }

  public function getDialogButtonText() {
    return pht('Save Parent Tasks');
  }

  protected function newRelationshipSource() {
    return id(new ManiphestTaskRelationshipSource())
      ->setSelectedFilter('open');
  }

}
