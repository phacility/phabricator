<?php

final class PhabricatorConfigApplicationController
  extends PhabricatorConfigController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $nav = $this->buildSideNavView();
    $nav->selectFilter('application/');

    $groups = PhabricatorApplicationConfigOptions::loadAll();
    $apps_list = $this->buildConfigOptionsList($groups, 'apps');
    $apps_list = $this->buildConfigBoxView(pht('Applications'), $apps_list);

    $title = pht('Application Settings');
    $header = $this->buildHeaderView($title);

    $content = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setNavigation($nav)
      ->setFixed(true)
      ->setMainColumn($apps_list);

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb($title)
      ->setBorder(true);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($content);
  }

  private function buildConfigOptionsList(array $groups, $type) {
    assert_instances_of($groups, 'PhabricatorApplicationConfigOptions');

    $list = new PHUIObjectItemListView();
    $list->setBig(true);
    $groups = msort($groups, 'getName');
    foreach ($groups as $group) {
      if ($group->getGroup() == $type) {
        $icon = id(new PHUIIconView())
          ->setIcon($group->getIcon())
          ->setBackground('bg-violet');
        $item = id(new PHUIObjectItemView())
          ->setHeader($group->getName())
          ->setHref('/config/group/'.$group->getKey().'/')
          ->addAttribute($group->getDescription())
          ->setImageIcon($icon);
        $list->addItem($item);
      }
    }

    return $list;
  }

}
