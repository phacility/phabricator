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

final class PhabricatorUserSettingsController
  extends PhabricatorPeopleController {

  private $page;
  private $pages;

  public function willProcessRequest(array $data) {
    $this->page = idx($data, 'page');
  }

  public function processRequest() {

    $request = $this->getRequest();

    $oauth_providers = PhabricatorOAuthProvider::getAllProviders();
    $sidenav = $this->renderSideNav($oauth_providers);
    $this->page = $sidenav->selectFilter($this->page, 'account');

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
      case 'emailpref':
        $delegate = new PhabricatorUserEmailPreferenceSettingsPanelController(
          $request);
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
      case 'search':
        $delegate = new PhabricatorUserSearchSettingsPanelController($request);
        break;
      default:
        $delegate = new PhabricatorUserOAuthSettingsPanelController($request);
        $delegate->setOAuthProvider($oauth_providers[$this->page]);
        break;
    }

    $response = $this->delegateToController($delegate);

    if ($response instanceof AphrontView) {
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

  private function renderSideNav($oauth_providers) {
    $sidenav = new AphrontSideNavFilterView();
    $sidenav
      ->setBaseURI(new PhutilURI('/settings/page/'))
      ->addLabel('Account Information')
      ->addFilter('account', 'Account')
      ->addFilter('profile', 'Profile')
      ->addSpacer()
      ->addLabel('Email')
      ->addFilter('email', 'Email Addresses')
      ->addFilter('emailpref', 'Email Preferences')
      ->addSpacer()
      ->addLabel('Authentication');

    if (PhabricatorEnv::getEnvConfig('account.editable') &&
        PhabricatorEnv::getEnvConfig('auth.password-auth-enabled')) {
      $sidenav->addFilter('password', 'Password');
    }

    $sidenav->addFilter('conduit', 'Conduit Certificate');

    if (PhabricatorUserSSHKeysSettingsPanelController::isEnabled()) {
      $sidenav->addFilter('sshkeys', 'SSH Public Keys');
    }

    $sidenav->addSpacer();
    $sidenav->addLabel('Application Settings');
    $sidenav->addFilter('preferences', 'Display Preferences');
    $sidenav->addFilter('search', 'Search Preferences');

    $items = array();
    foreach ($oauth_providers as $provider) {
      if (!$provider->isProviderEnabled()) {
        continue;
      }
      $key = $provider->getProviderKey();
      $name = $provider->getProviderName();
      $items[$key] = $name.' Account';
    }

    if ($items) {
      $sidenav->addSpacer();
      $sidenav->addLabel('Linked Accounts');
      foreach ($items as $key => $name) {
        $sidenav->addFilter($key, $name);
      }
    }

    return $sidenav;
  }
}
