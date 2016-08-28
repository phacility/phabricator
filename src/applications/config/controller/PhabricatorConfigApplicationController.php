<?php

final class PhabricatorConfigApplicationController
  extends PhabricatorConfigController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $nav = $this->buildSideNavView();
    $nav->selectFilter('application/');

    $groups = PhabricatorApplicationConfigOptions::loadAll();
    $apps_list = $this->buildConfigOptionsList($groups, 'apps');

    $title = pht('Application Configuration');

    $apps = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setObjectList($apps_list);

    $crumbs = $this
      ->buildApplicationCrumbs()
      ->addTextCrumb(pht('Configuration'), $this->getApplicationURI())
      ->addTextCrumb(pht('Applications'));

    $view = id(new PHUITwoColumnView())
      ->setNavigation($nav)
      ->setMainColumn(array(
        $apps,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function buildConfigOptionsList(array $groups, $type) {
    assert_instances_of($groups, 'PhabricatorApplicationConfigOptions');

    $list = new PHUIObjectItemListView();
    $groups = msort($groups, 'getName');
    foreach ($groups as $group) {
      if ($group->getGroup() == $type) {
        $item = id(new PHUIObjectItemView())
          ->setHeader($group->getName())
          ->setHref('/config/group/'.$group->getKey().'/')
          ->addAttribute($group->getDescription())
          ->setImageIcon($group->getIcon());
        $list->addItem($item);
      }
    }

    return $list;
  }

}
