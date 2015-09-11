<?php

/**
 * Collection of routes on a site for an application.
 *
 * @task info Map Information
 * @task routing Routing
 */
final class AphrontRoutingMap extends Phobject {

  private $site;
  private $application;
  private $routes = array();


/* -(  Map Info  )----------------------------------------------------------- */


  public function setSite(AphrontSite $site) {
    $this->site = $site;
    return $this;
  }

  public function getSite() {
    return $this->site;
  }

  public function setApplication(PhabricatorApplication $application) {
    $this->application = $application;
    return $this;
  }

  public function getApplication() {
    return $this->application;
  }

  public function setRoutes(array $routes) {
    $this->routes = $routes;
    return $this;
  }

  public function getRoutes() {
    return $this->routes;
  }


/* -(  Routing  )------------------------------------------------------------ */


  /**
   * Find the route matching a path, if one exists.
   *
   * @param string Path to route.
   * @return AphrontRoutingResult|null Routing result, if path matches map.
   * @task routing
   */
  public function routePath($path) {
    $map = $this->getRoutes();

    foreach ($map as $route => $value) {
      $match = $this->tryRoute($route, $value, $path);
      if (!$match) {
        continue;
      }

      $result = $this->newRoutingResult();
      $application = $result->getApplication();

      $controller_class = $match['class'];
      $controller = newv($controller_class, array());
      $controller->setCurrentApplication($application);

      $result
        ->setController($controller)
        ->setURIData($match['data']);

      return $result;
    }

    return null;
  }


  /**
   * Test a sub-map to see if any routes match a path.
   *
   * @param string Path to route.
   * @param string Pattern from the map.
   * @param string Value from the map.
   * @return dict<string, wild>|null Match details, if path matches sub-map.
   * @task routing
   */
  private function tryRoute($route, $value, $path) {
    $has_submap = is_array($value);

    if (!$has_submap) {
      // If the value is a controller rather than a sub-map, any matching
      // route must completely consume the path.
      $pattern = '(^'.$route.'\z)';
    } else {
      $pattern = '(^'.$route.')';
    }

    $data = null;
    $ok = preg_match($pattern, $path, $data);
    if ($ok === false) {
      throw new Exception(
        pht(
          'Routing fragment "%s" is not a valid regular expression.',
          $route));
    }

    if (!$ok) {
      return null;
    }

    $path_match = $data[0];

    // Clean up the data. We only want to retain named capturing groups, not
    // the duplicated numeric captures.
    foreach ($data as $k => $v) {
      if (is_numeric($k)) {
        unset($data[$k]);
      }
    }

    if (!$has_submap) {
      return array(
        'class' => $value,
        'data' => $data,
      );
    }

    $sub_path = substr($path, strlen($path_match));
    foreach ($value as $sub_route => $sub_value) {
      $result = $this->tryRoute($sub_route, $sub_value, $sub_path);
      if ($result) {
        $result['data'] += $data;
        return $result;
      }
    }

    return null;
  }


  /**
   * Build a new routing result for this map.
   *
   * @return AphrontRoutingResult New, empty routing result.
   * @task routing
   */
  private function newRoutingResult() {
    return id(new AphrontRoutingResult())
      ->setSite($this->getSite())
      ->setApplication($this->getApplication());
  }

}
