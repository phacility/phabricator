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

abstract class DifferentialController extends PhabricatorController {

  protected function allowsAnonymousAccess() {
    return PhabricatorEnv::getEnvConfig('differential.anonymous-access');
  }

  public function buildStandardPageResponse($view, array $data) {

    require_celerity_resource('differential-core-view-css');

    $viewer_is_anonymous = !$this->getRequest()->getUser()->isLoggedIn();

    $page = $this->buildStandardPageView();
    $page->setApplicationName('Differential');
    $page->setBaseURI('/differential/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xE2\x9A\x99");
    $page->appendChild($view);
    $page->setSearchDefaultScope(PhabricatorSearchScope::SCOPE_OPEN_REVISIONS);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

}
