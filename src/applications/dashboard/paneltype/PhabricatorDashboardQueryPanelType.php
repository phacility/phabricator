<?php

final class PhabricatorDashboardQueryPanelType
  extends PhabricatorDashboardPanelType {

  public function getPanelTypeKey() {
    return 'query';
  }

  public function getPanelTypeName() {
    return pht('Query Panel');
  }

  public function getIcon() {
    return 'fa-search';
  }

  public function getPanelTypeDescription() {
    return pht(
      'Show results of a search query, like the most recently filed tasks or '.
      'revisions you need to review.');
  }

  public function getFieldSpecifications() {
    return array(
      'class' => array(
        'name' => pht('Search For'),
        'type' => 'search.application',
      ),
      'key' => array(
        'name' => pht('Query'),
        'type' => 'search.query',
        'control.application' => 'class',
      ),
      'limit' => array(
        'name' => pht('Limit'),
        'caption' => pht('Leave this blank for the default number of items.'),
        'type' => 'text',
      ),
    );
  }

  public function initializeFieldsFromRequest(
    PhabricatorDashboardPanel $panel,
    PhabricatorCustomFieldList $field_list,
    AphrontRequest $request) {

    $map = array();
    if (strlen($request->getStr('engine'))) {
      $map['class'] = $request->getStr('engine');
    }

    if (strlen($request->getStr('query'))) {
      $map['key'] = $request->getStr('query');
    }

    $full_map = array();
    foreach ($map as $key => $value) {
      $full_map["std:dashboard:core:{$key}"] = $value;
    }

    foreach ($field_list->getFields() as $field) {
      $field_key = $field->getFieldKey();
      if (isset($full_map[$field_key])) {
        $field->setValueFromStorage($full_map[$field_key]);
      }
    }
  }

  public function renderPanelContent(
    PhabricatorUser $viewer,
    PhabricatorDashboardPanel $panel,
    PhabricatorDashboardPanelRenderingEngine $engine) {

    $engine = $this->getSearchEngine($panel);

    $engine->setViewer($viewer);
    $engine->setContext(PhabricatorApplicationSearchEngine::CONTEXT_PANEL);

    $key = $panel->getProperty('key');
    if ($engine->isBuiltinQuery($key)) {
      $saved = $engine->buildSavedQueryFromBuiltin($key);
    } else {
      $saved = id(new PhabricatorSavedQueryQuery())
        ->setViewer($viewer)
        ->withEngineClassNames(array(get_class($engine)))
        ->withQueryKeys(array($key))
        ->executeOne();
    }

    if (!$saved) {
      throw new Exception(
        pht(
          'Query "%s" is unknown to application search engine "%s"!',
          $key,
          get_class($engine)));
    }

    $query = $engine->buildQueryFromSavedQuery($saved);
    $pager = $engine->newPagerForSavedQuery($saved);

    if ($panel->getProperty('limit')) {
      $limit = (int)$panel->getProperty('limit');
      if ($pager->getPageSize() !== 0xFFFF) {
        $pager->setPageSize($limit);
      }
    }

    $query->setReturnPartialResultsOnOverheat(true);

    $results = $engine->executeQuery($query, $pager);
    $results_view = $engine->renderResults($results, $saved);

    $is_overheated = $query->getIsOverheated();
    $overheated_view = null;
    if ($is_overheated) {
      $content = $results_view->getContent();

      $overheated_message =
        PhabricatorApplicationSearchController::newOverheatedError(
          (bool)$results);

      $overheated_warning = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
        ->setTitle(pht('Query Overheated'))
        ->setErrors(
          array(
            $overheated_message,
          ));

      $overheated_box = id(new PHUIBoxView())
        ->addClass('mmt mmb')
        ->appendChild($overheated_warning);

      $content = array($content, $overheated_box);
      $results_view->setContent($content);
    }

    if ($pager->getHasMoreResults()) {
      $item_list = $results_view->getObjectList();

      $more_href = $engine->getQueryResultsPageURI($key);
      if ($item_list) {
        $item_list->newTailButton()
          ->setHref($more_href);
      } else {
        // For search engines that do not return an object list, add a fake
        // one to the end so we can render a "View All Results" button that
        // looks like it does in normal applications. At time of writing,
        // several major applications like Maniphest (which has group headers)
        // and Feed (which uses custom rendering) don't return simple lists.

        $content = $results_view->getContent();

        $more_list = id(new PHUIObjectItemListView())
          ->setAllowEmptyList(true);

        $more_list->newTailButton()
          ->setHref($more_href);

        $content = array($content, $more_list);
        $results_view->setContent($content);
      }
    }

    return $results_view;
  }

  public function adjustPanelHeader(
    PhabricatorUser $viewer,
    PhabricatorDashboardPanel $panel,
    PhabricatorDashboardPanelRenderingEngine $engine,
    PHUIHeaderView $header) {

    $search_engine = $this->getSearchEngine($panel);
    $key = $panel->getProperty('key');
    $href = $search_engine->getQueryResultsPageURI($key);

    $icon = id(new PHUIIconView())
      ->setIcon('fa-search');

    $button = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('View All'))
      ->setIcon($icon)
      ->setHref($href)
      ->setColor(PHUIButtonView::GREY);

    $header->addActionLink($button);

    return $header;
  }

  private function getSearchEngine(PhabricatorDashboardPanel $panel) {
    $class = $panel->getProperty('class');
    $engine = PhabricatorApplicationSearchEngine::getEngineByClassName($class);
    if (!$engine) {
      throw new Exception(
        pht(
          'The application search engine "%s" is not known to Phabricator!',
          $class));
    }

    if (!$engine->canUseInPanelContext()) {
      throw new Exception(
        pht(
          'Application search engines of class "%s" can not be used to build '.
          'dashboard panels.',
          $class));
    }

    return $engine;
  }

}
