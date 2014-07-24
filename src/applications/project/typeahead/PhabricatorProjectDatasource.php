<?php

final class PhabricatorProjectDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getPlaceholderText() {
    return pht('Type a project name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorProjectApplication';
  }

  public function loadResults() {
    $viewer = $this->getViewer();

    $raw_query = $this->getRawQuery();

    // Allow users to type "#qa" or "qa" to find "Quality Assurance".
    $raw_query = ltrim($raw_query, '#');

    if (!strlen($raw_query)) {
      return array();
    }

    $projs = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->needImages(true)
      ->needSlugs(true)
      ->withDatasourceQuery($raw_query)
      ->execute();

    $results = array();
    foreach ($projs as $proj) {
      $closed = null;
      if ($proj->isArchived()) {
        $closed = pht('Archived');
      }

      $all_strings = mpull($proj->getSlugs(), 'getSlug');
      $all_strings[] = $proj->getName();
      $all_strings = implode(' ', $all_strings);

      $proj_result = id(new PhabricatorTypeaheadResult())
        ->setName($all_strings)
        ->setDisplayName($proj->getName())
        ->setDisplayType('Project')
        ->setURI('/tag/'.$proj->getPrimarySlug().'/')
        ->setPHID($proj->getPHID())
        ->setIcon($proj->getIcon().' bluegrey')
        ->setPriorityType('proj')
        ->setClosed($closed);

      $proj_result->setImageURI($proj->getProfileImageURI());

      $results[] = $proj_result;
    }

    return $results;
  }

}
