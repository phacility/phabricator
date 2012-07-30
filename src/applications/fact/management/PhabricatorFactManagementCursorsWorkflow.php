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

final class PhabricatorFactManagementCursorsWorkflow
  extends PhabricatorFactManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('cursors')
      ->setSynopsis(pht('Show a list of fact iterators and cursors.'))
      ->setExamples(
        "**cursors**\n".
        "**cursors** --reset __cursor__")
      ->setArguments(
        array(
          array(
            'name'    => 'reset',
            'param'   => 'cursor',
            'repeat'  => true,
            'help'    => 'Reset cursor __cursor__.',
          )
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $reset = $args->getArg('reset');
    if ($reset) {
      foreach ($reset as $name) {
        $cursor = id(new PhabricatorFactCursor())->loadOneWhere(
          'name = %s',
          $name);
        if ($cursor) {
          $console->writeOut("%s\n", pht("Resetting cursor %s...", $name));
          $cursor->delete();
        } else {
          $console->writeErr(
            "%s\n",
            pht("Cursor %s does not exist or is already reset.", $name));
        }
      }
      return 0;
    }

    $iterator_map = PhabricatorFactDaemon::getAllApplicationIterators();
    if (!$iterator_map) {
      $console->writeErr("%s\n", pht("No cursors."));
      return 0;
    }

    $cursors = id(new PhabricatorFactCursor())->loadAllWhere(
      'name IN (%Ls)',
      array_keys($iterator_map));
    $cursors = mpull($cursors, 'getPosition', 'getName');

    foreach ($iterator_map as $iterator_name => $iterator) {
      $console->writeOut(
        "%s (%s)\n",
        $iterator_name,
        idx($cursors, $iterator_name, 'start'));
    }

    return 0;
  }

}
