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

abstract class PhabricatorConduitController extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {
    $page = new PhabricatorStandardPageView();

    $page->setApplicationName('Conduit');
    $page->setBaseURI('/conduit/');
    $page->setTitle(idx($data, 'title'));
    $page->setTabs(
      array(
        'console' => array(
          'href' => '/conduit/',
          'name' => 'Console',
        ),
        'logs' => array(
          'href' => '/conduit/log/',
          'name' => 'Logs',
        ),
      ),
      idx($data, 'tab'));
    $page->setGlyph("\xE2\x87\xB5");
    $page->appendChild($view);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

}
