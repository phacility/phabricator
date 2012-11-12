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
      $handles = $this->loadViewerHandles(array($phid));
      $uri = $handles[$phid]->getURI();

      return id(new AphrontRedirectResponse())->setURI($uri);
    }

    $form = new AphrontFormView();
    $form->setUser($request->getUser());
    $form->setAction($this->getApplicationURI('/receive/'));
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
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);

    $nav = $this->buildSideNavView();
    $nav->selectFilter('receive');
    $nav->appendChild($panel);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => 'Receive Test',
      ));
  }

}
