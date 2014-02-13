<?php

final class ReleephDependsOnFieldSpecification
  extends ReleephFieldSpecification {
  public function getFieldKey() {
    return 'dependsOn';
  }

  public function getName() {
    return pht('Depends On');
  }

  public function renderValueForHeaderView() {
    $revision_phids = $this->getDependentRevisionPHIDs();
    if (!$revision_phids) {
      return null;
    }

    $links = array();
    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($this->getUser())
      ->withPHIDs($revision_phids)
      ->execute();
    foreach ($revision_phids as $revision_phid) {
      $links[] = id(clone $handles[$revision_phid])
        // Hack to remove the strike-through rendering of diff links
        ->setStatus(null)
        ->renderLink();
    }

    return phutil_implode_html(phutil_tag('br'), $links);
  }

  private function getDependentRevisionPHIDs() {
    $revision = $this
      ->getReleephRequest()
      ->loadDifferentialRevision();
    if (!$revision) {
      return null;
    }

    return PhabricatorEdgeQuery::loadDestinationPHIDs(
      $revision->getPHID(),
      PhabricatorEdgeConfig::TYPE_DREV_DEPENDS_ON_DREV);
  }
}
