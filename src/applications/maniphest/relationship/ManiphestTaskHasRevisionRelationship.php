<?php

final class ManiphestTaskHasRevisionRelationship
  extends ManiphestTaskRelationship {

  const RELATIONSHIPKEY = 'task.has-revision';

  public function getEdgeConstant() {
    return ManiphestTaskHasRevisionEdgeType::EDGECONST;
  }

  protected function getActionName() {
    return pht('Edit Revisions');
  }

  protected function getActionIcon() {
    return 'fa-cog';
  }

  public function canRelateObjects($src, $dst) {
    return ($dst instanceof DifferentialRevision);
  }

  public function getDialogTitleText() {
    return pht('Edit Related Revisions');
  }

  public function getDialogHeaderText() {
    return pht('Current Revisions');
  }

  public function getDialogButtonText() {
    return pht('Save Related Revisions');
  }

  protected function newRelationshipSource() {
    return new DifferentialRevisionRelationshipSource();
  }

}
