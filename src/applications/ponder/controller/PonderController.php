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

abstract class PonderController extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {

    $page = $this->buildStandardPageView();
    $page->setApplicationName('Ponder!');
    $page->setBaseURI('/ponder/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xE2\x97\xB3");
    $page->appendChild($view);
    $page->setSearchDefaultScope(PhabricatorSearchScope::SCOPE_QUESTIONS);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

  protected function buildSideNavView(PonderQuestion $question = null) {
    $side_nav = new AphrontSideNavFilterView();
    $side_nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $side_nav->addLabel('Create');
    $side_nav->addFilter('question/ask', 'Ask a Question');

    $side_nav->addSpacer();

    $side_nav->addLabel('Questions');
    $side_nav->addFilter('feed', 'All Questions');

    $side_nav->addSpacer();

    $side_nav->addLabel('Profile');
    $side_nav->addFilter('profile', 'User Profile');

    return $side_nav;
  }

}
