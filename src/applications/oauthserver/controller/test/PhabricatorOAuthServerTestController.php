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
final class PhabricatorOAuthServerTestController
extends PhabricatorAuthController {

  public function shouldRequireLogin() {
    return true;
  }

  public function shouldRequireAdmin() {
    return true;
  }

  public function processRequest() {
    $request      = $this->getRequest();
    $current_user = $request->getUser();
    $server       = new PhabricatorOAuthServer($current_user);

    $forms = array();
    $form = id(new AphrontFormView())
      ->setUser($current_user)
      ->appendChild(
        id(new AphrontFormStaticControl())
        ->setValue('Create Test Client'))
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel('Name')
        ->setName('name')
        ->setValue(''))
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel('Redirect URI')
        ->setName('redirect_uri')
        ->setValue(''))
      ->appendChild(
        id(new AphrontFormSubmitControl())
        ->setValue('Create Client'));
    $forms[] = $form;
    $result = array();
    if ($request->isFormPost()) {
      $name         = $request->getStr('name');
      $redirect_uri = $request->getStr('redirect_uri');
      $secret       = Filesystem::readRandomCharacters(32);
      $client       = new PhabricatorOAuthServerClient();
      $client->setName($name);
      $client->setSecret($secret);
      $client->setCreatorPHID($current_user->getPHID());
      $client->setRedirectURI($redirect_uri);
      $client->save();
      $id      = $client->getID();
      $phid    = $client->getPHID();
      $name    = phutil_escape_html($name);
      $results = array();
      $results[] = "New client named {$name} with secret {$secret}.";
      $results[] = "Client has id {$id} and phid {$phid}.";
      $result = implode('<br />', $results);
    }
    $title = 'Test OAuthServer Stuff';
    $panel = new AphrontPanelView();
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->setHeader($title);
    $panel->appendChild($result);
    $panel->appendChild($forms);

    return $this->buildStandardPageResponse(
            $panel,
      array('title' => $title));
  }
}
