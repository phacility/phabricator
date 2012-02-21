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
final class PhabricatorOAuthServerAuthController
extends PhabricatorAuthController {

  public function shouldRequireLogin() {
    return true;
  }

  public function processRequest() {
    $request      = $this->getRequest();
    $current_user = $request->getUser();
    $server       = new PhabricatorOAuthServer($current_user);
    $client_phid  = $request->getStr('client_id');
    $scope        = $request->getStr('scope');
    $redirect_uri = $request->getStr('redirect_uri');
    $response     = new PhabricatorOAuthResponse();
    $errors       = array();

    if (!$client_phid) {
      return $response->setMalformed(
        'Required parameter client_id not specified.'
      );
    }
    $client = id(new PhabricatorOAuthServerClient())
      ->loadOneWhere('phid = %s', $client_phid);
    if (!$client) {
      return $response->setNotFound(
        'Client with id '.$client_phid.' not found. '
      );
    }

    $server->setClient($client);
    if ($server->userHasAuthorizedClient()) {
      $return_auth_code = true;
      $unguarded_write  = AphrontWriteGuard::beginScopedUnguardedWrites();
    } else if ($request->isFormPost()) {
      // TODO -- T848 (add scope to Phabricator OAuth)
      // should have some $scope based off of user submission here...!
      $scope = array(PhabricatorOAuthServerScope::SCOPE_WHOAMI => 1);
      $server->authorizeClient($scope);
      $return_auth_code = true;
      $unguarded_write  = null;
    } else {
      $return_auth_code = false;
      $unguarded_write  = null;
    }

    if ($return_auth_code) {
      // step 1 -- generate authorization code
      $auth_code =
        $server->generateAuthorizationCode();

      // step 2 -- error or return it
      if (!$auth_code) {
        $errors[] = 'Failed to generate an authorization code. '.
                    'Try again.';
      } else {
        $client_uri = new PhutilURI($client->getRedirectURI());
        if (!$redirect_uri) {
          $uri = $client_uri;
        } else {
          $redirect_uri = new PhutilURI($redirect_uri);
          if ($redirect_uri->getDomain() !=
              $client_uri->getDomain()) {
            $uri = $client_uri;
          } else {
            $uri = $redirect_uri;
          }
        }

        $uri->setQueryParam('code', $auth_code->getCode());
        return $response->setRedirect($uri);
      }
    }
    unset($unguarded_write);

    $error_view = null;
    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setTitle('Authorization Code Errors');
      $error_view->setErrors($errors);
    }

    $name  = phutil_escape_html($client->getName());
    $title = 'Authorize ' . $name . '?';
    $panel = new AphrontPanelView();
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->setHeader($title);

    // TODO -- T848 (add scope to Phabricator OAuth)
    // generally inform user what this means as this fleshes out
    $description =
      "Do want to authorize {$name} to access your ".
      "Phabricator account data?";

    $form = id(new AphrontFormView())
      ->setUser($current_user)
      ->appendChild(
        id(new AphrontFormStaticControl())
        ->setValue($description))
      ->appendChild(
        id(new AphrontFormSubmitControl())
        ->setValue('Authorize')
        ->addCancelButton('/'));
    // TODO -- T889 (make "cancel" do something more sensible)

    $panel->appendChild($form);

    return $this->buildStandardPageResponse(
      array($error_view,
            $panel),
      array('title' => $title));
  }
}
