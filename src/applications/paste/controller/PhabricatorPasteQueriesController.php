<?php

final class PhabricatorPasteQueriesController
  extends PhabricatorPasteController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $nav = $this->buildSideNavView();
    $nav->selectFilter('savedqueries');

    $named_queries = id(new PhabricatorNamedQueryQuery())
      ->setViewer($user)
      ->withUserPHIDs(array($user->getPHID()))
      ->withEngineClassNames(array('PhabricatorPasteSearchEngine'))
      ->execute();

    $list = new PhabricatorObjectItemListView();
    $list->setUser($user);

    foreach ($named_queries as $named_query) {
      $date_created = phabricator_datetime(
        $named_query->getDateCreated(),
        $user);

      $item = id(new PhabricatorObjectItemView())
        ->setHeader($named_query->getQueryName())
        ->setHref('/paste/query/'.$named_query->getQueryKey().'/')
        ->addIcon('none', $date_created)
        ->addAction(
          id(new PhabricatorMenuItemView())
            ->setIcon('edit')
            ->setHref('/search/edit/'.$named_query->getQueryKey().'/'));

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
