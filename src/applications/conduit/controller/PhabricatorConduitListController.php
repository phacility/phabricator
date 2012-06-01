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
final class PhabricatorConduitListController
  extends PhabricatorConduitController {

  public function processRequest() {
    $method_groups = $this->getMethodFilters();
    $rows = array();
    foreach ($method_groups as $group => $methods) {
      foreach ($methods as $info) {
        switch ($info['status']) {
          case ConduitAPIMethod::METHOD_STATUS_DEPRECATED:
            $status = 'Deprecated';
            break;
          case ConduitAPIMethod::METHOD_STATUS_UNSTABLE:
            $status = 'Unstable';
            break;
          default:
            $status = null;
            break;
        }

        $rows[] = array(
          $group,
          phutil_render_tag(
            'a',
            array(
              'href' => '/conduit/method/'.$info['full_name'],
            ),
            phutil_escape_html($info['full_name'])),
          $info['description'],
          $status,
        );
        $group = null;
      }
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(array(
      'Group',
      'Name',
      'Description',
      'Status',
    ));
    $table->setColumnClasses(array(
      'pri',
      'pri',
      'wide',
      null,
    ));

    $panel = new AphrontPanelView();
    $panel->setHeader('Conduit Methods');
    $panel->appendChild($table);
    $panel->setWidth(AphrontPanelView::WIDTH_FULL);

    $utils = new AphrontPanelView();
    $utils->setHeader('Utilities');
    $utils->appendChild(
      '<ul>'.
      '<li><a href="/conduit/log/">Log</a> - Conduit Method Calls</li>'.
      '<li><a href="/conduit/token/">Token</a> - Certificate Install</li>'.
      '</ul>');
    $utils->setWidth(AphrontPanelView::WIDTH_FULL);

    $this->setShowSideNav(false);

    return $this->buildStandardPageResponse(
      array(
        $panel,
        $utils,
      ),
      array(
        'title' => 'Conduit Console',
      ));
  }

}
