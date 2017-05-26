<?php

final class PhabricatorFeedSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Feed Stories');
  }

  public function getApplicationClassName() {
    return 'PhabricatorFeedApplication';
  }

  public function newQuery() {
    return new PhabricatorFeedQuery();
  }

  protected function shouldShowOrderField() {
    return false;
  }

  protected function buildCustomSearchFields() {
    return array(
      id(new PhabricatorUsersSearchField())
        ->setLabel(pht('Include Users'))
        ->setKey('userPHIDs'),
      // NOTE: This query is not executed with EdgeLogic, so we can't use
      // a fancy logical datasource.
      id(new PhabricatorSearchDatasourceField())
        ->setDatasource(new PhabricatorProjectDatasource())
        ->setLabel(pht('Include Projects'))
        ->setKey('projectPHIDs'),
      id(new PhabricatorSearchDateControlField())
        ->setLabel(pht('Occurs After'))
        ->setKey('rangeStart'),
      id(new PhabricatorSearchDateControlField())
        ->setLabel(pht('Occurs Before'))
        ->setKey('rangeEnd'),

      // NOTE: This is a legacy field retained only for backward
      // compatibility. If the projects field used EdgeLogic, we could use
      // `viewerprojects()` to execute an equivalent query.
      id(new PhabricatorSearchCheckboxesField())
        ->setKey('viewerProjects')
        ->setOptions(
          array(
            'self' => pht('Include stories about projects I am a member of.'),
          )),
    );
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    $phids = array();
    if ($map['userPHIDs']) {
      $phids += array_fuse($map['userPHIDs']);
    }

    if ($map['projectPHIDs']) {
      $phids += array_fuse($map['projectPHIDs']);
    }

    // NOTE: This value may be `true` for older saved queries, or
    // `array('self')` for newer ones.
    $viewer_projects = $map['viewerProjects'];
    if ($viewer_projects) {
      $viewer = $this->requireViewer();
      $projects = id(new PhabricatorProjectQuery())
        ->setViewer($viewer)
        ->withMemberPHIDs(array($viewer->getPHID()))
        ->execute();
      $phids += array_fuse(mpull($projects, 'getPHID'));
    }

    if ($phids) {
      $query->withFilterPHIDs($phids);
    }

    $range_min = $map['rangeStart'];
    if ($range_min) {
      $range_min = $range_min->getEpoch();
    }

    $range_max = $map['rangeEnd'];
    if ($range_max) {
      $range_max = $range_max->getEpoch();
    }

    if ($range_min && $range_max) {
      if ($range_min > $range_max) {
        throw new PhabricatorSearchConstraintException(
          pht(
            'The specified "Occurs Before" date is earlier in time than the '.
            'specified "Occurs After" date, so this query can never match '.
            'any results.'));
      }
    }

    if ($range_min || $range_max) {
      $query->withEpochInRange($range_min, $range_max);
    }

    return $query;
  }

  protected function getURI($path) {
    return '/feed/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('All Stories'),
    );

    if ($this->requireViewer()->isLoggedIn()) {
      $names['projects'] = pht('Tags');
    }

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {

    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    switch ($query_key) {
      case 'all':
        return $query;
      case 'projects':
        return $query->setParameter('viewerProjects', array('self'));
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function renderResultList(
    array $objects,
    PhabricatorSavedQuery $query,
    array $handles) {

    $builder = new PhabricatorFeedBuilder($objects);

    if ($this->isPanelContext()) {
      $builder->setShowHovercards(false);
    } else {
      $builder->setShowHovercards(true);
    }

    $builder->setUser($this->requireViewer());
    $view = $builder->buildView();

    $list = phutil_tag_div('phabricator-feed-frame', $view);

    $result = new PhabricatorApplicationSearchResultView();
    $result->setContent($list);

    return $result;
  }

}
