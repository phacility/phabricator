<?php

final class DiffusionCommitAuthorProjectsHeraldField
  extends DiffusionCommitHeraldField {

  const FIELDCONST = 'diffusion.commit.author.projects';

  public function getHeraldFieldName() {
    return pht("Author's projects");
  }

  public function getHeraldFieldValue($object) {
    $adapter = $this->getAdapter();
    $viewer = $adapter->getViewer();

    $phid = $adapter->getAuthorPHID();
    if (!$phid) {
      return array();
    }

    $projects = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->withMemberPHIDs(array($phid))
      ->execute();

    return mpull($projects, 'getPHID');
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_PHID_LIST;
  }

  protected function getDatasource() {
    return new PhabricatorProjectDatasource();
  }

}
