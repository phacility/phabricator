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

final class PhabricatorOAuthUnlinkController extends PhabricatorAuthController {

  private $provider;

  public function willProcessRequest(array $data) {
    $this->provider = PhabricatorOAuthProvider::newProvider($data['provider']);
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $provider = $this->provider;

    if ($provider->isProviderLinkPermanent()) {
      throw new Exception(
        "You may not unlink accounts from this OAuth provider.");
    }

    $provider_key = $provider->getProviderKey();

    $oauth_info = id(new PhabricatorUserOAuthInfo())->loadOneWhere(
      'userID = %d AND oauthProvider = %s',
      $user->getID(),
      $provider_key);

    if (!$oauth_info) {
      return new Aphront400Response();
    }

    if (!$request->isDialogFormPost()) {
      $dialog = new AphrontDialogView();
      $dialog->setUser($user);
      $dialog->setTitle('Really unlink account?');
      $dialog->appendChild(
        '<p><strong>You will not be able to login</strong> using this account '.
        'once you unlink it. Continue?</p>');
      $dialog->addSubmitButton('Unlink Account');
      $dialog->addCancelButton('/settings/page/'.$provider_key.'/');

      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    $oauth_info->delete();

    return id(new AphrontRedirectResponse())
      ->setURI('/settings/page/'.$provider_key.'/');
  }

}
