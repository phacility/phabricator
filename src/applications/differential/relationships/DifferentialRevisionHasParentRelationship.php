<?php

final class DifferentialRevisionHasParentRelationship
  extends DifferentialRevisionRelationship {

  const RELATIONSHIPKEY = 'revision.has-parent';

  public function getEdgeConstant() {
    return DifferentialRevisionDependsOnRevisionEdgeType::EDGECONST;
  }

  protected function getActionName() {
    return pht('Edit Parent Revisions');
  }

  protected function getActionIcon() {
    return 'fa-chevron-circle-up';
  }

  public function canRelateObjects($src, $dst) {
    return ($dst instanceof DifferentialRevision);
  }

  public function shouldAppearInActionMenu() {
    return false;
  }

  public function getDialogTitleText() {
    return pht('Edit Parent Revisions');
  }

  public function getDialogHeaderText() {
    return pht('Current Parent Revisions');
  }

  public function getDialogButtonText() {
    return pht('Save Parent Revisions');
  }

  protected function newRelationshipSource() {
    return new DifferentialRevisionRelationshipSource();
  }

}
