<?php

final class ManiphestTaskEditEngineLock
  extends PhabricatorEditEngineLock {

  public function willPromptUserForLockOverrideWithDialog(
    AphrontDialogView $dialog) {

    return $dialog
      ->setTitle(pht('Edit Locked Task'))
      ->appendParagraph(pht('This task is locked. Edit it anyway?'))
      ->addSubmitButton(pht('Override Task Lock'));
  }

  public function willBlockUserInteractionWithDialog(
    AphrontDialogView $dialog) {

    return $dialog
      ->setTitle(pht('Task Locked'))
      ->appendParagraph(
        pht('You can not interact with this task because it is locked.'));
  }

  public function getLockedObjectDisplayText() {
    return pht('This task has been locked.');
  }

}
