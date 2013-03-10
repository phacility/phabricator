<?php

final class PhabricatorConfigListController
  extends PhabricatorConfigController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $nav = $this->buildSideNavView();
    $nav->selectFilter('/');

    $groups = PhabricatorApplicationConfigOptions::loadAll();
    $list = $this->buildConfigOptionsList($groups);

    $title = pht('Phabricator Configuration');

    $header = id(new PhabricatorHeaderView())
      ->setHeader($title);

    $nav->appendChild(
      array(
        $header,
        $list,
      ));

    $crumbs = $this
      ->buildApplicationCrumbs()
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName(pht('Config'))
          ->setHref($this->getApplicationURI()));

    $nav->setCrumbs($crumbs);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $title,
        'device' => true,
        'dust' => true,
      ));
  }

  private function buildConfigOptionsList(array $groups) {
    assert_instances_of($groups, 'PhabricatorApplicationConfigOptions');

    $list = new PhabricatorObjectItemListView();
    $list->setStackable(true);
    $groups = msort($groups, 'getName');
    foreach ($groups as $group) {
      $item = id(new PhabricatorObjectItemView())
        ->setHeader($group->getName())
        ->setHref('/config/group/'.$group->getKey().'/')
        ->addAttribute($group->getDescription());
      $list->addItem($item);
    }

    return $list;
  }

}
