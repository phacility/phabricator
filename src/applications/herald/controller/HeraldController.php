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

abstract class HeraldController extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {
    $page = $this->buildStandardPageView();

    $page->setApplicationName('Herald');
    $page->setBaseURI('/herald/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xE2\x98\xBF");

    $page->appendChild($view);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

  protected function renderNav() {
    $nav = id(new AphrontSideNavFilterView())
      ->setBaseURI(new PhutilURI('/herald/'))
      ->addLabel('My Rules')
      ->addFilter('new', 'Create Rule');

    $rules_map = HeraldContentTypeConfig::getContentTypeMap();
    foreach ($rules_map as $key => $value) {
      $nav->addFilter("view/{$key}/personal", $value);
    }

    $nav
      ->addSpacer()
      ->addLabel('Global Rules');

    foreach ($rules_map as $key => $value) {
      $nav->addFilter("view/{$key}/global", $value);
    }

    $nav
      ->addSpacer()
      ->addLabel('Utilities')
      ->addFilter('test',       'Test Console')
      ->addFilter('transcript', 'Transcripts')
      ->addFilter('history',    'Edit Log');

    if ($this->getRequest()->getUser()->getIsAdmin()) {
      $nav
        ->addSpacer()
        ->addLabel('Admin');
      foreach ($rules_map as $key => $value) {
        $nav->addFilter("view/{$key}/all", $value);
      }
    }

    return $nav;
  }

}
