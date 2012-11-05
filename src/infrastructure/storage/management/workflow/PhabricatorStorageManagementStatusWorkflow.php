<?php

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
