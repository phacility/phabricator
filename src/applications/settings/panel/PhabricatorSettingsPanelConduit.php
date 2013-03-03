<?php

final class PhabricatorSettingsPanelConduit
  extends PhabricatorSettingsPanel {

  public function getPanelKey() {
    return 'conduit';
  }

  public function getPanelName() {
    return pht('Conduit');
  }

  public function getPanelGroup() {
    return pht('Authentication');
  }

  public function processRequest(AphrontRequest $request) {
    $user = $request->getUser();

    if ($request->isFormPost()) {
      if (!$request->isDialogFormPost()) {
        $dialog = new AphrontDialogView();
        $dialog->setUser($user);
        $dialog->setTitle(pht('Really regenerate session?'));
        $dialog->setSubmitURI($this->getPanelURI());
        $dialog->addSubmitButton(pht('Regenerate'));
        $dialog->addCancelbutton($this->getPanelURI());
        $dialog->appendChild(phutil_tag('p', array(), pht(
          'Really destroy the old certificate? Any established '.
          'sessions will be terminated.')));

        return id(new AphrontDialogResponse())
          ->setDialog($dialog);
      }

      $conn = $user->establishConnection('w');
      queryfx(
        $conn,
        'DELETE FROM %T WHERE userPHID = %s AND type LIKE %>',
        PhabricatorUser::SESSION_TABLE,
        $user->getPHID(),
        'conduit');

      // This implicitly regenerates the certificate.
      $user->setConduitCertificate(null);
      $user->save();
      return id(new AphrontRedirectResponse())
        ->setURI($this->getPanelURI('?regenerated=true'));
    }

    if ($request->getStr('regenerated')) {
      $notice = new AphrontErrorView();
      $notice->setSeverity(AphrontErrorView::SEVERITY_NOTICE);
      $notice->setTitle(pht('Certificate Regenerated'));
      $notice->appendChild(phutil_tag(
        'p',
        array(),
        pht('Your old certificate has been destroyed and you have been issued '.
        'a new certificate. Sessions established under the old certificate '.
        'are no longer valid.')));
      $notice = $notice->render();
    } else {
      $notice = null;
    }

    $cert_form = new AphrontFormView();
    $cert_form
      ->setUser($user)
      ->appendChild(hsprintf(
        '<p class="aphront-form-instructions">%s</p>',
        pht('This certificate allows you to authenticate over Conduit, '.
          'the Phabricator API. Normally, you just run %s to install it.',
          hsprintf('<tt>%s</tt>', 'arc install-certificate'))))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel(pht('Certificate'))
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_SHORT)
          ->setValue($user->getConduitCertificate()));

    $cert = new AphrontPanelView();
    $cert->setHeader(pht('Arcanist Certificate'));
    $cert->appendChild($cert_form);
    $cert->setNoBackground();

    $regen_instruction = pht('You can regenerate this certificate, which '.
      'will invalidate the old certificate and create a new one.');

    $regen_form = new AphrontFormView();
    $regen_form
      ->setUser($user)
      ->setAction($this->getPanelURI())
      ->setWorkflow(true)
      ->appendChild(hsprintf(
        '<p class="aphront-form-instructions">%s</p>', $regen_instruction))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Regenerate Certificate')));

    $regen = new AphrontPanelView();
    $regen->setHeader(pht('Regenerate Certificate'));
    $regen->appendChild($regen_form);
    $regen->setNoBackground();

    return array(
      $notice,
      $cert,
      $regen,
    );
  }
}
