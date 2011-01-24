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

class ConduitAPI_conduit_connect_Method extends ConduitAPIMethod {

  public function getMethodDescription() {
    return "Connect a session-based client.";
  }

  public function defineParamTypes() {
    return array(
      'client'              => 'required string',
      'clientVersion'       => 'required int',
      'clientDescription'   => 'optional string',
      'user'                => 'optional string',
    );
  }

  public function defineReturnType() {
    return 'dict<string, any>';
  }

  public function defineErrorTypes() {
    return array(
      "ERR-BAD-VERSION" =>
        "Client/server version mismatch. Update your client.",
      "ERR-UNKNOWN-CLIENT" =>
        "Client is unknown.",
      "ERR-UPDATE-ARC" =>
        "Arcanist is now open source! Update your scripts/aliases to use ".
        "'/home/engshare/devtools/arcanist/bin/arc' if you're running from ".
        "a Facebook host, or see ".
        "<http://www.intern.facebook.com/intern/wiki/index.php/Arcanist> for ".
        "laptop instructions.",
    );
  }

  protected function execute(ConduitAPIRequest $request) {

    $client = $request->getValue('client');
    $client_version = (int)$request->getValue('clientVersion');
    $client_description = (string)$request->getValue('clientDescription');

    // Log the connection, regardless of the outcome of checks below.
    $connection = new PhabricatorConduitConnectionLog();
    $connection->setClient($client);
    $connection->setClientVersion($client_version);
    $connection->setClientDescription($client_description);
    $connection->setUsername((string)$request->getValue('user'));
    $connection->save();

    switch ($client) {
      case 'arc':
        $server_version = 2;
        switch ($client_version) {
          case 1:
            throw new ConduitException('ERR-UPDATE-ARC');
          case $server_version:
            break;
          default:
            throw new ConduitException('ERR-BAD-VERSION');
        }
        break;
      default:
        throw new ConduitException('ERR-UNKNOWN-CLIENT');
    }

    return array(
      'connectionID' => $connection->getID(),
    );
  }

}
