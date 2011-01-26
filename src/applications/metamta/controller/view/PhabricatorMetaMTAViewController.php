<?php

/*
 * Copyright 2011 Facebook, Inc.
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

class PhabricatorMetaMTAViewController extends PhabricatorMetaMTAController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {

    $mail = id(new PhabricatorMetaMTAMail())->load($this->id);
    if (!$mail) {
      return new Aphront404Response();
    }

    $form = new AphrontFormView();
    $form->setAction('/mail/send/');
    $form
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Subject')
          ->setValue($mail->getSubject()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Created')
          ->setValue(date('F jS, Y g:i:s A', $mail->getDateCreated())))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Related PHID')
          ->setValue($mail->getRelatedPHID()))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Parameters')
          ->setValue(json_encode($mail->getParameters())))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton('/mail/'));

    $panel = new AphrontPanelView();
    $panel->setHeader('View Email');
    $panel->appendChild($form);
    $panel->setWidth(AphrontPanelView::WIDTH_WIDE);

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'View Mail',
      ));
  }

}
