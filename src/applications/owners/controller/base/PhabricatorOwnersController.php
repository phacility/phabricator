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

abstract class PhabricatorOwnersController extends PhabricatorController {

  private $filter;

  private function getSideNavFilter() {
    return $this->filter;
  }
  protected function setSideNavFilter($filter) {
    $this->filter = $filter;
    return $this;
  }
  public function buildStandardPageResponse($view, array $data) {

    $page = $this->buildStandardPageView();

    $page->setApplicationName('Owners');
    $page->setBaseURI('/owners/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xE2\x98\x81");
    $nav = $this->renderSideNav();
    $nav->appendChild($view);
    $page->appendChild($nav);

    $doclink =
      PhabricatorEnv::getDoclink('article/Owners_Tool_User_Guide.html');
    $tabs = array(
      'help' => array(
        'href' => $doclink,
        'name' => 'Help',
      ),
    );
    $page->setTabs($tabs, null);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

  public function renderSideNav() {
    $package_views = array(
      array('name' => 'Owned',
            'key'  => 'view/owned'),
      array('name' => 'All',
            'key'  => 'view/all'),
    );

    $package_views =
      array_merge($this->getExtraPackageViews(),
                  $package_views);

    $base_uri = new PhutilURI('/owners/');
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseUri($base_uri);

    $nav->addLabel('Packages');
    $nav->addFilters($package_views);

    $nav->addSpacer();
    $nav->addLabel('Related Commits');
    $related_views = $this->getRelatedViews();
    $nav->addFilters($related_views);

    $nav->addSpacer();
    $nav->addLabel('Commits Need Attention');
    $attention_views = $this->getAttentionViews();
    $nav->addFilters($attention_views);

    $filter = $this->getSideNavFilter();
    $nav->selectFilter($filter, 'view/owned');

    return $nav;
  }

  protected function getExtraPackageViews() {
    return array();
  }

  protected function getRelatedViews() {
    $related_views = array(
      array('name' => 'By Package',
            'key'  => 'related/package'),
      array('name' => 'By Package Owner',
            'key'  => 'related/owner'),
          );

    return $related_views;
  }

  protected function getAttentionViews() {
    $attention_views = array(
      array('name' => 'By Package',
            'key'  => 'attention/package'),
      array('name' => 'By Package Owner',
            'key'  => 'attention/owner'),
          );

    return $attention_views;
  }

}
