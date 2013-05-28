<?php

final class PhabricatorPasteQueriesController
  extends PhabricatorPasteController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $engine = id(new PhabricatorPasteSearchEngine())
      ->setViewer($user);

    $nav = $this->buildSideNavView();
    $nav->selectFilter('savedqueries');

    $named_queries = id(new PhabricatorNamedQueryQuery())
      ->setViewer($user)
      ->withUserPHIDs(array($user->getPHID()))
      ->withEngineClassNames(array(get_class($engine)))
      ->execute();

    $named_queries += $engine->getBuiltinQueries();

    $list = new PhabricatorObjectItemListView();
    $list->setUser($user);

    foreach ($named_queries as $named_query) {
      $date_created = phabricator_datetime(
        $named_query->getDateCreated(),
        $user);

      $item = id(new PhabricatorObjectItemView())
        ->setHeader($named_query->getQueryName())
        ->setHref($engine->getQueryResultsPageURI($named_query->getQueryKey()));

      if ($named_query->getIsBuiltin()) {
        $item->addIcon('lock-grey', pht('Builtin'));
        $item->setBarColor('grey');
      } else {
        $item->addIcon('none', $date_created);
        $item->addAction(
          id(new PhabricatorMenuItemView())
            ->setIcon('delete')
            ->setHref('/search/delete/'.$named_query->getQueryKey().'/')
            ->setWorkflow(true));
        $item->addAction(
          id(new PhabricatorMenuItemView())
            ->setIcon('edit')
            ->setHref('/search/edit/'.$named_query->getQueryKey().'/'));
      }

      $list->addItem($item);
    }

    $list->setNoDataString(pht("No results found for this query."));

    $nav->appendChild(
      array(
        $list,
      ));

    $crumbs = $this
      ->buildApplicationCrumbs($nav)
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName(pht("Saved Queries"))
          ->setHref($this->getApplicationURI('/savedqueries/')));

    $nav->setCrumbs($crumbs);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => pht("Saved Queries"),
        'device' => true,
        'dust' => true,
      ));
  }

}
