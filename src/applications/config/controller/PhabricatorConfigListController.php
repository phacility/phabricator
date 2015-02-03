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

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->appendChild($list);

    $nav->appendChild(
      array(
        $box,
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

  private function buildConfigOptionsList(array $groups) {
    assert_instances_of($groups, 'PhabricatorApplicationConfigOptions');

    $list = new PHUIObjectItemListView();
    $list->setStackable(true);
    $groups = msort($groups, 'getName');
    foreach ($groups as $group) {
      $item = id(new PHUIObjectItemView())
        ->setHeader($group->getName())
        ->setHref('/config/group/'.$group->getKey().'/')
        ->addAttribute($group->getDescription())
        ->setFontIcon($group->getFontIcon());
      $list->addItem($item);
    }

    return $list;
  }

}
