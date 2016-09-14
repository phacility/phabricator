<?php

final class ManiphestTaskHasCommitRelationship
  extends ManiphestTaskRelationship {

  const RELATIONSHIPKEY = 'task.has-commit';

  public function getEdgeConstant() {
    return ManiphestTaskHasCommitEdgeType::EDGECONST;
  }

  protected function getActionName() {
    return pht('Edit Commits');
  }

  protected function getActionIcon() {
    return 'fa-code';
  }

  public function canRelateObjects($src, $dst) {
    return ($dst instanceof PhabricatorRepositoryCommit);
  }

  public function getDialogTitleText() {
    return pht('Edit Related Commits');
  }

  public function getDialogHeaderText() {
    return pht('Current Commits');
  }

  public function getDialogButtonText() {
    return pht('Save Related Commits');
  }

  protected function newRelationshipSource() {
    return new DiffusionCommitRelationshipSource();
  }

}
