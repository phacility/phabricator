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
