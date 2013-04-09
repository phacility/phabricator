<?php

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
      $mail->setOverrideNoSelfMailPreference(true);
      $mail->save();
      if ($request->getInt('immediately')) {
        $mail->sendNow();
      }

      return id(new AphrontRedirectResponse())
        ->setURI($this->getApplicationURI('/view/'.$mail->getID().'/'));
    }

    $failure_caption =
      pht("Enter a number to simulate that many consecutive send failures ".
      "brefore really attempting to deliver via the underlying MTA.");

    $doclink_href = PhabricatorEnv::getDoclink(
      'article/Configuring_Outbound_Email.html');

    $doclink = phutil_tag(
      'a',
      array(
        'href' => $doclink_href,
        'target' => '_blank',
      ),
    pht('Configuring Outbound Email'));
    $instructions = hsprintf(
      '<p class="aphront-form-instructions">%s</p>',
      pht(
        'This form will send a normal email using the settings you have '.
          'configured for Phabricator. For more information, see %s.',
        $doclink));

    $adapter = PhabricatorEnv::getEnvConfig('metamta.mail-adapter');
    $warning = null;
    if ($adapter == 'PhabricatorMailImplementationTestAdapter') {
      $warning = new AphrontErrorView();
      $warning->setTitle('Email is Disabled');
      $warning->setSeverity(AphrontErrorView::SEVERITY_WARNING);
      $warning->appendChild(phutil_tag(
        'p',
        array(),
        pht(
          'This installation of Phabricator is currently set to use %s to '.
            'deliver outbound email. This completely disables outbound email! '.
            'All outbound email will be thrown in a deep, dark hole until you '.
            'configure a real adapter.',
          phutil_tag(
            'tt',
            array(),
            'PhabricatorMailImplementationTestAdapter'))));
    }

    $phdlink_href = PhabricatorEnv::getDoclink(
      'article/Managing_Daemons_with_phd.html');

    $phdlink = phutil_tag(
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
          ->setLabel(pht('Adapter'))
          ->setValue($adapter))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('To'))
          ->setName('to')
          ->setDatasource('/typeahead/common/mailable/'))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('CC'))
          ->setName('cc')
          ->setDatasource('/typeahead/common/mailable/'))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Subject'))
          ->setName('subject'))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel(pht('Body'))
          ->setName('body'))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Mail Tags'))
          ->setName('mailtags')
          ->setCaption(pht(
            'Example: %s',
            phutil_tag(
              'tt',
              array(),
              'differential-cc, differential-comment'))))
      ->appendChild(
        id(new AphrontFormDragAndDropUploadControl())
          ->setLabel(pht('Attach Files'))
          ->setName('files')
          ->setActivatedClass('aphront-panel-view-drag-and-drop'))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Simulate Failures'))
          ->setName('failures')
          ->setCaption($failure_caption))
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->setLabel(pht('HTML'))
          ->addCheckbox('html', '1', 'Send as HTML email.'))
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->setLabel(pht('Bulk'))
          ->addCheckbox('bulk', '1', 'Send with bulk email headers.'))
      ->appendChild(
        id(new AphrontFormCheckboxControl())
          ->setLabel('Send Now')
          ->addCheckbox(
            'immediately',
            '1',
            pht('Send immediately. (Do not enqueue for daemons.)'),
            PhabricatorEnv::getEnvConfig('metamta.send-immediately'))
          ->setCaption(pht('Daemons can be started with %s.', $phdlink)))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Send Mail')));

    $panel = new AphrontPanelView();
    $panel->setHeader(pht('Send Email'));
    $panel->appendChild($form);
    $panel->setNoBackground();

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
        'title' => pht('Send Test'),
        'device' => true,
      ));
  }

}
