<?php

final class PhabricatorPasteListController extends PhabricatorPasteController {

  private $queryKey;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->queryKey = idx($data, 'queryKey', 'all');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $engine = id(new PhabricatorPasteSearchEngine())
      ->setViewer($user);

    if ($request->isFormPost()) {
      return id(new AphrontRedirectResponse())->setURI(
        $engine->getQueryResultsPageURI(
          $engine->buildSavedQueryFromRequest($request)->getQueryKey()));
    }

    $nav = $this->buildSideNavView();

    $named_query = null;
    $run_query = true;
    $query_key = $this->queryKey;
    if ($this->queryKey == 'advanced') {
      $run_query = false;
      $query_key = $request->getStr('query');
    }

    if ($engine->isBuiltinQuery($query_key)) {
      $saved_query = $engine->buildSavedQueryFromBuiltin($query_key);
      $named_query = $engine->getBuiltinQuery($query_key);
    } else if ($query_key) {
      $saved_query = id(new PhabricatorSavedQueryQuery())
        ->setViewer($user)
        ->withQueryKeys(array($query_key))
        ->executeOne();

      if (!$saved_query) {
        return new Aphront404Response();
      }

      $named_query = id(new PhabricatorNamedQueryQuery())
        ->setViewer($user)
        ->withQueryKeys(array($saved_query->getQueryKey()))
        ->withEngineClassNames(array(get_class($engine)))
        ->withUserPHIDs(array($user->getPHID()))
        ->executeOne();
    } else {
      $saved_query = $engine->buildSavedQueryFromRequest($request);
    }

    $filter = $nav->selectFilter(
      'query/'.$saved_query->getQueryKey(),
      'query/advanced');

    $form = id(new AphrontFormView())
      ->setNoShading(true)
      ->setUser($user);

    $engine->buildSearchForm($form, $saved_query);

    $submit = id(new AphrontFormSubmitControl())
      ->setValue(pht('Execute Query'));

    if ($run_query && !$named_query) {
      $submit->addCancelButton(
        '/search/edit/'.$saved_query->getQueryKey().'/',
        pht('Save Custom Query...'));
    }

    $form->appendChild($submit);
    $filter_view = id(new AphrontListFilterView())->appendChild($form);

    if ($run_query && $named_query) {
      if ($named_query->getIsBuiltin()) {
        $description = pht(
          'Showing results for query "%s".',
          $named_query->getQueryName());
      } else {
        $description = pht(
          'Showing results for saved query "%s".',
          $named_query->getQueryName());
      }

      $filter_view->setCollapsed(
        pht('Edit Query...'),
        pht('Hide Query'),
        $description,
        $this->getApplicationURI('query/advanced/?query='.$query_key));
    }

    $nav->appendChild($filter_view);

    if ($run_query) {
      $query = id(new PhabricatorPasteSearchEngine())
        ->buildQueryFromSavedQuery($saved_query);

      $pager = new AphrontCursorPagerView();
      $pager->readFromRequest($request);
      $pastes = $query->setViewer($request->getUser())
        ->needContent(true)
        ->executeWithCursorPager($pager);

      $list = $this->buildPasteList($pastes);
      $list->setPager($pager);
      $list->setNoDataString(pht("No results found for this query."));

      $nav->appendChild($list);
    }

    $crumbs = $this
      ->buildApplicationCrumbs($nav)
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName(pht("Pastes"))
          ->setHref($this->getApplicationURI('filter/'.$filter.'/')));

    $nav->setCrumbs($crumbs);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => pht("Pastes"),
        'device' => true,
        'dust' => true,
      ));
  }

  private function buildPasteList(array $pastes) {
    assert_instances_of($pastes, 'PhabricatorPaste');

    $user = $this->getRequest()->getUser();

    $this->loadHandles(mpull($pastes, 'getAuthorPHID'));

    $lang_map = PhabricatorEnv::getEnvConfig('pygments.dropdown-choices');

    $list = new PhabricatorObjectItemListView();
    $list->setUser($user);
    foreach ($pastes as $paste) {
      $created = phabricator_date($paste->getDateCreated(), $user);
      $author = $this->getHandle($paste->getAuthorPHID())->renderLink();
      $source_code = $this->buildSourceCodeView($paste, 5)->render();

      $source_code = phutil_tag(
        'div',
        array(
          'class' => 'phabricator-source-code-summary',
        ),
        $source_code);

      $line_count = count(explode("\n", $paste->getContent()));
      $line_count = pht(
        '%s Line(s)',
        new PhutilNumber($line_count));

      $title = nonempty($paste->getTitle(), pht('(An Untitled Masterwork)'));

      $item = id(new PhabricatorObjectItemView())
        ->setObjectName('P'.$paste->getID())
        ->setHeader($title)
        ->setHref('/P'.$paste->getID())
        ->setObject($paste)
        ->addByline(pht('Author: %s', $author))
        ->addIcon('none', $line_count)
        ->appendChild($source_code);

      $lang_name = $paste->getLanguage();
      if ($lang_name) {
        $lang_name = idx($lang_map, $lang_name, $lang_name);
        $item->addIcon('none', $lang_name);
      }

      $list->addItem($item);
    }

    return $list;
  }

}
