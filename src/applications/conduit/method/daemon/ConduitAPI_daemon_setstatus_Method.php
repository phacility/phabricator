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
final class ConduitAPI_daemon_setstatus_Method extends ConduitAPIMethod {

  public function shouldRequireAuthentication() {
    // TODO: Lock this down once we build phantoms.
    return false;
  }

  public function shouldAllowUnguardedWrites() {
    return true;
  }

  public function getMethodDescription() {
    return "Used by daemons to update their status.";
  }

  public function defineParamTypes() {
    return array(
      'daemonLogID' => 'required string',
      'status'      => 'required enum<unknown, run, timeout, dead, exit>',
    );
  }

  public function defineReturnType() {
    return 'void';
  }

  public function defineErrorTypes() {
    return array(
      'ERR-INVALID-ID' => 'An invalid daemonLogID was provided.',
    );
  }

  protected function execute(ConduitAPIRequest $request) {

    $daemon_log = id(new PhabricatorDaemonLog())
      ->load($request->getValue('daemonLogID'));
    if (!$daemon_log) {
      throw new ConduitException('ERR-INVALID-ID');
    }
    $daemon_log->setStatus($request->getValue('status'));

    $daemon_log->save();
  }

}
