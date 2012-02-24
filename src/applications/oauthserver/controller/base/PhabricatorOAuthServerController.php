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

abstract class PhabricatorOAuthServerController
extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {
    $user = $this->getRequest()->getUser();
    $page = $this->buildStandardPageView();
    $page->setApplicationName('OAuth Server');
    $page->setBaseURI('/oauthserver/');
    $page->setTitle(idx($data, 'title'));

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI('/oauthserver/'));
    $nav->addLabel('Client Authorizations');
    $nav->addFilter('clientauthorization',
                    'My Authorizations');
    $nav->addSpacer();
    $nav->addLabel('Clients');
    $nav->addFilter('client/create',
                    'Create Client');
    foreach ($this->getExtraClientFilters() as $filter) {
      $nav->addFilter($filter['url'],
                      $filter['label']);
    }
    $nav->addFilter('client',
                    'My Clients');
    $nav->selectFilter($this->getFilter(),
                       'clientauthorization');

    $nav->appendChild($view);

    $page->appendChild($nav);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

  protected function getFilter() {
    return 'clientauthorization';
  }

  protected function getExtraClientFilters() {
    return array();
  }

  protected function getHighlightPHIDs() {
    $phids   = array();
    $request = $this->getRequest();
    $edited  = $request->getStr('edited');
    $new     = $request->getStr('new');
    if ($edited) {
      $phids[$edited] = $edited;
    }
    if ($new) {
      $phids[$new] = $new;
    }
    return $phids;
  }

  protected function buildErrorView($error_message) {
    $error = new AphrontErrorView();
    $error->setSeverity(AphrontErrorView::SEVERITY_ERROR);
    $error->setTitle($error_message);

    return $error;
  }
}
