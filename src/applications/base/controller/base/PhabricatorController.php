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

abstract class PhabricatorController extends AphrontController {

  public function shouldRequireLogin() {
    return true;
  }

  final public function willBeginExecution() {

    $request = $this->getRequest();

    $user = new PhabricatorUser();

    $phusr = $request->getCookie('phusr');
    $phsid = $request->getCookie('phsid');

    if ($phusr && $phsid) {
      $info = queryfx_one(
        $user->establishConnection('r'),
        'SELECT u.* FROM %T u JOIN %T s ON u.phid = s.userPHID
          AND s.type = %s AND s.sessionKey = %s',
        $user->getTableName(),
        'phabricator_session',
        'web',
        $phsid);
      if ($info) {
        $user->loadFromArray($info);
      }
    }

    $request->setUser($user);

    if (PhabricatorEnv::getEnvConfig('darkconsole.enabled')) {
      if ($user->getConsoleEnabled() ||
          PhabricatorEnv::getEnvConfig('darkconsole.always-on')) {
        $console = new DarkConsoleCore();
        $request->getApplicationConfiguration()->setConsole($console);
      }
    }

    if ($this->shouldRequireLogin() && !$user->getPHID()) {
      throw new AphrontRedirectException('/login/');
    }
  }

  public function buildStandardPageView() {
    $view = new PhabricatorStandardPageView();
    $view->setRequest($this->getRequest());
    return $view;
  }

  public function buildStandardPageResponse($view, array $data) {
    $page = $this->buildStandardPageView();
    $page->appendChild($view);
    $response = new AphrontWebpageResponse();
    $response->setContent($page->render());
    return $response;
  }

}
