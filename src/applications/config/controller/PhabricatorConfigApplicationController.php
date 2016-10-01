<?php

final class PhabricatorConfigApplicationController
  extends PhabricatorConfigController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    $nav = $this->buildSideNavView();
    $nav->selectFilter('application/');

    $groups = PhabricatorApplicationConfigOptions::loadAll();
    $apps_list = $this->buildConfigOptionsList($groups, 'apps');

    $title = pht('Application Settings');

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setProfileHeader(true);

    $crumbs = $this
      ->buildApplicationCrumbs()
      ->addTextCrumb(pht('Applications'))
      ->setBorder(true);

    $content = id(new PhabricatorConfigPageView())
      ->setHeader($header)
      ->setContent($apps_list);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setNavigation($nav)
      ->appendChild($content)
      ->addClass('white-background');
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
