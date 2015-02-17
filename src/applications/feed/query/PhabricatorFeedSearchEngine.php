<?php

final class PhabricatorFeedSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Feed Stories');
  }

  public function getApplicationClassName() {
    return 'PhabricatorFeedApplication';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter(
      'userPHIDs',
      $this->readUsersFromRequest($request, 'users'));

    $saved->setParameter(
      'projectPHIDs',
      array_values($request->getArr('projectPHIDs')));

    $saved->setParameter(
      'viewerProjects',
      $request->getBool('viewerProjects'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhabricatorFeedQuery());

    $phids = array();

    $user_phids = $saved->getParameter('userPHIDs');
    if ($user_phids) {
      $phids[] = $user_phids;
    }

    $proj_phids = $saved->getParameter('projectPHIDs');
    if ($proj_phids) {
      $phids[] = $proj_phids;
    }

    $viewer_projects = $saved->getParameter('viewerProjects');
    if ($viewer_projects) {
      $viewer = $this->requireViewer();
      $projects = id(new PhabricatorProjectQuery())
        ->setViewer($viewer)
        ->withMemberPHIDs(array($viewer->getPHID()))
        ->execute();
      $phids[] = mpull($projects, 'getPHID');
    }

    $phids = array_mergev($phids);
    if ($phids) {
      $query->setFilterPHIDs($phids);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {

    $user_phids = $saved_query->getParameter('userPHIDs', array());
    $proj_phids = $saved_query->getParameter('projectPHIDs', array());

    $phids = array_merge($user_phids, $proj_phids);
    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($this->requireViewer())
      ->withPHIDs($phids)
      ->execute();
    $user_handles = array_select_keys($handles, $user_phids);
    $proj_handles = array_select_keys($handles, $proj_phids);

    $viewer_projects = $saved_query->getParameter('viewerProjects');

    $form
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorPeopleDatasource())
          ->setName('users')
          ->setLabel(pht('Include Users'))
          ->setValue($user_handles))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorProjectDatasource())
          ->setName('projectPHIDs')
          ->setLabel(pht('Include Projects'))
          ->setValue($proj_handles))
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->addCheckbox(
            'viewerProjects',
            1,
            pht('Include stories about projects I am a member of.'),
            $viewer_projects));
  }

  protected function getURI($path) {
    return '/feed/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array(
      'all' => pht('All Stories'),
    );

    if ($this->requireViewer()->isLoggedIn()) {
      $names['projects'] = pht('Projects');
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
        return $query->setParameter('viewerProjects', true);
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

    return phutil_tag_div('phabricator-feed-frame', $view);
  }

}
