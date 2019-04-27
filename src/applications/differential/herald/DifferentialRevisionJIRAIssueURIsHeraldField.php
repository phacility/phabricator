<?php

final class DifferentialRevisionJIRAIssueURIsHeraldField
  extends DifferentialRevisionHeraldField {

  const FIELDCONST = 'differential.revision.jira.uris';

  public function getHeraldFieldName() {
    return pht('JIRA Issue URIs');
  }

  public function supportsObject($object) {
    $provider = PhabricatorJIRAAuthProvider::getJIRAProvider();
    if (!$provider) {
      return false;
    }

    return parent::supportsObject($object);
  }

  public function getHeraldFieldValue($object) {
    $adapter = $this->getAdapter();
    $viewer = $adapter->getViewer();

    $jira_phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $object->getPHID(),
      PhabricatorJiraIssueHasObjectEdgeType::EDGECONST);
    if (!$jira_phids) {
      return array();
    }

    $xobjs = id(new DoorkeeperExternalObjectQuery())
      ->setViewer($viewer)
      ->withPHIDs($jira_phids)
      ->execute();

    return mpull($xobjs, 'getObjectURI');
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_TEXT_LIST;
  }

}
