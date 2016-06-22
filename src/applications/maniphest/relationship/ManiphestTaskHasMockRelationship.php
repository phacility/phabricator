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

}
