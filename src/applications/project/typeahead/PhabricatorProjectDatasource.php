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

    // If this is for policy selection, prevent users from using milestones.
    $for_policy = $this->getParameter('policy');
    if ($for_policy) {
      $query->withIsMilestone(false);
    }

    $for_autocomplete = $this->getParameter('autocomplete');

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

      $slug = $proj->getPrimarySlug();
      if (!strlen($slug)) {
        foreach ($proj->getSlugs() as $slug_object) {
          $slug = $slug_object->getSlug();
          if (strlen($slug)) {
            break;
          }
        }
      }

      // If we're building results for the autocompleter and this project
      // doesn't have any usable slugs, don't return it as a result.
      if ($for_autocomplete && !strlen($slug)) {
        continue;
      }

      $closed = null;
      if ($proj->isArchived()) {
        $closed = pht('Archived');
      }

      $all_strings = array();
      $all_strings[] = $proj->getDisplayName();

      // Add an extra space after the name so that the original project
      // sorts ahead of milestones. This is kind of a hack but ehh?
      $all_strings[] = null;

      foreach ($proj->getSlugs() as $project_slug) {
        $all_strings[] = $project_slug->getSlug();
      }
      $all_strings = implode(' ', $all_strings);

      $proj_result = id(new PhabricatorTypeaheadResult())
        ->setName($all_strings)
        ->setDisplayName($proj->getDisplayName())
        ->setDisplayType($proj->getDisplayIconName())
        ->setURI($proj->getURI())
        ->setPHID($proj->getPHID())
        ->setIcon($proj->getDisplayIconIcon())
        ->setColor($proj->getColor())
        ->setPriorityType('proj')
        ->setClosed($closed);

      if (strlen($slug)) {
        $proj_result->setAutocomplete('#'.$slug);
      }

      $proj_result->setImageURI($proj->getProfileImageURI());

      $results[] = $proj_result;
    }

    return $results;
  }

}
