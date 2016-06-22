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

  public function shouldAppearInActionMenu() {
    // TODO: For now, the default search for commits is not very good, so
    // it is hard to find objects to link to. Until that works better, just
    // hide this item.
    return false;
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

}
