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
    if ($raw_query !== null && strlen($raw_query)) {
      // Allow users to type "#qa" or "qa" to find "Quality Assurance".
      $raw_query = ltrim($raw_query, '#');
    }
    $tokens = self::tokenizeString($raw_query);

    $query = id(new PhabricatorProjectQuery())
      ->needImages(true)
      ->needSlugs(true)
      ->setOrderVector(array('-status', 'id'));

    if ($this->getPhase() == self::PHASE_PREFIX) {
      $prefix = $this->getPrefixQuery();
      $query->withNamePrefixes(array($prefix));
    } else if ($tokens) {
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
        ->withIsProxyColumn(false)
        ->execute();
      $has_cols = mgroup($columns, 'getProjectPHID');
    } else {
      $has_cols = array_fill_keys(array_keys($projs), true);
    }

    $is_browse = $this->getIsBrowse();
    if ($is_browse && $projs) {
      // TODO: This is a little ad-hoc, but we don't currently have
      // infrastructure for bulk querying custom fields efficiently.
      $table = new PhabricatorProjectCustomFieldStorage();
      $descriptions = $table->loadAllWhere(
        'objectPHID IN (%Ls) AND fieldIndex = %s',
        array_keys($projs),
        PhabricatorHash::digestForIndex('std:project:internal:description'));
      $descriptions = mpull($descriptions, 'getFieldValue', 'getObjectPHID');
    } else {
      $descriptions = array();
    }

    $results = array();
    foreach ($projs as $proj) {
      $phid = $proj->getPHID();

      if (!isset($has_cols[$phid])) {
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

      // NOTE: We list the project's name first because results will be
      // sorted into prefix vs content phases incorrectly if we don't: it
      // will look like "Parent (Milestone)" matched "Parent" as a prefix,
      // but it did not.
      $all_strings[] = $proj->getName();

      if ($proj->isMilestone()) {
        $all_strings[] = $proj->getParentProject()->getName();
      }

      foreach ($proj->getSlugs() as $project_slug) {
        $all_strings[] = $project_slug->getSlug();
      }

      $all_strings = implode("\n", $all_strings);

      $proj_result = id(new PhabricatorTypeaheadResult())
        ->setName($all_strings)
        ->setDisplayName($proj->getDisplayName())
        ->setDisplayType($proj->getDisplayIconName())
        ->setURI($proj->getURI())
        ->setPHID($phid)
        ->setIcon($proj->getDisplayIconIcon())
        ->setColor($proj->getColor())
        ->setPriorityType('proj')
        ->setClosed($closed);

      if (strlen($slug)) {
        $proj_result->setAutocomplete('#'.$slug);
      }

      $proj_result->setImageURI($proj->getProfileImageURI());

      if ($is_browse) {
        $proj_result->addAttribute($proj->getDisplayIconName());

        $description = idx($descriptions, $phid);
        if ($description !== null && strlen($description)) {
          $summary = PhabricatorMarkupEngine::summarizeSentence($description);
          $proj_result->addAttribute($summary);
        }
      }

      $results[] = $proj_result;
    }

    return $results;
  }

}
