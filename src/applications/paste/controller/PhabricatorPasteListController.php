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

    if ($engine->isBuiltinQuery($this->queryKey)) {
      $saved_query = $engine->buildSavedQueryFromBuiltin($this->queryKey);
    } else {
      $saved_query = id(new PhabricatorSavedQueryQuery())
        ->setViewer($user)
        ->withQueryKeys(array($this->queryKey))
        ->executeOne();

      if (!$saved_query) {
        return new Aphront404Response();
      }
    }

    $query = id(new PhabricatorPasteSearchEngine())
      ->buildQueryFromSavedQuery($saved_query);

    $filter = $nav->selectFilter(
      'query/'.$saved_query->getQueryKey(),
      'filter/advanced');

    $pager = new AphrontCursorPagerView();
    $pager->readFromRequest($request);
    $pastes = $query->setViewer($request->getUser())
      ->needContent(true)
      ->executeWithCursorPager($pager);

    $list = $this->buildPasteList($pastes);
    $list->setPager($pager);
    $list->setNoDataString(pht("No results found for this query."));

    if ($this->queryKey !== null || $filter == "filter/advanced") {
      $form = id(new AphrontFormView())
        ->setNoShading(true)
        ->setUser($user);

      $engine->buildSearchForm($form, $saved_query);

      $submit = id(new AphrontFormSubmitControl())
        ->setValue(pht('Execute Query'));

      if ($filter == 'filter/advanced') {
        $submit->addCancelButton(
          '/search/edit/'.$saved_query->getQueryKey().'/',
          pht('Save Custom Query...'));
      }

      $form->appendChild($submit);

      $filter_view = id(new AphrontListFilterView())->appendChild($form);
      $nav->appendChild($filter_view);
    }

    $nav->appendChild($list);

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
