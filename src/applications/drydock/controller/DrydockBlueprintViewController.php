<?php

final class DrydockBlueprintViewController extends DrydockController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $blueprint = id(new DrydockBlueprint())->load($this->id);
    if (!$blueprint) {
      return new Aphront404Response();
    }

    $title = 'Blueprint '.$blueprint->getID().' '.$blueprint->getClassName();

    $header = id(new PHUIHeaderView())
      ->setHeader($title);

    $actions = $this->buildActionListView($blueprint);
    $properties = $this->buildPropertyListView($blueprint, $actions);

    $blueprint_uri = 'blueprint/'.$blueprint->getID().'/';
    $blueprint_uri = $this->getApplicationURI($blueprint_uri);

    $resources = id(new DrydockResourceQuery())
      ->withBlueprintPHIDs(array($blueprint->getPHID()))
      ->setViewer($user)
      ->execute();

    $resource_list = $this->buildResourceListView($resources);
    $resource_list->setNoDataString(pht('This blueprint has no resources.'));

    $pager = new AphrontPagerView();
    $pager->setURI(new PhutilURI($blueprint_uri), 'offset');
    $pager->setOffset($request->getInt('offset'));

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->setActionList($actions);
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Blueprint %d', $blueprint->getID())));

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
        $resource_list
      ),
      array(
        'device'  => true,
        'title'   => $title,
      ));

  }

  private function buildActionListView(DrydockBlueprint $blueprint) {
    $view = id(new PhabricatorActionListView())
      ->setUser($this->getRequest()->getUser())
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setObject($blueprint);

    $uri = '/blueprint/edit/'.$blueprint->getID().'/';
    $uri = $this->getApplicationURI($uri);

    $view->addAction(
      id(new PhabricatorActionView())
        ->setHref($uri)
        ->setName(pht('Edit Blueprint Policies'))
        ->setIcon('edit')
        ->setWorkflow(true)
        ->setDisabled(false));

    return $view;
  }

  private function buildPropertyListView(
    DrydockBlueprint $blueprint,
    PhabricatorActionListView $actions) {

    $view = new PHUIPropertyListView();
    $view->setActionList($actions);

    $view->addProperty(
      pht('Implementation'),
      $blueprint->getClassName());

    return $view;
  }

}
