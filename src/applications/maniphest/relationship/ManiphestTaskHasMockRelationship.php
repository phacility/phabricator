<?php

final class ManiphestTaskHasMockRelationship
  extends ManiphestTaskRelationship {

  const RELATIONSHIPKEY = 'task.has-mock';

  public function getEdgeConstant() {
    return ManiphestTaskHasMockEdgeType::EDGECONST;
  }

  protected function getActionName() {
    return pht('Edit Mocks');
  }

  protected function getActionIcon() {
    return 'fa-camera-retro';
  }

  public function canRelateObjects($src, $dst) {
    return ($dst instanceof PholioMock);
  }

  public function getDialogTitleText() {
    return pht('Edit Related Mocks');
  }

  public function getDialogHeaderText() {
    return pht('Current Mocks');
  }

  public function getDialogButtonText() {
    return pht('Save Related Mocks');
  }

  protected function newRelationshipSource() {
    return new PholioMockRelationshipSource();
  }

}
