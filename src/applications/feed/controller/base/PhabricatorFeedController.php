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

abstract class PhabricatorFeedController extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {
    $page = $this->buildStandardPageView();

    $page->setApplicationName('Feed');
    $page->setBaseURI('/feed/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xE2\x88\x9E");
    $page->appendChild($view);

    $response = new AphrontWebpageResponse();

    if (!empty($data['public'])) {
      $page->setFrameable(true);
      $page->setShowChrome(false);
      $response->setFrameable(true);
    }

    return $response->setContent($page->render());
  }

}
