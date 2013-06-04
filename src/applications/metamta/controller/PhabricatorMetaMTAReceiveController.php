<?php

final class PhabricatorMetaMTAReceiveController
  extends PhabricatorMetaMTAController {

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    if ($request->isFormPost()) {
      $received = new PhabricatorMetaMTAReceivedMail();
      $header_content = array(
        'Message-ID' => Filesystem::readRandomCharacters(12),
      );
      $from = $request->getStr('sender');
      $to = $request->getStr('receiver');
      $uri = '/mail/received/';

      if (!empty($from)) {
        $header_content['from'] = $from;
      } else {
        // If the user doesn't provide a "From" address, use their primary
        // address.
        $header_content['from'] = $user->loadPrimaryEmail()->getAddress();
      }

      if (preg_match('/.+@.+/', $to)) {
        $header_content['to'] = $to;
      } else {

        // We allow the user to use an object name instead of a real address
        // as a convenience. To build the mail, we build a similar message and
        // look for a receiver which will accept it.
        $pseudohash = PhabricatorObjectMailReceiver::computeMailHash('x', 'y');
        $pseudomail = id(new PhabricatorMetaMTAReceivedMail())
          ->setHeaders(
            array(
              'to' => $to.'+1+'.$pseudohash,
            ));

        $receivers = id(new PhutilSymbolLoader())
          ->setAncestorClass('PhabricatorMailReceiver')
          ->loadObjects();

        $receiver = null;
        foreach ($receivers as $possible_receiver) {
          if (!$possible_receiver->isEnabled()) {
            continue;
          }
          if (!$possible_receiver->canAcceptMail($pseudomail)) {
            continue;
          }
          $receiver = $possible_receiver;
          break;
        }

        if (!$receiver) {
          throw new Exception(
            "No configured mail receiver can accept mail to '{$to}'.");
        }

        if (!($receiver instanceof PhabricatorObjectMailReceiver)) {
          $class = get_class($receiver);
          throw new Exception(
            "Receiver '{$class}' accepts mail to '{$to}', but is not a ".
            "subclass of PhabricatorObjectMailReceiver.");
        }

        $object = $receiver->loadMailReceiverObject($to, $user);
        if (!$object) {
          throw new Exception("No such object '{$to}'!");
        }

        $hash = PhabricatorObjectMailReceiver::computeMailHash(
          $object->getMailKey(),
          $user->getPHID());

        $header_content['to'] = $to.'+'.$user->getID().'+'.$hash.'@test.com';
      }

      $received->setHeaders($header_content);
      $received->setBodies(
        array(
          'text' => $request->getStr('body'),
        ));

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

