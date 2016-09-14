<?php

final class DifferentialRevisionHasChildRelationship
  extends DifferentialRevisionRelationship {

  const RELATIONSHIPKEY = 'revision.has-child';

  public function getEdgeConstant() {
    return DifferentialRevisionDependedOnByRevisionEdgeType::EDGECONST;
  }

  protected function getActionName() {
    return pht('Edit Child Revisions');
  }

  protected function getActionIcon() {
    return 'fa-chevron-circle-down';
  }

  public function canRelateObjects($src, $dst) {
    return ($dst instanceof DifferentialRevision);
  }

  public function shouldAppearInActionMenu() {
    return false;
  }

  public function getDialogTitleText() {
    return pht('Edit Child Revisions');
  }

  public function getDialogHeaderText() {
    return pht('Current Child Revisions');
  }

  public function getDialogButtonText() {
    return pht('Save Child Revisions');
  }

  protected function newRelationshipSource() {
    return new DifferentialRevisionRelationshipSource();
  }

}
