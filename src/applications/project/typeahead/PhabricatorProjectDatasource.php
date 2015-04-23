<?php

final class PhabricatorProjectDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getBrowseTitle() {
    return pht('Browse Projects');
  }

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
    $tokens = self::tokenizeString($raw_query);

    $query = id(new PhabricatorProjectQuery())
      ->needImages(true)
      ->needSlugs(true);

    if ($tokens) {
      $query->withNameTokens($tokens);
    }

    $projs = $this->executeQuery($query);

    $projs = mpull($projs, null, 'getPHID');

    $must_have_cols = $this->getParameter('mustHaveColumns', false);
    if ($must_have_cols) {
      $columns = id(new PhabricatorProjectColumnQuery())
        ->setViewer($viewer)
        ->withProjectPHIDs(array_keys($projs))
        ->execute();
      $has_cols = mgroup($columns, 'getProjectPHID');
    } else {
      $has_cols = array_fill_keys(array_keys($projs), true);
    }

    $results = array();
    foreach ($projs as $proj) {
      if (!isset($has_cols[$proj->getPHID()])) {
        continue;
      }
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
        ->setIcon($proj->getIcon())
        ->setColor($proj->getColor())
        ->setPriorityType('proj')
        ->setClosed($closed);

      $proj_result->setImageURI($proj->getProfileImageURI());

      $results[] = $proj_result;
    }

    return $results;
  }

}
