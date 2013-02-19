<?php

final class PhabricatorMailingListsListController
  extends PhabricatorMailingListsController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();
    $offset = $request->getInt('offset', 0);

    $pager = new AphrontPagerView();
    $pager->setPageSize(250);
    $pager->setOffset($offset);
    $pager->setURI($request->getRequestURI(), 'offset');

    $list = new PhabricatorMetaMTAMailingList();
    $conn_r = $list->establishConnection('r');
    $data = queryfx_all(
      $conn_r,
      'SELECT * FROM %T
        ORDER BY name ASC
        LIMIT %d, %d',
        $list->getTableName(),
        $pager->getOffset(), $pager->getPageSize() + 1);
    $data = $pager->sliceResults($data);

    $nav = $this->buildSideNavView('all');

    $lists = $list->loadAllFromArray($data);

    $rows = array();
    foreach ($lists as $list) {
      $rows[] = array(
        $list->getName(),
        $list->getEmail(),
        phutil_tag(
          'a',
          array(
            'class' => 'button grey small',
            'href'  => $this->getApplicationURI('/edit/'.$list->getID().'/'),
          ),
          pht('Edit')),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        pht('Name'),
        pht('Email'),
        '',
      ));
    $table->setColumnClasses(
      array(
        null,
        'wide',
        'action',
      ));

    $crumbs = $this->buildApplicationCrumbs($this->buildSideNavView());
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('All Lists'))
        ->setHref($this->getApplicationURI()));
    $nav->setCrumbs($crumbs);

    $panel = new AphrontPanelView();
    $panel->appendChild($table);
    $panel->setHeader(pht('Mailing Lists'));
    $panel->appendChild($pager);
    $panel->setNoBackground();

    $nav->appendChild($panel);

    return $this->buildApplicationPage(
      array(
        $nav,
      ),
      array(
        'title' => pht('Mailing Lists'),
        'device' => true,
      ));
  }
}
