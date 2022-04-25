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

  protected function newEditEngineFields(PhabricatorDashboardPanel $panel) {
    $application_field =
      id(new PhabricatorDashboardQueryPanelApplicationEditField())
        ->setKey('class')
        ->setLabel(pht('Search For'))
        ->setTransactionType(
          PhabricatorDashboardQueryPanelApplicationTransaction::TRANSACTIONTYPE)
        ->setValue($panel->getProperty('class', ''));

    $application_id = $application_field->getControlID();

    $query_field =
      id(new PhabricatorDashboardQueryPanelQueryEditField())
        ->setKey('key')
        ->setLabel(pht('Query'))
        ->setApplicationControlID($application_id)
        ->setTransactionType(
          PhabricatorDashboardQueryPanelQueryTransaction::TRANSACTIONTYPE)
        ->setValue($panel->getProperty('key', ''));

    $limit_field = id(new PhabricatorIntEditField())
      ->setKey('limit')
      ->setLabel(pht('Limit'))
      ->setTransactionType(
        PhabricatorDashboardQueryPanelLimitTransaction::TRANSACTIONTYPE)
      ->setValue($panel->getProperty('limit'));

    return array(
      $application_field,
      $query_field,
      $limit_field,
    );
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

    // TODO: A small number of queries, including "Notifications" and "Search",
    // use an offset pager which has a slightly different API. Some day, we
    // should unify these.
    if ($pager instanceof PHUIPagerView) {
      $has_more = $pager->getHasMorePages();
    } else {
      $has_more = $pager->getHasMoreResults();
    }

    if ($has_more) {
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
          'The application search engine "%s" is unknown.',
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

  public function newHeaderEditActions(
    PhabricatorDashboardPanel $panel,
    PhabricatorUser $viewer,
    $context_phid) {
    $actions = array();

    $engine = $this->getSearchEngine($panel);

    $customize_uri = $engine->getCustomizeURI(
      $panel->getProperty('key'),
      $panel->getPHID(),
      $context_phid);

    $actions[] = id(new PhabricatorActionView())
      ->setIcon('fa-pencil-square-o')
      ->setName(pht('Customize Query'))
      ->setWorkflow(true)
      ->setHref($customize_uri);

    return $actions;
  }

}
