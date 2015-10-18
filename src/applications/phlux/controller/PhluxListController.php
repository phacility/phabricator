<?php

final class PhluxListController extends PhluxController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $pager = new AphrontCursorPagerView();
    $pager->readFromRequest($request);
    $query = id(new PhluxVariableQuery())
      ->setViewer($viewer);

    $vars = $query->executeWithCursorPager($pager);

    $view = new PHUIObjectItemListView();
    foreach ($vars as $var) {
      $key = $var->getVariableKey();

      $item = new PHUIObjectItemView();
      $item->setHeader($key);
      $item->setHref($this->getApplicationURI('/view/'.$key.'/'));
      $item->addIcon(
        'none',
        phabricator_datetime($var->getDateModified(), $viewer));

      $view->addItem($item);
    }

    $crumbs = $this->buildApplicationCrumbs();

    $title = pht('Variable List');

    $crumbs->addTextCrumb($title, $this->getApplicationURI());

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $view,
        $pager,
      ),
      array(
        'title'  => $title,
      ));
  }

}
