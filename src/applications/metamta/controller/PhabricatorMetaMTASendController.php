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

final class PhabricatorMetaMTASendController
  extends PhabricatorMetaMTAController {

  public function processRequest() {

    $request = $this->getRequest();

    if ($request->isFormPost()) {

      $mail = new PhabricatorMetaMTAMail();
      $mail->addTos($request->getArr('to'));
      $mail->addCCs($request->getArr('cc'));
      $mail->setSubject($request->getStr('subject'));
      $mail->setBody($request->getStr('body'));

      $files = $request->getArr('files');
      if ($files) {
        foreach ($files as $phid) {
          $file = id(new PhabricatorFile())->loadOneWhere('phid = %s', $phid);
          $mail->addAttachment(new PhabricatorMetaMTAAttachment(
            $file->loadFileData(),
            $file->getName(),
            $file->getMimeType()
          ));
        }
      }

      $mail->setFrom($request->getUser()->getPHID());
      $mail->setSimulatedFailureCount($request->getInt('failures'));
      $mail->setIsHTML($request->getInt('html'));
      $mail->setIsBulk($request->getInt('bulk'));
      $mail->setMailTags($request->getStrList('mailtags'));
      $mail->save();
      if ($request->getInt('immediately')) {
        $mail->sendNow();
      }

      return id(new AphrontRedirectResponse())
        ->setURI($this->getApplicationURI('/view/'.$mail->getID().'/'));
    }

    $failure_caption =
      "Enter a number to simulate that many consecutive send failures before ".
      "really attempting to deliver via the underlying MTA.";

    $doclink_href = PhabricatorEnv::getDoclink(
      'article/Configuring_Outbound_Email.html');

    $doclink = phutil_render_tag(
      'a',
      array(
        'href' => $doclink_href,
        'target' => '_blank',
      ),
    'Configuring Outbound Email');
    $instructions =
      '<p class="aphront-form-instructions">This form will send a normal '.
      'email using the settings you have configured for Phabricator. For more '.
      'information, see '.$doclink.'.</p>';

    $adapter = PhabricatorEnv::getEnvConfig('metamta.mail-adapter');
    $warning = null;
    if ($adapter == 'PhabricatorMailImplementationTestAdapter') {
      $warning = new AphrontErrorView();
      $warning->setTitle('Email is Disabled');
      $warning->setSeverity(AphrontErrorView::SEVERITY_WARNING);
      $warning->appendChild(
        '<p>This installation of Phabricator is currently set to use '.
        '<tt>PhabricatorMailImplementationTestAdapter</tt> to deliver '.
        'outbound email. This completely disables outbound email! All '.
        'outbound email will be thrown in a deep, dark hole until you '.
        'configure a real adapter.</p>');
    }

    $phdlink_href = PhabricatorEnv::getDoclink(
      'article/Managing_Daemons_with_phd.html');

    $phdlink = phutil_render_tag(
      'a',
      array(
        'href' => $phdlink_href,
        'target' => '_blank',
      ),
      '"phd start"');

    $form = new AphrontFormView();
    $form->setUser($request->getUser());
    $form
      ->appendChild($instructions)
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Adapter')
          ->setValue($adapter))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel('To')
          ->setName('to')
          ->setDatasource('/typeahead/common/mailable/'))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel('CC')
          ->setName('cc')
          ->setDatasource('/typeahead/common/mailable/'))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Subject')
          ->setName('subject'))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Body')
          ->setName('body'))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Mail Tags')
          ->setName('mailtags')
          ->setCaption(
            'Example: <tt>differential-cc, differential-comment</tt>'))
      ->appendChild(
        id(new AphrontFormDragAndDropUploadControl())
          ->setLabel('Attach Files')
          ->setName('files')
          ->setActivatedClass('aphront-panel-view-drag-and-drop'))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Simulate Failures')
          ->setName('failures')
          ->setCaption($failure_caption))
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->setLabel('HTML')
          ->addCheckbox('html', '1', 'Send as HTML email.'))
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->setLabel('Bulk')
          ->addCheckbox('bulk', '1', 'Send with bulk email headers.'))
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->setLabel('Send Now')
          ->addCheckbox(
            'immediately',
            '1',
            'Send immediately. (Do not enqueue for daemons.)',
            PhabricatorEnv::getEnvConfig('metamta.send-immediately'))
          ->setCaption('Daemons can be started with '.$phdlink.'.')
          )
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Send Mail'));

    $panel = new AphrontPanelView();
    $panel->setHeader('Send Email');
    $panel->appendChild($form);
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);

    $nav = $this->buildSideNavView();
    $nav->selectFilter('send');
    $nav->appendChild(
      array(
        $warning,
        $panel,
      ));

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => 'Send Test',
      ));
  }

}
