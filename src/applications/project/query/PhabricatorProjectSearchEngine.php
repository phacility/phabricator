<?php

final class PhabricatorProjectSearchEngine
  extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Projects');
  }

  public function getApplicationClassName() {
    return 'PhabricatorProjectApplication';
  }

  public function getCustomFieldObject() {
    return new PhabricatorProject();
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter(
      'memberPHIDs',
      $this->readUsersFromRequest($request, 'members'));

    $saved->setParameter('status', $request->getStr('status'));
    $saved->setParameter('name', $request->getStr('name'));

    $this->readCustomFieldsFromRequest($request, $saved);

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new PhabricatorProjectQuery())
      ->needImages(true);

    $member_phids = $saved->getParameter('memberPHIDs', array());
    if ($member_phids && is_array($member_phids)) {
      $query->withMemberPHIDs($member_phids);
    }

    $status = $saved->getParameter('status');
    $status = idx($this->getStatusValues(), $status);
    if ($status) {
      $query->withStatus($status);
    }

    $name = $saved->getParameter('name');
    if (strlen($name)) {
      $query->withDatasourceQuery($name);
    }

    $this->applyCustomFieldsToQuery($query, $saved);

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved) {

    $phids = $saved->getParameter('memberPHIDs', array());
    $member_handles = id(new PhabricatorHandleQuery())
      ->setViewer($this->requireViewer())
      ->withPHIDs($phids)
      ->execute();

    $status = $saved->getParameter('status');
    $name = $saved->getParameter('name');

    $form
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('name')
          ->setLabel(pht('Name'))
          ->setValue($name))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorPeopleDatasource())
          ->setName('members')
          ->setLabel(pht('Members'))
          ->setValue($member_handles))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Status'))
          ->setName('status')
          ->setOptions($this->getStatusOptions())
          ->setValue($status));

    $this->appendCustomFieldsToForm($form, $saved);
  }

  protected function getURI($path) {
    return '/project/'.$path;
  }

  public function getBuiltinQueryNames() {
    $names = array();

    if ($this->requireViewer()->isLoggedIn()) {
      $names['joined'] = pht('Joined');
    }

    $names['active'] = pht('Active');
    $names['all'] = pht('All');

    return $names;
  }

  public function buildSavedQueryFromBuiltin($query_key) {
    $query = $this->newSavedQuery();
    $query->setQueryKey($query_key);

    $viewer_phid = $this->requireViewer()->getPHID();

    switch ($query_key) {
      case 'all':
        return $query;
      case 'active':
        return $query
          ->setParameter('status', 'active');
      case 'joined':
        return $query
          ->setParameter('memberPHIDs', array($viewer_phid))
          ->setParameter('status', 'active');
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  private function getStatusOptions() {
    return array(
      'active' => pht('Show Only Active Projects'),
      'all'    => pht('Show All Projects'),
    );
  }

  private function getStatusValues() {
    return array(
      'active' => PhabricatorProjectQuery::STATUS_ACTIVE,
      'all' => PhabricatorProjectQuery::STATUS_ANY,
    );
  }

  protected function renderResultList(
    array $projects,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($projects, 'PhabricatorProject');
    $viewer = $this->requireViewer();

    $list = new PHUIObjectItemListView();
    $list->setUser($viewer);
    foreach ($projects as $project) {
      $id = $project->getID();
      $workboards_uri = $this->getApplicationURI("board/{$id}/");
      $members_uri = $this->getApplicationURI("members/{$id}/");
      $workboards_url = phutil_tag(
        'a',
        array(
          'href' => $workboards_uri
        ),
        pht('Workboards'));

      $members_url = phutil_tag(
        'a',
        array(
          'href' => $members_uri
        ),
        pht('Members'));

      $item = id(new PHUIObjectItemView())
        ->setHeader($project->getName())
        ->setHref($this->getApplicationURI("view/{$id}/"))
        ->setImageURI($project->getProfileImageURI())
        ->addAttribute($workboards_url)
        ->addAttribute($members_url);

      if ($project->getStatus() == PhabricatorProjectStatus::STATUS_ARCHIVED) {
        $item->addIcon('delete-grey', pht('Archived'));
        $item->setDisabled(true);
      }

      $list->addItem($item);
    }

    return $list;
  }

}
