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

final class PhabricatorStorageManagementDumpWorkflow
  extends PhabricatorStorageManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('dump')
      ->setExamples('**dump** [__options__]')
      ->setSynopsis('Dump all data in storage to stdout.');
  }

  public function execute(PhutilArgumentParser $args) {
    $api = $this->getAPI();
    $patches = $this->getPatches();

    $applied = $api->getAppliedPatches();
    if ($applied === null) {
      $namespace = $api->getNamespace();
      echo phutil_console_wrap(
        phutil_console_format(
          "**No Storage**: There is no database storage initialized in this ".
          "storage namespace ('{$namespace}'). Use '**storage upgrade**' to ".
          "initialize storage.\n"));
      return 1;
    }

    $databases = $api->getDatabaseList($patches);

    list($host, $port) = $this->getBareHostAndPort($api->getHost());

    $flag_password = '';

    $password = $api->getPassword();
    if ($password) {
      $password = $password->openEnvelope();
      if (strlen($password)) {
        $flag_password = csprintf('-p%s', $password);
      }
    }

    $flag_port = $port
      ? csprintf('--port %d', $port)
      : '';

    return phutil_passthru(

      'mysqldump --default-character-set=utf8 '.
      '-u %s %C -h %s %C --databases %Ls',

      $api->getUser(),
      $flag_password,
      $host,
      $flag_port,
      $databases);
  }

  private function getBareHostAndPort($host) {
    // Split out port information, since the command-line client requires a
    // separate flag for the port.
    $uri = new PhutilURI('mysql://'.$host);
    if ($uri->getPort()) {
      $port = $uri->getPort();
      $bare_hostname = $uri->getDomain();
    } else {
      $port = null;
      $bare_hostname = $host;
    }

    return array($bare_hostname, $port);
  }

}
