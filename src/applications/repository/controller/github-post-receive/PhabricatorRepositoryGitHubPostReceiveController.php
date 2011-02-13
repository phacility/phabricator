<?php

/*
 * Copyright 2011 Facebook, Inc.
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


class PhabricatorRepositoryGitHubPostReceiveController
  extends PhabricatorRepositoryController {

  public function shouldRequireLogin() {
    return false;
  }

  private $id;
  private $token;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
    $this->token = $data['token'];
  }

  public function processRequest() {
    $request = $this->getRequest();

    $repo = id(new PhabricatorRepository())->load($this->id);
    if (!$repo) {
      return new Aphront404Response();
    }

    if ($repo->getDetail('github-token') != $this->token) {
      return new Aphront400Response();
    }

    if (!$request->isHTTPPost()) {
      return id(new AphrontFileResponse())
        ->setMimeType('text/plain')
        ->setContent(
          "Put this URL in your GitHub configuration. Accessing it directly ".
          "won't do anything!");
    }

    $notification = new PhabricatorRepositoryGitHubNotification();
    $notification->setRepositoryPHID($repo->getPHID());
    $notification->setRemoteAddress($_SERVER['REMOTE_ADDR']);
    $notification->setPayload($request->getStr('payload', ''));
    $notification->save();

    return id(new AphrontFileResponse())
      ->setMimeType('text/plain')
      ->setContent('OK');
  }

}
