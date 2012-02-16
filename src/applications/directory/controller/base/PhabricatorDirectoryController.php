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

abstract class PhabricatorDirectoryController extends PhabricatorController {

  public function shouldRequireAdmin() {
    // Most controllers here are admin-only, so default to locking them down.
    return true;
  }

  public function buildStandardPageResponse($view, array $data) {
    $page = $this->buildStandardPageView();

    $page->setBaseURI('/');
    $page->setTitle(idx($data, 'title'));

    $page->setGlyph("\xE2\x9A\x92");
    $page->appendChild($view);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

  public function buildNav() {
    $user = $this->getRequest()->getUser();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI('/'));

    $nav->addLabel('Phabricator');
    $nav->addFilter('home', 'Tactical Command', '/');
    $nav->addFilter('jump', 'Jump Nav');
    $nav->addFilter('feed', 'Feed');
    $nav->addSpacer();
    $nav->addLabel('Applications');

    $categories = $this->loadDirectoryCategories();

    foreach ($categories as $category) {
      $nav->addFilter(
        'directory/'.$category->getID(),
        $category->getName());
    }

    if ($user->getIsAdmin()) {
      $nav->addSpacer();
      $nav->addFilter('directory/edit', 'Edit Applications...');
    }

    return $nav;
  }

  protected function loadDirectoryCategories() {
    $categories = id(new PhabricatorDirectoryCategory())->loadAll();
    $categories = msort($categories, 'getSequence');
    return $categories;
  }

}
