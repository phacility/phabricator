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

abstract class PhabricatorPeopleController extends PhabricatorController {

  public function buildSideNavView() {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $is_admin = $this->getRequest()->getUser()->getIsAdmin();

    if ($is_admin) {
      $nav->addLabel('Create Users');
      $nav->addFilter('edit', 'Create New User');
      if (PhabricatorEnv::getEnvConfig('ldap.auth-enabled') === true) {
        $nav->addFilter('ldap', 'Import from LDAP');
      }
      $nav->addSpacer();
    }

    $nav->addLabel('Directory');
    $nav->addFilter('people', 'User Directory', $this->getApplicationURI());

    if ($is_admin) {
      $nav->addSpacer();
      $nav->addLabel('Logs');
      $nav->addFilter('logs', 'Activity Logs');
    }

    return $nav;
  }

}
