<?php

final class PhamePostEditEngineLock
  extends PhabricatorEditEngineLock {

  public function willPromptUserForLockOverrideWithDialog(
    AphrontDialogView $dialog) {

    return $dialog
      ->setTitle(pht('Edit Locked Post'))
      ->appendParagraph(
          pht('Comments are disabled for this post. Edit it anyway?'))
      ->addSubmitButton(pht('Edit Post'));
  }

  public function willBlockUserInteractionWithDialog(
    AphrontDialogView $dialog) {

    return $dialog
      ->setTitle(pht('Post Locked'))
      ->appendParagraph(
        pht('You can not interact with this post because it is locked.'));
  }

  public function getLockedObjectDisplayText() {
    return pht('Comments have been disabled for this post.');
  }

}
