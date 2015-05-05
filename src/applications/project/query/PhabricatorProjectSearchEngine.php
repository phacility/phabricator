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

    $saved->setParameter(
      'icons',
      $this->readListFromRequest($request, 'icons'));

    $saved->setParameter(
      'colors',
      $this->readListFromRequest($request, 'colors'));

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
      $tokens = PhabricatorTypeaheadDatasource::tokenizeString($name);
      $query->withNameTokens($tokens);
    }

    $icons = $saved->getParameter('icons');
    if ($icons) {
      $query->withIcons($icons);
    }

    $colors = $saved->getParameter('colors');
    if ($colors) {
      $query->withColors($colors);
    }

    $this->applyCustomFieldsToQuery($query, $saved);

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved) {

    $member_phids = $saved->getParameter('memberPHIDs', array());

    $status = $saved->getParameter('status');
    $name_match = $saved->getParameter('name');

    $icons = array_fuse($saved->getParameter('icons', array()));
    $colors = array_fuse($saved->getParameter('colors', array()));

    $icon_control = id(new AphrontFormCheckboxControl())
      ->setLabel(pht('Icons'));
    foreach (PhabricatorProjectIcon::getIconMap() as $icon => $name) {
      $image = id(new PHUIIconView())
        ->setIconFont($icon);

      $icon_control->addCheckbox(
        'icons[]',
        $icon,
        array($image, ' ', $name),
        isset($icons[$icon]));
    }

    $color_control = id(new AphrontFormCheckboxControl())
      ->setLabel(pht('Colors'));
    foreach (PhabricatorProjectIcon::getColorMap() as $color => $name) {
      $tag = id(new PHUITagView())
        ->setType(PHUITagView::TYPE_SHADE)
        ->setShade($color)
        ->setName($name);

      $color_control->addCheckbox(
        'colors[]',
        $color,
        $tag,
        isset($colors[$color]));
    }

    $form
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setName('name')
          ->setLabel(pht('Name'))
          ->setValue($name_match))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorPeopleDatasource())
          ->setName('members')
          ->setLabel(pht('Members'))
          ->setValue($member_phids))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Status'))
          ->setName('status')
          ->setOptions($this->getStatusOptions())
          ->setValue($status))
      ->appendChild($icon_control)
      ->appendChild($color_control);

    $this->appendCustomFieldsToForm($form, $saved);
  }

  protected function getURI($path) {
    return '/project/'.$path;
  }

  protected function getBuiltinQueryNames() {
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
      'active'   => pht('Show Only Active Projects'),
      'archived' => pht('Show Only Archived Projects'),
      'all'      => pht('Show All Projects'),
    );
  }

  private function getStatusValues() {
    return array(
      'active'   => PhabricatorProjectQuery::STATUS_ACTIVE,
      'archived' => PhabricatorProjectQuery::STATUS_ARCHIVED,
      'all'      => PhabricatorProjectQuery::STATUS_ANY,
    );
  }

  private function getColorValues() {}

  private function getIconValues() {}

  protected function getRequiredHandlePHIDsForResultList(
    array $projects,
    PhabricatorSavedQuery $query) {
    return mpull($projects, 'getPHID');
  }

  protected function renderResultList(
    array $projects,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($projects, 'PhabricatorProject');
    $viewer = $this->requireViewer();

    $list = new PHUIObjectItemListView();
    $list->setUser($viewer);
    $can_edit_projects = id(new PhabricatorPolicyFilter())
      ->setViewer($viewer)
      ->requireCapabilities(array(PhabricatorPolicyCapability::CAN_EDIT))
      ->apply($projects);

    foreach ($projects as $key => $project) {
      $id = $project->getID();

      $tag_list = id(new PHUIHandleTagListView())
        ->setSlim(true)
        ->setHandles(array($handles[$project->getPHID()]));

      $item = id(new PHUIObjectItemView())
        ->setHeader($project->getName())
        ->setHref($this->getApplicationURI("view/{$id}/"))
        ->setImageURI($project->getProfileImageURI())
        ->addAttribute($tag_list);

      if ($project->getStatus() == PhabricatorProjectStatus::STATUS_ARCHIVED) {
        $item->addIcon('delete-grey', pht('Archived'));
        $item->setDisabled(true);
      }

      $list->addItem($item);
    }

    return $list;
  }

}
