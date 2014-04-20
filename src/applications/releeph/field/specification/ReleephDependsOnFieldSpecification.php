<?php

final class ReleephDependsOnFieldSpecification
  extends ReleephFieldSpecification {
  public function getFieldKey() {
    return 'dependsOn';
  }

  public function getName() {
    return pht('Depends On');
  }

  public function getRequiredHandlePHIDsForPropertyView() {
    return $this->getDependentRevisionPHIDs();
  }

  public function renderPropertyViewValue(array $handles) {
    return $this->renderHandleList($handles);
  }

  private function getDependentRevisionPHIDs() {
    $requested_object = $this->getObject()->getRequestedObjectPHID();
    if (!($requested_object instanceof DifferentialRevision)) {
      return array();
    }

    $revision = $requested_object;

    return PhabricatorEdgeQuery::loadDestinationPHIDs(
      $revision->getPHID(),
      PhabricatorEdgeConfig::TYPE_DREV_DEPENDS_ON_DREV);
  }
}
