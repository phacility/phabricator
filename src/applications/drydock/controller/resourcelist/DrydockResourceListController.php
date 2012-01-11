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

final class DrydockResourceListController extends DrydockController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $nav = $this->buildSideNav('resourcelist');

    $pager = new AphrontPagerView();
    $pager->setURI(new PhutilURI('/drydock/resource/'), 'page');

    $data = id(new DrydockResource())->loadAllWhere(
      '1 = 1 ORDER BY id DESC LIMIT %d, %d',
      $pager->getOffset(),
      $pager->getPageSize() + 1);
    $data = $pager->sliceResults($data);

    $phids = mpull($data, 'getOwnerPHID');
    $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();

    $rows = array();
    foreach ($data as $resource) {
      $rows[] = array(
        $resource->getID(),
        ($resource->getOwnerPHID()
          ? $handles[$resource->getOwnerPHID()]->renderLink()
          : null),
        phutil_escape_html($resource->getType()),
        DrydockResourceStatus::getNameForStatus($resource->getStatus()),
        phutil_escape_html(nonempty($resource->getName(), 'Unnamed')),
        phabricator_datetime($resource->getDateCreated(), $user),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'ID',
        'Owner',
        'Type',
        'Status',
        'Resource',
        'Created',
      ));
    $table->setColumnClasses(
      array(
        '',
        '',
        '',
        '',
        'pri wide',
        'right',
      ));

    $panel = new AphrontPanelView();
    $panel->setHeader('Drydock Resources');

    $panel->addButton(
      phutil_render_tag(
        'a',
        array(
          'href' => '/drydock/resource/allocate/',
          'class' => 'green button',
        ),
        'Allocate Resource'));

    $panel->appendChild($table);
    $panel->appendChild($pager);

    $nav->appendChild($panel);

    return $this->buildStandardPageResponse(
      $nav,
      array(
        'title' => 'Resources',
      ));

  }

}
