<?php

final class PhabricatorConfigListController
  extends PhabricatorConfigController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $nav = $this->buildSideNavView();
    $nav->selectFilter('/');

    $groups = PhabricatorApplicationConfigOptions::loadAll();
    $core_list = $this->buildConfigOptionsList($groups, 'core');
    $apps_list = $this->buildConfigOptionsList($groups, 'apps');

    $title = pht('Phabricator Configuration');

    $core = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setObjectList($core_list);

    $apps = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Applications Configuration'))
      ->setObjectList($apps_list);

    $crumbs = $this
      ->buildApplicationCrumbs()
      ->addTextCrumb(pht('Config'), $this->getApplicationURI());

    $view = id(new PHUITwoColumnView())
      ->setNavigation($nav)
      ->setMainColumn(array(
        $core,
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
          ->setIcon($group->getIcon());
        $list->addItem($item);
      }
    }

    return $list;
  }

}
