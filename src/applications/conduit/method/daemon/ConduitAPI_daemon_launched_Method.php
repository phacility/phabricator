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
final class ConduitAPI_daemon_launched_Method extends ConduitAPIMethod {

  public function shouldRequireAuthentication() {
    // TODO: Lock this down once we build phantoms.
    return false;
  }

  public function shouldAllowUnguardedWrites() {
    return true;
  }

  public function getMethodDescription() {
    return "Used by daemons to log run status.";
  }

  public function defineParamTypes() {
    return array(
      'daemon'    => 'required string',
      'host'      => 'required string',
      'pid'       => 'required int',
      'argv'      => 'required string',
    );
  }

  public function defineReturnType() {
    return 'string';
  }

  public function defineErrorTypes() {
    return array(
    );
  }

  protected function execute(ConduitAPIRequest $request) {

    $daemon_log = new PhabricatorDaemonLog();
    $daemon_log->setDaemon($request->getValue('daemon'));
    $daemon_log->setHost($request->getValue('host'));
    $daemon_log->setPID($request->getValue('pid'));
    $daemon_log->setStatus(PhabricatorDaemonLog::STATUS_RUNNING);
    $daemon_log->setArgv(json_decode($request->getValue('argv')));

    $daemon_log->save();

    return $daemon_log->getID();
  }

}
