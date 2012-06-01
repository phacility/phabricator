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
 * @group console
 */
final class DarkConsoleController extends PhabricatorController {

  protected $op;
  protected $data;

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $visible = $request->getStr('visible');
    if (strlen($visible)) {
      $user->setConsoleVisible((int)$visible);
      $user->save();
      return id(new AphrontAjaxResponse())->setDisableConsole(true);
    }

    $tab = $request->getStr('tab');
    if (strlen($tab)) {
      $user->setConsoleTab($tab);
      $user->save();
      return id(new AphrontAjaxResponse())->setDisableConsole(true);
    }

    if (PhabricatorEnv::getEnvConfig('darkconsole.enabled')) {
      $user->setConsoleEnabled(!$user->getConsoleEnabled());
      if ($user->getConsoleEnabled()) {
        $user->setConsoleVisible(true);
      }
      $user->save();
      if ($request->isAjax()) {
        return new AphrontRedirectResponse();
      } else {
        return id(new AphrontRedirectResponse())->setURI('/');
      }
    }

  }

}
