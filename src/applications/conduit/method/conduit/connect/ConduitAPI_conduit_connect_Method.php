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

  public function shouldRequireAuthentication() {
    return false;
  }

  public function getMethodDescription() {
    return "Connect a session-based client.";
  }

  public function defineParamTypes() {
    return array(
      'client'              => 'required string',
      'clientVersion'       => 'required int',
      'clientDescription'   => 'optional string',
      'user'                => 'optional string',
      'authToken'           => 'optional int',
      'authSignature'       => 'optional string',
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
      "ERR-INVALID-USER" =>
        "The username you are attempting to authenticate with is not valid.",
      "ERR-INVALID-CERTIFICATE" =>
        "Your authentication certificate for this server is invalid.",
      "ERR-INVALID-TOKEN" =>
        "The challenge token you are authenticating with is outside of the ".
        "allowed time range. Either your system clock is out of whack or ".
        "you're executing a replay attack.",
      "ERR-NO-CERTIFICATE" =>
        "This server requires authentication but your client is not ".
        "configured with an authentication certificate."
    );
  }

  protected function execute(ConduitAPIRequest $request) {

    $client = $request->getValue('client');
    $client_version = (int)$request->getValue('clientVersion');
    $client_description = (string)$request->getValue('clientDescription');
    $username = (string)$request->getValue('user');

    // Log the connection, regardless of the outcome of checks below.
    $connection = new PhabricatorConduitConnectionLog();
    $connection->setClient($client);
    $connection->setClientVersion($client_version);
    $connection->setClientDescription($client_description);
    $connection->setUsername($username);
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

    $token = $request->getValue('authToken');
    $signature = $request->getValue('authSignature');

    $user = id(new PhabricatorUser())->loadOneWhere(
      'username = %s',
      $username);
    if (!$user) {
      throw new ConduitException('ERR-INVALID-USER');
    }

    $session_key = null;
    if ($token && $signature) {
      if (abs($token - time()) > 60 * 15) {
        throw new ConduitException('ERR-INVALID-TOKEN');
      }
      $valid = sha1($token.$user->getConduitCertificate());
      if ($valid != $signature) {
        throw new ConduitException('ERR-INVALID-CERTIFICATE');
      }

      $sessions = queryfx_all(
        $user->establishConnection('r'),
        'SELECT * FROM %T WHERE userPHID = %s AND type LIKE %>',
        PhabricatorUser::SESSION_TABLE,
        $user->getPHID(),
        'conduit-');

      $session_type = null;

      $sessions = ipull($sessions, null, 'type');
      for ($ii = 1; $ii <= 3; $ii++) {
        if (empty($sessions['conduit-'.$ii])) {
          $session_type = 'conduit-'.$ii;
          break;
        }
      }

      if (!$session_type) {
        $sessions = isort($sessions, 'sessionStart');
        $oldest = reset($sessions);
        $session_type = $oldest['type'];
      }

      $session_key = $user->establishSession($session_type);
    } else {
      throw new ConduitException('ERR-NO-CERTIFICATE');
    }

    return array(
      'connectionID'  => $connection->getID(),
      'sessionKey'    => $session_key,
      'userPHID'      => $user->getPHID(),
    );
  }

}
