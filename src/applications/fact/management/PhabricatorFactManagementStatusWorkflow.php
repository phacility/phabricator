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

final class PhabricatorFactManagementStatusWorkflow
  extends PhabricatorFactManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('status')
      ->setSynopsis(pht('Show status of fact data.'))
      ->setArguments(array());
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $map = array(
      'raw' => new PhabricatorFactRaw(),
      'agg' => new PhabricatorFactAggregate(),
    );

    foreach ($map as $type => $table) {
      $conn = $table->establishConnection('r');
      $name = $table->getTableName();

      $row = queryfx_one(
        $conn,
        'SELECT COUNT(*) N FROM %T',
        $name);

      $n = $row['N'];

      switch ($type) {
        case 'raw':
          $desc = pht('There are %d raw fact(s) in storage.', $n);
          break;
        case 'agg':
          $desc = pht('There are %d aggregate fact(s) in storage.', $n);
          break;
      }

      $console->writeOut("%s\n", $desc);
    }

    return 0;
  }

}
