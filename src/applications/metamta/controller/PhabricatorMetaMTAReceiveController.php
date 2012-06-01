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

final class PhabricatorMetaMTAReceiveController
  extends PhabricatorMetaMTAController {

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    if ($request->isFormPost()) {
      $receiver = PhabricatorMetaMTAReceivedMail::loadReceiverObject(
        $request->getStr('obj'));
      if (!$receiver) {
        throw new Exception("No such task or revision!");
      }

      $hash = PhabricatorMetaMTAReceivedMail::computeMailHash(
        $receiver->getMailKey(),
        $user->getPHID());

      $received = new PhabricatorMetaMTAReceivedMail();
      $received->setHeaders(
        array(
          'to' => $request->getStr('obj').'+'.$user->getID().'+'.$hash.'@',
        ));
      $received->setBodies(
        array(
          'text' => $request->getStr('body'),
        ));
      $received->save();

      $received->processReceivedMail();

      $phid = $receiver->getPHID();
      $handles = id(new PhabricatorObjectHandleData(array($phid)))
        ->loadHandles();
      $uri = $handles[$phid]->getURI();

      return id(new AphrontRedirectResponse())->setURI($uri);
    }

    $form = new AphrontFormView();
    $form->setUser($request->getUser());
    $form->setAction('/mail/receive/');
    $form
      ->appendChild(
        '<p class="aphront-form-instructions">This form will simulate '.
        'sending mail to an object.</p>')
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('To')
          ->setName('obj')
          ->setCaption('e.g. <tt>D1234</tt> or <tt>T1234</tt>'))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Body')
          ->setName('body'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Receive Mail'));

    $panel = new AphrontPanelView();
    $panel->setHeader('Receive Email');
    $panel->appendChild($form);
    $panel->setWidth(AphrontPanelView::WIDTH_WIDE);

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'Receive Mail',
      ));
  }

}
