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
final class PhabricatorOAuthClientAuthorizationDeleteController
extends PhabricatorOAuthClientAuthorizationBaseController {

  public function processRequest() {
    $phid          = $this->getAuthorizationPHID();
    $title         = 'Delete OAuth Client Authorization';
    $request       = $this->getRequest();
    $current_user  = $request->getUser();
    $authorization = id(new PhabricatorOAuthClientAuthorization())
      ->loadOneWhere('phid = %s',
                     $phid);

    if (empty($authorization)) {
      return new Aphront404Response();
    }
    if ($authorization->getUserPHID() != $current_user->getPHID()) {
      $message = 'Access denied to client authorization with phid '.$phid.'. '.
                 'Only the user who authorized the client has permission to '.
                 'delete the authorization.';
      return id(new Aphront403Response())
        ->setForbiddenText($message);
    }

    if ($request->isFormPost()) {
      $authorization->delete();
      return id(new AphrontRedirectResponse())
        ->setURI('/oauthserver/clientauthorization/?notice=deleted');
    }

    $client_phid = $authorization->getClientPHID();
    $client      = id(new PhabricatorOAuthServerClient())
      ->loadOneWhere('phid = %s',
                     $client_phid);
    if ($client) {
      $client_name = phutil_escape_html($client->getName());
      $title .= ' for '.$client_name;
    } else {
      // the client does not exist so token is dead already (but
      // let's let the user clean this up anyway in that case)
      $client_name = '';
    }

    $dialog = new AphrontDialogView();
    $dialog->setUser($current_user);
    $dialog->setTitle($title);
    $dialog->appendChild(
      '<p>Are you sure you want to delete this client authorization?</p>'
    );
    $dialog->addSubmitButton();
    $dialog->addCancelButton($authorization->getEditURI());
    return id(new AphrontDialogResponse())->setDialog($dialog);

  }
}
