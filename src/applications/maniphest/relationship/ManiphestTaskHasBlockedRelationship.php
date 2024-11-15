<?php

final class ManiphestTaskHasBlockedRelationship
  extends ManiphestTaskRelationship {

  const RELATIONSHIPKEY = 'task.has-blocked';

  public function getEdgeConstant() {
    return ManiphestTaskBlockedEdgeType::EDGECONST;
  }

  protected function getActionName() {
    return pht('It blocks.. (Edit)');
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
    return pht('Edit Blocked Tasks');
  }

  public function getDialogHeaderText() {
    return pht('Current Blocked Tasks');
  }

  public function getDialogButtonText() {
    return pht('Save Blocked Tasks');
  }

  protected function newRelationshipSource() {
    return id(new ManiphestTaskRelationshipSource())
      ->setSelectedFilter('open');
  }

}
