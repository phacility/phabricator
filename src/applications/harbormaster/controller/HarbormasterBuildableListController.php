<?php

final class HarbormasterBuildableListController extends HarbormasterController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $items = array();

    $items[] = id(new PHUIListItemView())
      ->setType(PHUIListItemView::TYPE_LABEL)
      ->setName(pht('Builds'));

    $items[] = id(new PHUIListItemView())
      ->setType(PHUIListItemView::TYPE_LINK)
      ->setName(pht('Browse Builds'))
      ->setHref($this->getApplicationURI('build/'));

    $items[] = id(new PHUIListItemView())
      ->setType(PHUIListItemView::TYPE_LABEL)
      ->setName(pht('Build Plans'));

    $items[] = id(new PHUIListItemView())
      ->setType(PHUIListItemView::TYPE_LINK)
      ->setName(pht('Manage Build Plans'))
      ->setHref($this->getApplicationURI('plan/'));

    return id(new HarbormasterBuildableSearchEngine())
      ->setController($this)
      ->setNavigationItems($items)
      ->buildResponse();
  }

}
