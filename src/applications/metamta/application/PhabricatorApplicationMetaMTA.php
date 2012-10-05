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

final class PhabricatorApplicationMetaMTA extends PhabricatorApplication {

  public function getBaseURI() {
    return '/mail/';
  }

  public function getShortDescription() {
    return 'View Mail Logs';
  }

  public function getAutospriteName() {
    return 'mail';
  }

  public function getFlavorText() {
    return pht('Yo dawg, we heard you like MTAs.');
  }

  public function getApplicationGroup() {
    return self::GROUP_ADMIN;
  }

  public function getRoutes() {
    return array(
      $this->getBaseURI() => array(
        '' => 'PhabricatorMetaMTAListController',
        'send/' => 'PhabricatorMetaMTASendController',
        'view/(?P<id>[1-9]\d*)/' => 'PhabricatorMetaMTAViewController',
        'receive/' => 'PhabricatorMetaMTAReceiveController',
        'received/' => 'PhabricatorMetaMTAReceivedListController',
        'sendgrid/' => 'PhabricatorMetaMTASendGridReceiveController',
      ),
    );
  }

  public function getTitleGlyph() {
    return '@';
  }

}
