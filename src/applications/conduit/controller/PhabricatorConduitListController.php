<?php

/**
 * @group conduit
 */
final class PhabricatorConduitListController
  extends PhabricatorConduitController
  implements PhabricatorApplicationSearchResultsControllerInterface {

  private $queryKey;

  public function willProcessRequest(array $data) {
    $this->queryKey = idx($data, 'queryKey');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $controller = id(new PhabricatorApplicationSearchController($request))
      ->setQueryKey($this->queryKey)
      ->setSearchEngine(new PhabricatorConduitSearchEngine())
      ->setNavigation($this->buildSideNavView());
    return $this->delegateToController($controller);
  }

  public function renderResultsList(
    array $methods,
    PhabricatorSavedQuery $query) {
    assert_instances_of($methods, 'ConduitAPIMethod');

    $viewer = $this->getRequest()->getUser();

    $out = array();

    $last = null;
    $list = null;
    foreach ($methods as $method) {
      $app = $method->getApplicationName();
      if ($app !== $last) {
        $last = $app;
        if ($list) {
          $out[] = $list;
        }
        $list = id(new PHUIObjectItemListView());

        $app_object = $method->getApplication();
        if ($app_object) {
          $app_name = $app_object->getName();
        } else {
          $app_name = $app;
        }
      }

      $method_name = $method->getAPIMethodName();

      $item = id(new PHUIObjectItemView())
        ->setHeader($method_name)
        ->setHref($this->getApplicationURI('method/'.$method_name.'/'))
        ->addAttribute($method->getMethodDescription());

      switch ($method->getMethodStatus()) {
        case ConduitAPIMethod::METHOD_STATUS_STABLE:
          break;
        case ConduitAPIMethod::METHOD_STATUS_UNSTABLE:
          $item->addIcon('warning-grey', pht('Unstable'));
          $item->setBarColor('yellow');
          break;
        case ConduitAPIMethod::METHOD_STATUS_DEPRECATED:
          $item->addIcon('warning', pht('Deprecated'));
          $item->setBarColor('red');
          break;
      }

      $list->addItem($item);
    }

    if ($list) {
      $out[] = $list;
    }

    return $out;
  }

}
