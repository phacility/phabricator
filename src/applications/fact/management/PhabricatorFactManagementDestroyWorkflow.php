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

final class PhabricatorFactManagementDestroyWorkflow
  extends PhabricatorFactManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('destroy')
      ->setSynopsis(pht('Destroy all facts.'))
      ->setArguments(array());
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $question = pht(
      'Really destroy all facts? They will need to be rebuilt through '.
      'analysis, which may take some time.');

    $ok = $console->confirm($question, $default = false);
    if (!$ok) {
      return 1;
    }

    $tables = array();
    $tables[] = new PhabricatorFactRaw();
    $tables[] = new PhabricatorFactAggregate();
    foreach ($tables as $table) {
      $conn = $table->establishConnection('w');
      $name = $table->getTableName();

      $console->writeOut("%s\n", pht("Destroying table '%s'...", $name));

      queryfx(
        $conn,
        'TRUNCATE TABLE %T',
        $name);
    }

    $console->writeOut("%s\n", pht('Done.'));
  }

}
