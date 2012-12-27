<?php

final class PhabricatorConfigListController
  extends PhabricatorConfigController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $nav = $this->buildSideNavView();

    $pager = new AphrontCursorPagerView();
    $pager->readFromRequest($request);

    $config = new PhabricatorConfigFileSource('default');
    $list = $this->buildConfigList(array_keys($config->getAllKeys()));
    $list->setPager($pager);
    $list->setNoDataString(
      'No data. Something probably went wrong in reading the default config.');

    $header = id(new PhabricatorHeaderView())
      ->setHeader(pht('Configuration'));

    $nav->appendChild(
      array(
        $header,
        $list,
      ));

    $crumbs = $this
      ->buildApplicationCrumbs($nav)
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName(pht('Configuration'))
          ->setHref($this->getApplicationURI('filter/')));

    $nav->setCrumbs($crumbs);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => pht('Configuration'),
        'device' => true,
      )
    );
  }

  private function buildConfigList(array $keys) {
    $list = new PhabricatorObjectItemListView();

    foreach ($keys as $key) {
      $item = id(new PhabricatorObjectItemView())
        ->setHeader($key)
        ->setHref('/config/edit/'.$key)
        ->setObject($key);
      $list->addItem($item);
    }

    return $list;
  }

}
