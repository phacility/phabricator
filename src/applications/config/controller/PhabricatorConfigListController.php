<?php

final class PhabricatorConfigListController
  extends PhabricatorConfigController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $nav = $this->buildSideNavView();
    $nav->selectFilter('/');

    $groups = PhabricatorApplicationConfigOptions::loadAll();
    $core_list = $this->buildConfigOptionsList($groups, 'core');
    $core_list = $this->buildConfigBoxView(pht('Core'), $core_list);

    $title = pht('Core Settings');
    $header = $this->buildHeaderView($title);

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb($title)
      ->setBorder(true);

    $content = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setNavigation($nav)
      ->setFixed(true)
      ->setMainColumn($core_list);

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
          ->setBackground('bg-blue');
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
