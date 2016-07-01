<?php

final class DiffusionCommitHasTaskRelationship
  extends DiffusionCommitRelationship {

  const RELATIONSHIPKEY = 'commit.has-task';

  public function getEdgeConstant() {
    return DiffusionCommitHasTaskEdgeType::EDGECONST;
  }

  protected function getActionName() {
    return pht('Edit Tasks');
  }

  protected function getActionIcon() {
    return 'fa-anchor';
  }

  public function canRelateObjects($src, $dst) {
    return ($dst instanceof ManiphestTask);
  }

  public function getDialogTitleText() {
    return pht('Edit Related Tasks');
  }

  public function getDialogHeaderText() {
    return pht('Current Tasks');
  }

  public function getDialogButtonText() {
    return pht('Save Related Tasks');
  }

  protected function newRelationshipSource() {
    return new ManiphestTaskRelationshipSource();
  }

}
