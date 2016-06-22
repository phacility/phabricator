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

}
