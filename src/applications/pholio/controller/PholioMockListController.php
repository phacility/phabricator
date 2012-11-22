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
 * @group pholio
 */
final class PholioMockListController extends PholioController {

  private $view;

  public function willProcessRequest(array $data) {
    $this->view = idx($data, 'view');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $query = id(new PholioMockQuery())
      ->setViewer($user);

    $nav = $this->buildSideNav();
    $filter = $nav->selectFilter('view/'.$this->view, 'view/all');

    switch ($filter) {
      case 'view/all':
      default:
        $title = 'All Mocks';
        break;
    }

    $pager = new AphrontCursorPagerView();
    $pager->readFromRequest($request);

    $mocks = $query->executeWithCursorPager($pager);

    $board = new PhabricatorPinboardView();
    foreach ($mocks as $mock) {
      $board->addItem(
        id(new PhabricatorPinboardItemView())
          ->setHeader($mock->getName())
          ->setURI('/M'.$mock->getID()));
    }

    $header = id(new PhabricatorHeaderView())
      ->setHeader($title);

    $content = array(
      $header,
      $board,
      $pager,
    );

    $nav->appendChild($content);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => $title,
      ));
  }

}
