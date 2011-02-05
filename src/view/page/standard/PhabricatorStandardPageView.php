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

class PhabricatorStandardPageView extends AphrontPageView {

  private $baseURI;
  private $applicationName;
  private $tabs = array();
  private $selectedTab;
  private $glyph;
  private $bodyContent;
  private $request;

  public function setRequest($request) {
    $this->request = $request;
    return $this;
  }

  public function getRequest() {
    return $this->request;
  }

  public function setApplicationName($application_name) {
    $this->applicationName = $application_name;
    return $this;
  }

  public function getApplicationName() {
    return $this->applicationName;
  }

  public function setBaseURI($base_uri) {
    $this->baseURI = $base_uri;
    return $this;
  }

  public function getBaseURI() {
    return $this->baseURI;
  }

  public function setTabs(array $tabs, $selected_tab) {
    $this->tabs = $tabs;
    $this->selectedTab = $selected_tab;
    return $this;
  }

  public function getTitle() {
    return $this->getGlyph().' '.parent::getTitle();
  }


  protected function willRenderPage() {

    if (!$this->getRequest()) {
      throw new Exception(
        "You must set the Request to render a PhabricatorStandardPageView.");
    }

    $console = $this->getRequest()->getApplicationConfiguration()->getConsole();

    require_celerity_resource('phabricator-core-css');
    require_celerity_resource('phabricator-core-buttons-css');
    require_celerity_resource('phabricator-standard-page-view');

    require_celerity_resource('javelin-lib-dev');
    require_celerity_resource('javelin-workflow-dev');

    if ($console) {
      require_celerity_resource('aphront-dark-console-css');
      Javelin::initBehavior(
        'dark-console',
        array(
          'uri' => '/~/',
        ));
    }

    $this->bodyContent = $this->renderChildren();
  }


  protected function getHead() {
    $response = CelerityAPI::getStaticResourceResponse();
    return
      $response->renderResourcesOfType('css').
      '<script type="text/javascript">window.__DEV__=1;</script>'.
      '<script type="text/javascript" src="/rsrc/js/javelin/init.dev.js">'.
      '</script>';
  }

  public function setGlyph($glyph) {
    $this->glyph = $glyph;
    return $this;
  }

  public function getGlyph() {
    return $this->glyph;
  }

  protected function willSendResponse($response) {
    $console = $this->getRequest()->getApplicationConfiguration()->getConsole();
    if ($console) {
      $response = str_replace(
        '<darkconsole />',
        $console->render($this->getRequest()),
        $response);
    }
    return $response;
  }

  protected function getBody() {
    $console = $this->getRequest()->getApplicationConfiguration()->getConsole();

    $tabs = array();
    foreach ($this->tabs as $name => $tab) {
      $tabs[] = phutil_render_tag(
        'a',
        array(
          'href'  => idx($tab, 'href'),
          'class' => ($name == $this->selectedTab)
            ? 'phabricator-selected-tab'
            : null,
        ),
        phutil_escape_html(idx($tab, 'name')));
    }
    $tabs = implode('', $tabs);
    if ($tabs) {
      $tabs = '<span class="phabricator-head-tabs">'.$tabs.'</span>';
    }

    $login_stuff = null;
    $request = $this->getRequest();
    if ($request) {
      $user = $request->getUser();
      if ($user->getPHID()) {
        $login_stuff =
          'Logged in as '.phutil_escape_html($user->getUsername()).
          ' &middot; '.
          '<a href="/settings/">Settings</a>'.
          ' &middot; '.
          '<form action="/logout/" method="post" style="display: inline;">'.
            phutil_render_tag(
              'input',
              array(
                'type' => 'hidden',
                'name' => '__csrf__',
                'value' => $user->getCSRFToken(),
              )).
            phutil_render_tag(
              'input',
              array(
                'type' => 'hidden',
                'name' => '__form__',
                'value' => true,
              )).
            '<button class="link">Logout</button>'.
          '</form>';
      }
    }

    return
      ($console ? '<darkconsole />' : null).
      '<div class="phabricator-standard-page">'.
        '<div class="phabricator-standard-header">'.
          '<div class="phabricator-login-details">'.
            $login_stuff.
          '</div>'.
          '<a href="/">Phabricator</a> '.
          phutil_render_tag(
            'a',
            array(
              'href'  => $this->getBaseURI(),
              'class' => 'phabricator-head-appname',
            ),
            phutil_escape_html($this->getApplicationName())).
          $tabs.
        '</div>'.
        $this->bodyContent.
        '<div style="clear: both;"></div>'.
      '</div>';
  }

  protected function getTail() {
    $response = CelerityAPI::getStaticResourceResponse();
    return
      $response->renderResourcesOfType('js').
      $response->renderHTMLFooter();
  }

}
