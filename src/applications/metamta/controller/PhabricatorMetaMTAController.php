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

abstract class PhabricatorMetaMTAController extends PhabricatorController {

  public function shouldRequireAdmin() {
    return true;
  }

  public function buildSideNavView() {
    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $nav->addLabel('Mail Logs');
    $nav->addFilter('sent', 'Sent Mail', $this->getApplicationURI());
    $nav->addFilter('received', 'Received Mail');

    $nav->addSpacer();

    if ($this->getRequest()->getUser()->getIsAdmin()) {
      $nav->addLabel('Diagnostics');
      $nav->addFilter('send', 'Send Test');
      $nav->addFilter('receive', 'Receive Test');
    }

    return $nav;
  }

}
