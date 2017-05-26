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
