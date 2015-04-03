<?php

final class HeraldRuleSearchEngine extends PhabricatorApplicationSearchEngine {

  public function getResultTypeDescription() {
    return pht('Herald Rules');
  }

  public function getApplicationClassName() {
    return 'PhabricatorHeraldApplication';
  }

  public function buildSavedQueryFromRequest(AphrontRequest $request) {
    $saved = new PhabricatorSavedQuery();

    $saved->setParameter(
      'authorPHIDs',
      $this->readUsersFromRequest($request, 'authors'));

    $saved->setParameter('contentType', $request->getStr('contentType'));
    $saved->setParameter('ruleType', $request->getStr('ruleType'));
    $saved->setParameter(
      'disabled',
      $this->readBoolFromRequest($request, 'disabled'));

    return $saved;
  }

  public function buildQueryFromSavedQuery(PhabricatorSavedQuery $saved) {
    $query = id(new HeraldRuleQuery());

    $author_phids = $saved->getParameter('authorPHIDs');
    if ($author_phids) {
      $query->withAuthorPHIDs($author_phids);
    }

    $content_type = $saved->getParameter('contentType');
    $content_type = idx($this->getContentTypeValues(), $content_type);
    if ($content_type) {
      $query->withContentTypes(array($content_type));
    }

    $rule_type = $saved->getParameter('ruleType');
    $rule_type = idx($this->getRuleTypeValues(), $rule_type);
    if ($rule_type) {
      $query->withRuleTypes(array($rule_type));
    }

    $disabled = $saved->getParameter('disabled');
    if ($disabled !== null) {
      $query->withDisabled($disabled);
    }

    return $query;
  }

  public function buildSearchForm(
    AphrontFormView $form,
    PhabricatorSavedQuery $saved_query) {

    $author_phids = $saved_query->getParameter('authorPHIDs', array());
    $content_type = $saved_query->getParameter('contentType');
    $rule_type = $saved_query->getParameter('ruleType');

    $form
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setDatasource(new PhabricatorPeopleDatasource())
          ->setName('authors')
          ->setLabel(pht('Authors'))
          ->setValue($author_phids))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setName('contentType')
          ->setLabel(pht('Content Type'))
          ->setValue($content_type)
          ->setOptions($this->getContentTypeOptions()))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setName('ruleType')
          ->setLabel(pht('Rule Type'))
          ->setValue($rule_type)
          ->setOptions($this->getRuleTypeOptions()))
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setName('disabled')
          ->setLabel(pht('Rule Status'))
          ->setValue($this->getBoolFromQuery($saved_query, 'disabled'))
          ->setOptions(
            array(
              '' => pht('Show Enabled and Disabled Rules'),
              'false' => pht('Show Only Enabled Rules'),
              'true' => pht('Show Only Disabled Rules'),
            )));
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
        return $query->setParameter('disabled', false);
      case 'authored':
        return $query
          ->setParameter('authorPHIDs', array($viewer_phid))
          ->setParameter('disabled', false);
    }

    return parent::buildSavedQueryFromBuiltin($query_key);
  }

  private function getContentTypeOptions() {
    return array(
      '' => pht('(All Content Types)'),
    ) + HeraldAdapter::getEnabledAdapterMap($this->requireViewer());
  }

  private function getContentTypeValues() {
    return array_fuse(
      array_keys(
        HeraldAdapter::getEnabledAdapterMap($this->requireViewer())));
  }

  private function getRuleTypeOptions() {
    return array(
      '' => pht('(All Rule Types)'),
    ) + HeraldRuleTypeConfig::getRuleTypeMap();
  }

  private function getRuleTypeValues() {
    return array_fuse(array_keys(HeraldRuleTypeConfig::getRuleTypeMap()));
  }

  protected function getRequiredHandlePHIDsForResultList(
    array $rules,
    PhabricatorSavedQuery $query) {

    return mpull($rules, 'getAuthorPHID');
  }

  protected function renderResultList(
    array $rules,
    PhabricatorSavedQuery $query,
    array $handles) {
    assert_instances_of($rules, 'HeraldRule');

    $viewer = $this->requireViewer();

    $content_type_map = HeraldAdapter::getEnabledAdapterMap($viewer);

    $list = id(new PHUIObjectItemListView())
      ->setUser($viewer);
    foreach ($rules as $rule) {
      $id = $rule->getID();

      $item = id(new PHUIObjectItemView())
        ->setObjectName("H{$id}")
        ->setHeader($rule->getName())
        ->setHref($this->getApplicationURI("rule/{$id}/"));

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
      }

      $item->addAction(
        id(new PHUIListItemView())
          ->setHref($this->getApplicationURI("history/{$id}/"))
          ->setIcon('fa-file-text-o')
          ->setName(pht('Edit Log')));

      $content_type_name = idx($content_type_map, $rule->getContentType());
      $item->addAttribute(pht('Affects: %s', $content_type_name));

      $list->addItem($item);
    }

    return $list;
  }

}
