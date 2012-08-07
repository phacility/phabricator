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

final class PhabricatorRepositoryManagementPullWorkflow
  extends PhabricatorRepositoryManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('pull')
      ->setExamples('**pull** __repository__ ...')
      ->setSynopsis('Pull __repository__, named by callsign or PHID.')
      ->setArguments(
        array(
          array(
            'name'        => 'verbose',
            'help'        => 'Show additional debugging information.',
          ),
          array(
            'name'        => 'repos',
            'wildcard'    => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $names = $args->getArg('repos');
    $repos = PhabricatorRepository::loadAllByPHIDOrCallsign($names);

    if (!$repos) {
      throw new PhutilArgumentUsageException(
        "Specify one or more repositories to pull, by callsign or PHID.");
    }

    $console = PhutilConsole::getConsole();
    foreach ($repos as $repo) {
      $console->writeOut("Pulling '%s'...\n", $repo->getCallsign());

      $daemon = new PhabricatorRepositoryPullLocalDaemon(array());
      $daemon->setVerbose($args->getArg('verbose'));
      $daemon->pullRepository($repo);
    }

    $console->writeOut("Done.\n");

    return 0;
  }

}
