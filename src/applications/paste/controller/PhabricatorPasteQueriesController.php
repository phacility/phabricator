<?php

final class PhabricatorPasteQueriesController
  extends PhabricatorPasteController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $nav = $this->buildSideNavView("");
    $filter = $nav->getSelectedFilter();

    $table = new PhabricatorNamedQuery();
    $conn = $table->establishConnection('r');
    $data = queryfx_all(
      $conn,
      'SELECT * FROM %T WHERE userPHID=%s AND engineClassName=%s',
      $table->getTableName(),
      $user->getPHID(),
      'PhabricatorPasteSearchEngine');

    $list = new PhabricatorObjectItemListView();
    $list->setUser($user);

    foreach ($data as $key => $saved_query) {
      $date_created = phabricator_datetime($saved_query["dateCreated"], $user);
      $item = id(new PhabricatorObjectItemView())
        ->setHeader($saved_query["queryName"])
        ->setHref('/paste/query/'.$saved_query["queryKey"].'/')
        ->addByline(pht('Date Created: ').$date_created);
      $list->addItem($item);
    }

    $pager = new AphrontCursorPagerView();
    $pager->readFromRequest($request);

    $list->setPager($pager);

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
