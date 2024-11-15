<?php

final class PhabricatorRoleDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getBrowseTitle() {
    return pht('Browse Roles');
  }

  public function getPlaceholderText() {
    return pht('Type a role name...');
  }

  public function getDatasourceApplicationClass() {
    return 'PhabricatorRoleApplication';
  }

  public function loadResults() {
    $viewer = $this->getViewer();

    $raw_query = $this->getRawQuery();

    // Allow users to type "#qa" or "qa" to find "Quality Assurance".
    $raw_query = ltrim($raw_query, '#');
    $tokens = self::tokenizeString($raw_query);

    $query = id(new PhabricatorRoleQuery())
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

    $roles = $this->executeQuery($query);

    $roles = mpull($roles, null, 'getPHID');

    $must_have_cols = $this->getParameter('mustHaveColumns', false);
    if ($must_have_cols) {
      $columns = id(new PhabricatorRoleColumnQuery())
        ->setViewer($viewer)
        ->withRolePHIDs(array_keys($roles))
        ->withIsProxyColumn(false)
        ->execute();
      $has_cols = mgroup($columns, 'getRolePHID');
    } else {
      $has_cols = array_fill_keys(array_keys($roles), true);
    }

    $is_browse = $this->getIsBrowse();
    if ($is_browse && $roles) {
      // TODO: This is a little ad-hoc, but we don't currently have
      // infrastructure for bulk querying custom fields efficiently.
      $table = new PhabricatorRoleCustomFieldStorage();
      $descriptions = $table->loadAllWhere(
        'objectPHID IN (%Ls) AND fieldIndex = %s',
        array_keys($roles),
        PhabricatorHash::digestForIndex('std:role:internal:description'));
      $descriptions = mpull($descriptions, 'getFieldValue', 'getObjectPHID');
    } else {
      $descriptions = array();
    }

    $results = array();
    foreach ($roles as $role) {
      $phid = $role->getPHID();

      if (!isset($has_cols[$phid])) {
        continue;
      }

      $slug = $role->getPrimarySlug();
      if ($slug === null || $slug === '') {
        foreach ($role->getSlugs() as $slug_object) {
          $slug = $slug_object->getSlug();
          if ($slug !== null && $slug !== '') {
            break;
          }
        }
      }

      // If we're building results for the autocompleter and this role
      // doesn't have any usable slugs, don't return it as a result.
      if ($for_autocomplete && !strlen($slug)) {
        continue;
      }

      $closed = null;
      if ($role->isArchived()) {
        $closed = pht('Archived');
      }

      $all_strings = array();

      // NOTE: We list the role's name first because results will be
      // sorted into prefix vs content phases incorrectly if we don't: it
      // will look like "Parent (Milestone)" matched "Parent" as a prefix,
      // but it did not.
      $all_strings[] = $role->getName();

      if ($role->isMilestone()) {
        $all_strings[] = $role->getParentRole()->getName();
      }

      foreach ($role->getSlugs() as $role_slug) {
        $all_strings[] = $role_slug->getSlug();
      }

      $all_strings = implode("\n", $all_strings);

      $role_result = id(new PhabricatorTypeaheadResult())
        ->setName($all_strings)
        ->setDisplayName($role->getDisplayName())
        ->setDisplayType($role->getDisplayIconName())
        ->setURI($role->getURI())
        ->setPHID($phid)
        ->setIcon($role->getDisplayIconIcon())
        ->setColor($role->getColor())
        ->setPriorityType('role')
        ->setClosed($closed);

      if ($slug !== null && $slug !== '') {
        $role_result->setAutocomplete('#'.$slug);
      }

      $role_result->setImageURI($role->getProfileImageURI());

      if ($is_browse) {
        $role_result->addAttribute($role->getDisplayIconName());

        $description = idx($descriptions, $phid);
        if ($description !== null && $description !== '') {
          $summary = PhabricatorMarkupEngine::summarizeSentence($description);
          $role_result->addAttribute($summary);
        }
      }

      $results[] = $role_result;
    }

    return $results;
  }

}
