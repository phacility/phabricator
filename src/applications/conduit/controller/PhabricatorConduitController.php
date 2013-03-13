<?php

/**
 * @group conduit
 */
abstract class PhabricatorConduitController extends PhabricatorController {

  private $filter;
  protected $showSideNav;

  public function buildStandardPageResponse($view, array $data) {
    $page = $this->buildStandardPageView();

    $page->setApplicationName('Conduit');
    $page->setBaseURI('/conduit/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xE2\x87\xB5");

    if ($this->showSideNav()) {

      $nav = new AphrontSideNavFilterView();
      $nav->setBaseURI(new PhutilURI('/conduit/'));
      $method_filters = $this->getMethodFilters();
      foreach ($method_filters as $group => $methods) {
        $nav->addLabel($group);
        foreach ($methods as $method) {
          $method_name = $method['full_name'];

          $display_name = $method_name;
          switch ($method['status']) {
            case ConduitAPIMethod::METHOD_STATUS_DEPRECATED:
              $display_name = '('.$display_name.')';
              break;
          }

          $nav->addFilter('method/'.$method_name,
            $display_name);
        }
      }
      $nav->selectFilter($this->getFilter());
      $nav->appendChild($view);
      $body = $nav;
    } else {
      $body = $view;
    }
    $page->appendChild($body);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

  private function getFilter() {
    return $this->filter;
  }

  protected function setFilter($filter) {
    $this->filter = $filter;
    return $this;
  }

  private function showSideNav() {
    return $this->showSideNav !== false;
  }

  protected function setShowSideNav($show_side_nav) {
    $this->showSideNav = $show_side_nav;
    return $this;
  }

  protected function getAllMethodImplementationClasses() {
    $classes = id(new PhutilSymbolLoader())
      ->setAncestorClass('ConduitAPIMethod')
      ->setType('class')
      ->setConcreteOnly(true)
      ->selectSymbolsWithoutLoading();

    return array_values(ipull($classes, 'name'));
  }

  protected function getMethodFilters() {
    $classes = $this->getAllMethodImplementationClasses();
    $method_names = array();
    foreach ($classes as $method_class) {
      $method_name = ConduitAPIMethod::getAPIMethodNameFromClassName(
        $method_class);
      $group_name = head(explode('.', $method_name));

      $method_object = newv($method_class, array());

      $application = $method_object->getApplication();
      if ($application && !$application->isInstalled()) {
        continue;
      }

      $status = $method_object->getMethodStatus();

      $key = sprintf(
        '%02d %s %s',
        $this->getOrderForMethodStatus($status),
        $group_name,
        $method_name);

      $method_names[$key] = array(
        'full_name'   => $method_name,
        'group_name'  => $group_name,
        'status'      => $status,
        'description' => $method_object->getMethodDescription(),
      );
    }
    ksort($method_names);
    $method_names = igroup($method_names, 'group_name');
    ksort($method_names);

    return $method_names;
  }

  private function getOrderForMethodStatus($status) {
    $map = array(
      ConduitAPIMethod::METHOD_STATUS_STABLE      => 0,
      ConduitAPIMethod::METHOD_STATUS_UNSTABLE    => 1,
      ConduitAPIMethod::METHOD_STATUS_DEPRECATED  => 2,
    );
    return idx($map, $status, 0);
  }

}
