<?php

final class PhabricatorConfigListController
  extends PhabricatorConfigController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $nav = $this->buildSideNavView();
    $nav->selectFilter('/');

    $groups = PhabricatorApplicationConfigOptions::loadAll();
    $core_list = $this->buildConfigOptionsList($groups, 'core');
    $apps_list = $this->buildConfigOptionsList($groups, 'apps');

    $title = pht('Phabricator Configuration');

    $core = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->appendChild($core_list);

    $apps = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Applications Configuration'))
      ->appendChild($apps_list);

    $nav->appendChild(
      array(
        $core,
        $apps,
      ));

    $crumbs = $this
      ->buildApplicationCrumbs()
      ->addTextCrumb(pht('Config'), $this->getApplicationURI());

    $nav->setCrumbs($crumbs);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $title,
      ));
  }

  private function buildConfigOptionsList(array $groups, $type) {
    assert_instances_of($groups, 'PhabricatorApplicationConfigOptions');

    $list = new PHUIObjectItemListView();
    $list->setStackable(true);
    $groups = msort($groups, 'getName');
    foreach ($groups as $group) {
      if ($group->getGroup() == $type) {
        $item = id(new PHUIObjectItemView())
          ->setHeader($group->getName())
          ->setHref('/config/group/'.$group->getKey().'/')
          ->addAttribute($group->getDescription())
          ->setFontIcon($group->getFontIcon());
        $list->addItem($item);
      }
    }

    return $list;
  }

}
