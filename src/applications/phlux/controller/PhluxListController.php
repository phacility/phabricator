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
    $view->setFlush(true);
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

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText('Variables')
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($view);

    $title = pht('Variable List');
    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setHeaderIcon('fa-copy');

    $crumbs->addTextCrumb($title, $this->getApplicationURI());
    $crumbs->setBorder(true);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $box,
        $pager,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);

  }

}
