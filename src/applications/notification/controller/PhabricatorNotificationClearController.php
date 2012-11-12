<?php

final class PhabricatorNotificationClearController
  extends PhabricatorNotificationController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    if ($request->isDialogFormPost()) {
      $table = new PhabricatorFeedStoryNotification();

      queryfx(
        $table->establishConnection('w'),
        'UPDATE %T SET hasViewed = 1 WHERE
          userPHID = %s AND hasViewed = 0',
        $table->getTableName(),
        $user->getPHID());

      return id(new AphrontReloadResponse())
        ->setURI('/notification/');
    }

    $dialog = new AphrontDialogView();
    $dialog->setUser($user);
    $dialog->setTitle('Really mark all notifications as read?');
    $dialog->appendChild(
      "You can't ignore your problems forever, you know.");
    $dialog->addCancelButton('/notification/');
    $dialog->addSubmitButton('Mark All Read');

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }
}
