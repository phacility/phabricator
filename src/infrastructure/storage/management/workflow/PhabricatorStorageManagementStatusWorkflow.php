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

final class PhabricatorStorageManagementStatusWorkflow
  extends PhabricatorStorageManagementWorkflow {

  public function didConstruct() {
    $this
      ->setName('status')
      ->setExamples('**status** [__options__]')
      ->setSynopsis('Show patch application status.');
  }

  public function execute(PhutilArgumentParser $args) {
    $api = $this->getAPI();
    $patches = $this->getPatches();

    $applied = $api->getAppliedPatches();

    if ($applied === null) {
      echo phutil_console_format(
        "**Database Not Initialized**: Run **storage upgrade** to ".
        "initialize.\n");

      return 1;
    }

    $len = 0;
    foreach ($patches as $patch) {
      $len = max($len, strlen($patch->getFullKey()));
    }

    foreach ($patches as $patch) {
      printf(

        "% -".($len + 2)."s ".
        "%-".strlen("Not Applied")."s   ".
        "%-4s   ".
        "%s\n",

        $patch->getFullKey(),
        in_array($patch->getFullKey(), $applied)
          ? 'Applied'
          : 'Not Applied',
        $patch->getType(),
        $patch->getName());
    }

    return 0;
  }

}
