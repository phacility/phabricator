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
 * @group conduit
 */
abstract class PhabricatorConduitController extends PhabricatorController {

  private $filter;
  protected $showSideNav;

  public function buildStandardPageResponse($view, array $data) {
    $doclink = PhabricatorEnv::getDoclink(
      'article/Conduit_Technical_Documentation.html'
      );

    $page = $this->buildStandardPageView();

    $page->setApplicationName('Conduit');
    $page->setBaseURI('/conduit/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xE2\x87\xB5");
    $page->setTabs(array(
      'help' => array(
        'href' => $doclink,
        'name' => 'Help')
      ), null);

    if ($this->showSideNav()) {

      $nav = new AphrontSideNavFilterView();
      $nav->setBaseURI(new PhutilURI('/conduit/'));
      $first_filter = null;
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
          if (!$first_filter) {
            $first_filter = 'method/'.$method_name;
          }
        }
        $nav->addSpacer();
      }
      $nav->addLabel('Utilities');
      $nav->addFilter('log', 'Logs');
      $nav->addFilter('token', 'Token');
      $nav->selectFilter($this->getFilter(), $first_filter);
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

  private function getMethodFilters() {
    $classes = $this->getAllMethodImplementationClasses();
    $method_names = array();
    foreach ($classes as $method_class) {
      $method_name = ConduitAPIMethod::getAPIMethodNameFromClassName(
        $method_class);
      $group_name = head(explode('.', $method_name));

      $status = newv($method_class, array())->getMethodStatus();

      $key = sprintf(
        '%02d %s %s',
        $this->getOrderForMethodStatus($status),
        $group_name,
        $method_name);

      $method_names[$key] = array(
        'full_name'   => $method_name,
        'group_name'  => $group_name,
        'status'      => $status,
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
