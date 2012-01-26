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
 * @group conduit
 */
class PhabricatorConduitTokenController extends PhabricatorConduitController {

  public function processRequest() {

    $user = $this->getRequest()->getUser();

    // Ideally we'd like to verify this, but it's fine to leave it unguarded
    // for now and verifying it would need some Ajax junk or for the user to
    // click a button or similar.
    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

    $old_token = id(new PhabricatorConduitCertificateToken())
      ->loadOneWhere(
        'userPHID = %s',
        $user->getPHID());
    if ($old_token) {
      $old_token->delete();
    }

    $token = id(new PhabricatorConduitCertificateToken())
      ->setUserPHID($user->getPHID())
      ->setToken(Filesystem::readRandomCharacters(40))
      ->save();

    $panel = new AphrontPanelView();
    $panel->setHeader('Certificate Install Token');
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);

    $panel->appendChild(
      '<p class="aphront-form-instructions">Copy and paste this token into '.
      'the prompt given to you by "arc install-certificate":</p>'.
      '<p style="padding: 0 0 1em 4em;">'.
        '<strong>'.phutil_escape_html($token->getToken()).'</strong>'.
      '</p>'.
      '<p class="aphront-form-instructions">arc will then complete the '.
      'install process for you.</p>');

    $this->setFilter('token');
    $this->setShowSideNav(false);

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'Certificate Install Token',
      ));
  }
}
