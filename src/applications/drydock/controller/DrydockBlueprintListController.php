<?php

final class DrydockBlueprintListController extends DrydockController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $title = pht('Blueprints');

    $blueprint_header = id(new PHUIHeaderView())
      ->setHeader($title);

    $blueprints = id(new DrydockBlueprintQuery())
      ->setViewer($user)
      ->execute();

    $blueprint_list = $this->buildBlueprintListView($blueprints);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName($title)
        ->setHref($request->getRequestURI()));

    $crumbs->addAction(
      id(new PHUIListItemView())
        ->setName(pht('New Blueprint'))
        ->setHref($this->getApplicationURI('blueprint/create/'))
        ->setIcon('create'));

    $nav = $this->buildSideNav('blueprint');
    $nav->setCrumbs($crumbs);
    $nav->appendChild(
      array(
        $blueprint_header,
        $blueprint_list
      ));

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $title,
        'device' => true,
      ));

  }

  protected function buildBlueprintListView(array $blueprints) {
    assert_instances_of($blueprints, 'DrydockBlueprint');

    $user = $this->getRequest()->getUser();
    $view = new PHUIObjectItemListView();

    foreach ($blueprints as $blueprint) {
      $item = id(new PHUIObjectItemView())
        ->setHeader($blueprint->getClassName())
        ->setHref($this->getApplicationURI('/blueprint/'.$blueprint->getID()))
        ->setObjectName(pht('Blueprint %d', $blueprint->getID()));

      if ($blueprint->getImplementation()->isEnabled()) {
        $item->addAttribute(pht('Enabled'));
        $item->setBarColor('green');
      } else {
        $item->addAttribute(pht('Disabled'));
        $item->setBarColor('red');
      }

      $item->addAttribute($blueprint->getImplementation()->getDescription());

      $view->addItem($item);
    }

    return $view;
  }

}
