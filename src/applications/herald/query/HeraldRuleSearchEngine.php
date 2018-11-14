<?php

final class HeraldRuleSearchEngine extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Herald Rules');
  }

  public function getApplicationClassName() {
    return 'PhabricatorHeraldApplication';
  }

  public function newQuery() {
    return id(new HeraldRuleQuery())
      ->needValidateAuthors(true);
  }

  protected function buildCustomSearchFields() {
    $viewer = $this->requireViewer();

    $rule_types = HeraldRuleTypeConfig::getRuleTypeMap();
    $content_types = HeraldAdapter::getEnabledAdapterMap($viewer);

    return array(
      id(new PhabricatorUsersSearchField())
        ->setLabel(pht('Authors'))
        ->setKey('authorPHIDs')
        ->setAliases(array('author', 'authors', 'authorPHID'))
        ->setDescription(
          pht('Search for rules with given authors.')),
      id(new PhabricatorSearchCheckboxesField())
        ->setKey('ruleTypes')
        ->setAliases(array('ruleType'))
        ->setLabel(pht('Rule Type'))
        ->setDescription(
          pht('Search for rules of given types.'))
        ->setOptions($rule_types),
      id(new PhabricatorSearchCheckboxesField())
        ->setKey('contentTypes')
        ->setLabel(pht('Content Type'))
        ->setDescription(
          pht('Search for rules affecting given types of content.'))
        ->setOptions($content_types),
      id(new PhabricatorSearchThreeStateField())
        ->setLabel(pht('Active Rules'))
        ->setKey('active')
        ->setOptions(
          pht('(Show All)'),
          pht('Show Only Active Rules'),
          pht('Show Only Inactive Rules')),
      id(new PhabricatorSearchThreeStateField())
        ->setLabel(pht('Disabled Rules'))
        ->setKey('disabled')
        ->setOptions(
          pht('(Show All)'),
          pht('Show Only Disabled Rules'),
          pht('Show Only Enabled Rules')),
    );
  }

  protected function buildQueryFromParameters(array $map) {
    $query = $this->newQuery();

    if ($map['authorPHIDs']) {
      $query->withAuthorPHIDs($map['authorPHIDs']);
    }

    if ($map['contentTypes']) {
      $query->withContentTypes($map['contentTypes']);
    }

    if ($map['ruleTypes']) {
      $query->withRuleTypes($map['ruleTypes']);
    }

    if ($map['disabled'] !== null) {
      $query->withDisabled($map['disabled']);
    }

    if ($map['active'] !== null) {
      $query->withActive($map['active']);
    }

    return $query;
  }

  protected function getURI($path) {
    return '/herald/'.$path;
  }

  protected function getBuiltinQueryNames() {
    $names = array();

    if ($this->requireViewer()->isLoggedIn()) {
      $names['authored'] = pht('Authored');
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
          ->setParameter('active', true);
      case 'authored':
        return $query
          ->setParameter('authorPHIDs', array($viewer_phid))
          ->setParameter('disabled', false);
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  protected function renderResultList(
    array $rules,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($rules, 'HeraldRule');

    $viewer = $this->requireViewer();
    $handles = $viewer->loadHandles(mpull($rules, 'getAuthorPHID'));

    $content_type_map = HeraldAdapter::getEnabledAdapterMap($viewer);

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer);
    foreach ($rules as $rule) {
      $monogram = $rule->getMonogram();

      $item = id(new PHUIObjectItemView())
        ->setObjectName($monogram)
        ->setHeader($rule->getName())
        ->setHref("/{$monogram}");

      if ($rule->isPersonalRule()) {
        $item->addIcon('fa-user', pht('Personal Rule'));
        $item->addByline(
          pht(
            'Authored by %s',
            $handles[$rule->getAuthorPHID()]->renderLink()));
      } else if ($rule->isObjectRule()) {
        $item->addIcon('fa-briefcase', pht('Object Rule'));
      } else {
        $item->addIcon('fa-globe', pht('Global Rule'));
      }

      if ($rule->getIsDisabled()) {
        $item->setDisabled(true);
        $item->addIcon('fa-lock grey', pht('Disabled'));
      } else if (!$rule->hasValidAuthor()) {
        $item->setDisabled(true);
        $item->addIcon('fa-user grey', pht('Author Not Active'));
      }

      $content_type_name = idx($content_type_map, $rule->getContentType());
      $item->addAttribute(pht('Affects: %s', $content_type_name));

      $list->addItem($item);
    }

    $result = new PhabricatorApplicationSearchResultView();
    $result->setObjectList($list);
    $result->setNoDataString(pht('No rules found.'));

    return $result;

  }

  protected function getNewUserBody() {
    $create_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('Create Herald Rule'))
      ->setHref('/herald/create/')
      ->setColor(PHUIButtonView::GREEN);

    $icon = $this->getApplication()->getIcon();
    $app_name =  $this->getApplication()->getName();
    $view = id(new PHUIBigInfoView())
      ->setIcon($icon)
      ->setTitle(pht('Welcome to %s', $app_name))
      ->setDescription(
        pht('A flexible rules engine that can notify and act on '.
            'other actions such as tasks, diffs, and commits.'))
      ->addAction($create_button);

      return $view;
  }

}
