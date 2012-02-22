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

/**
 * @group oauthserver
 */
abstract class PhabricatorOAuthClientAuthorizationBaseController
extends PhabricatorOAuthServerController {

  private $authorizationPHID;
  protected function getAuthorizationPHID() {
    return $this->authorizationPHID;
  }
  private function setAuthorizationPHID($phid) {
    $this->authorizationPHID = $phid;
    return $this;
  }

  public function shouldRequireLogin() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->setAuthorizationPHID(idx($data, 'phid'));
  }
}
