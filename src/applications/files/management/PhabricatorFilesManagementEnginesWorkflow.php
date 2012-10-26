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

final class PhabricatorFilesManagementEnginesWorkflow
  extends PhabricatorFilesManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('engines')
      ->setSynopsis('List available storage engines.')
      ->setArguments(array());
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();

    $engines = PhabricatorFile::buildAllEngines();
    if (!$engines) {
      throw new Exception("No storage engines are available.");
    }

    foreach ($engines as $engine) {
      $console->writeOut(
        "%s\n",
        $engine->getEngineIdentifier());
    }

    return 0;
  }

}
