<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * @task  routing URI Routing
 * @group aphront
 */
abstract class AphrontApplicationConfiguration {

  private $request;
  private $host;
  private $path;
  private $console;

  abstract public function getApplicationName();
  abstract public function getURIMap();
  abstract public function buildRequest();
  abstract public function build404Controller();
  abstract public function buildRedirectController($uri);

  final public function setRequest(AphrontRequest $request) {
    $this->request = $request;
    return $this;
  }

  final public function getRequest() {
    return $this->request;
  }

  final public function getConsole() {
    return $this->console;
  }

  final public function setConsole($console) {
    $this->console = $console;
    return $this;
  }

  final public function setHost($host) {
    $this->host = $host;
    return $this;
  }

  final public function getHost() {
    return $this->host;
  }

  final public function setPath($path) {
    $this->path = $path;
    return $this;
  }

  final public function getPath() {
    return $this->path;
  }

  final public function willBuildRequest() {
  }

  /**
   * Hook for synchronizing account information from OAuth workflows.
   *
   * @task hook
   */
  public function willAuthenticateUserWithOAuth(
    PhabricatorUser $user,
    PhabricatorUserOAuthInfo $oauth_info,
    PhabricatorOAuthProvider $provider) {
    return;
  }


/* -(  URI Routing  )-------------------------------------------------------- */


  /**
   * Using builtin and application routes, build the appropriate
   * @{class:AphrontController} class for the request. To route a request, we
   * test the URI against all builtin routes from @{method:getURIMap}, then
   * against all application routes from installed
   * @{class:PhabricatorApplication}s.
   *
   * If we match a route, we construct the controller it points at, build it,
   * and return it.
   *
   * If we fail to match a route, but the current path is missing a trailing
   * "/", we try routing the same path with a trailing "/" and do a redirect
   * if that has a valid route. The idea is to canoncalize URIs for consistency,
   * but avoid breaking noncanonical URIs that we can easily salvage.
   *
   * NOTE: We only redirect on GET. On POST, we'd drop parameters and most
   * likely mutate the request implicitly, and a bad POST usually indicates a
   * programming error rather than a sloppy typist.
   *
   * If the failing path already has a trailing "/", or we can't route the
   * version with a "/", we call @{method:build404Controller}, which build a
   * fallback @{class:AphrontController}.
   *
   * @return pair<AphrontController,dict> Controller and dictionary of request
   *                                      parameters.
   * @task routing
   */
  final public function buildController() {
    $request = $this->getRequest();
    $path = $request->getPath();

    list($controller, $uri_data) = $this->buildControllerForPath($path);
    if (!$controller) {
      if (!preg_match('@/$@', $path)) {
        // If we failed to match anything but don't have a trailing slash, try
        // to add a trailing slash and issue a redirect if that resolves.
        list($controller, $uri_data) = $this->buildControllerForPath($path.'/');

        // NOTE: For POST, just 404 instead of redirecting, since the redirect
        // will be a GET without parameters.

        if ($controller && !$request->isHTTPPost()) {
          $uri = $request->getRequestURI()->setPath($path.'/');
          return $this->buildRedirectController($uri);
        }
      }
      return $this->build404Controller();
    }

    return array($controller, $uri_data);
  }


  /**
   * Map a specific path to the corresponding controller. For a description
   * of routing, see @{method:buildController}.
   *
   * @return pair<AphrontController,dict> Controller and dictionary of request
   *                                      parameters.
   * @task routing
   */
  final public function buildControllerForPath($path) {
    $maps = array();
    $maps[] = array(null, $this->getURIMap());

    $applications = PhabricatorApplication::getAllInstalledApplications();
    foreach ($applications as $application) {
      $maps[] = array($application, $application->getRoutes());
    }

    $current_application = null;
    $controller_class = null;
    foreach ($maps as $map_info) {
      list($application, $map) = $map_info;

      $mapper = new AphrontURIMapper($map);
      list($controller_class, $uri_data) = $mapper->mapPath($path);

      if ($controller_class) {
        if ($application) {
          $current_application = $application;
        }
        break;
      }
    }

    if (!$controller_class) {
      return array(null, null);
    }

    $request = $this->getRequest();

    $controller = newv($controller_class, array($request));
    if ($current_application) {
      $controller->setCurrentApplication($current_application);
    }

    return array($controller, $uri_data);
  }
}
