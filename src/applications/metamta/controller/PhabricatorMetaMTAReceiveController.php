<?php

final class PhabricatorMetaMTAReceiveController
  extends PhabricatorMetaMTAController {

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    if ($request->isFormPost()) {
      $received = new PhabricatorMetaMTAReceivedMail();
      $header_content = array();
      $from = $request->getStr('sender');
      $to = $request->getStr('receiver');
      $uri = '/mail/received/';

      if (!empty($from)) {
        $header_content['from'] = $from;
      }

      if (preg_match('/.+@.+/', $to)) {
        $header_content['to'] = $to;
      } else {
        $receiver = PhabricatorMetaMTAReceivedMail::loadReceiverObject($to);

        if (!$receiver) {
          throw new Exception(pht("No such task or revision!"));
        }

        $hash = PhabricatorMetaMTAReceivedMail::computeMailHash(
        $receiver->getMailKey(),
        $user->getPHID());

        $header_content['to'] =
          $to.'+'.$user->getID().'+'.$hash.'@';
      }

      $received->setHeaders($header_content);
      $received->setBodies(
        array(
          'text' => $request->getStr('body'),
        ));

      // Make up some unique value, since this column isn't nullable.
      $received->setMessageIDHash(
        PhabricatorHash::digestForIndex(
          Filesystem::readRandomBytes(12)));

      $received->save();

      $received->processReceivedMail();

      return id(new AphrontRedirectResponse())->setURI($uri);
    }

    $form = new AphrontFormView();
    $form->setUser($request->getUser());
    $form->setAction($this->getApplicationURI('/receive/'));
    $form
      ->appendChild(hsprintf(
        '<p class="aphront-form-instructions">%s</p>',
        pht(
          'This form will simulate sending mail to an object '.
          'or an email address.')))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('From'))
          ->setName('sender'))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('To'))
          ->setName('receiver')
          ->setCaption(pht(
            'e.g. %s or %s',
            phutil_tag('tt', array(), 'D1234'),
            phutil_tag('tt', array(), 'bugs@example.com'))))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel(pht('Body'))
          ->setName('body'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Receive Mail')));

    $panel = new AphrontPanelView();
    $panel->setHeader(pht('Receive Email'));
    $panel->appendChild($form);
    $panel->setNoBackground();

    $nav = $this->buildSideNavView();
    $nav->selectFilter('receive');
    $nav->appendChild($panel);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => pht('Receive Test'),
        'device' => true,
      ));
  }

}

