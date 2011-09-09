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

class PhabricatorUserSettingsController extends PhabricatorPeopleController {

  private $page;
  private $pages;

  public function willProcessRequest(array $data) {
    $this->page = idx($data, 'page');
  }

  public function processRequest() {

    $request = $this->getRequest();

    $this->pages = array(
      'account'     => 'Account',
      'profile'     => 'Profile',
      'email'       => 'Email',
      'password'    => 'Password',
      'preferences' => 'Preferences',
      'conduit'     => 'Conduit Certificate',
    );

    if (!PhabricatorEnv::getEnvConfig('account.editable') ||
        !PhabricatorEnv::getEnvConfig('auth.password-auth-enabled')) {
      unset($this->pages['password']);
    }

    if (PhabricatorUserSSHKeysSettingsPanelController::isEnabled()) {
      $this->pages['sshkeys'] = 'SSH Public Keys';
    }

    $oauth_providers = PhabricatorOAuthProvider::getAllProviders();
    foreach ($oauth_providers as $provider) {
      if (!$provider->isProviderEnabled()) {
        continue;
      }
      $key = $provider->getProviderKey();
      $name = $provider->getProviderName();
      $this->pages[$key] = $name.' Account';
    }

    if (empty($this->pages[$this->page])) {
      $this->page = key($this->pages);
    }

    switch ($this->page) {
      case 'account':
        $delegate = new PhabricatorUserAccountSettingsPanelController($request);
        break;
      case 'profile':
        $delegate = new PhabricatorUserProfileSettingsPanelController($request);
        break;
      case 'email':
        $delegate = new PhabricatorUserEmailSettingsPanelController($request);
        break;
      case 'password':
        $delegate = new PhabricatorUserPasswordSettingsPanelController(
          $request);
        break;
      case 'conduit':
        $delegate = new PhabricatorUserConduitSettingsPanelController($request);
        break;
      case 'sshkeys':
        $delegate = new PhabricatorUserSSHKeysSettingsPanelController($request);
        break;
      case 'preferences':
        $delegate = new PhabricatorUserPreferenceSettingsPanelController(
          $request);
        break;
      default:
        if (empty($this->pages[$this->page])) {
          return new Aphront404Response();
        }
        $delegate = new PhabricatorUserOAuthSettingsPanelController($request);
        $delegate->setOAuthProvider($oauth_providers[$this->page]);
    }

    $response = $this->delegateToController($delegate);

    if ($response instanceof AphrontView) {
      $sidenav = $this->renderSideNav();
      $sidenav->appendChild($response);
      return $this->buildStandardPageResponse(
        $sidenav,
        array(
          'title' => 'Account Settings',
        ));
    } else {
      return $response;
    }
  }

  private function renderSideNav() {
    $sidenav = new AphrontSideNavView();
    foreach ($this->pages as $page => $name) {
      $sidenav->addNavItem(
        phutil_render_tag(
          'a',
          array(
            'href' => '/settings/page/'.$page.'/',
            'class' => ($page == $this->page)
              ? 'aphront-side-nav-selected'
              : null,
          ),
          phutil_escape_html($name)));
    }
    return $sidenav;
  }
}
