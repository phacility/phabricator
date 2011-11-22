<?php

/*
 * Copyright 2011 Facebook, Inc.
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

abstract class PhabricatorDirectoryController extends PhabricatorController {

  public function shouldRequireAdmin() {
    // Most controllers here are admin-only, so default to locking them down.
    return true;
  }

  public function buildStandardPageResponse($view, array $data) {
    $page = $this->buildStandardPageView();

    $page->setApplicationName('Directory');
    $page->setBaseURI('/');
    $page->setTitle(idx($data, 'title'));

    if ($this->getRequest()->getUser()->getIsAdmin()) {
      $tabs = array(
        'categories' => array(
          'href' => '/directory/category/',
          'name' => 'Categories',
        ),
        'items' => array(
          'href' => '/directory/item/',
          'name' => 'Items',
        ),
      );
    } else {
      $tabs = array();
    }

    $page->setTabs(
      $tabs,
      idx($data, 'tab'));
    $page->setGlyph("\xE2\x9A\x92");
    $page->appendChild($view);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

}
