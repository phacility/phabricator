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
    $revision = $this
      ->getReleephRequest()
      ->loadDifferentialRevision();
    if (!$revision) {
      return array();
    }

    return PhabricatorEdgeQuery::loadDestinationPHIDs(
      $revision->getPHID(),
      PhabricatorEdgeConfig::TYPE_DREV_DEPENDS_ON_DREV);
  }
}
