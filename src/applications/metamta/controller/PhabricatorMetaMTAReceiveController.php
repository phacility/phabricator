<?php

final class PhabricatorMetaMTAReceiveController
  extends PhabricatorMetaMTAController {

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    if ($request->isFormPost()) {
      $receiver = PhabricatorMetaMTAReceivedMail::loadReceiverObject(
        $request->getStr('obj'));
      if (!$receiver) {
        throw new Exception(pht("No such task or revision!"));
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

      // Make up some unique value, since this column isn't nullable.
      $received->setMessageIDHash(
        PhabricatorHash::digestForIndex(
          Filesystem::readRandomBytes(12)));

      $received->save();

      $received->processReceivedMail();

      $phid = $receiver->getPHID();
      $handles = $this->loadViewerHandles(array($phid));
      $uri = $handles[$phid]->getURI();

      return id(new AphrontRedirectResponse())->setURI($uri);
    }

    $form = new AphrontFormView();
    $form->setUser($request->getUser());
    $form->setAction($this->getApplicationURI('/receive/'));
    $form
      ->appendChild(hsprintf(
        '<p class="aphront-form-instructions">%s</p>',
        pht('This form will simulate sending mail to an object.')))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('To'))
          ->setName('obj')
          ->setCaption(pht(
            'e.g. %s or %s',
            phutil_tag('tt', array(), 'D1234'),
            phutil_tag('tt', array(), 'T1234'))))
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
