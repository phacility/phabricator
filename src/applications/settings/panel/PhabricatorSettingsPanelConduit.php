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
        $dialog->setTitle('Really regenerate session?');
        $dialog->setSubmitURI($this->getPanelURI());
        $dialog->addSubmitButton('Regenerate');
        $dialog->addCancelbutton($this->getPanelURI());
        $dialog->appendChild(
          '<p>Really destroy the old certificate? Any established '.
          'sessions will be terminated.');

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
      $notice->setTitle('Certificate Regenerated');
      $notice->appendChild(
        '<p>Your old certificate has been destroyed and you have been issued '.
        'a new certificate. Sessions established under the old certificate '.
        'are no longer valid.</p>');
      $notice = $notice->render();
    } else {
      $notice = null;
    }

    $cert_form = new AphrontFormView();
    $cert_form
      ->setUser($user)
      ->appendChild(
        '<p class="aphront-form-instructions">This certificate allows you to '.
        'authenticate over Conduit, the Phabricator API. Normally, you just '.
        'run <tt>arc install-certificate</tt> to install it.')
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Certificate')
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_SHORT)
          ->setValue($user->getConduitCertificate()));

    $cert = new AphrontPanelView();
    $cert->setHeader('Arcanist Certificate');
    $cert->appendChild($cert_form);
    $cert->setWidth(AphrontPanelView::WIDTH_FORM);

    $regen_form = new AphrontFormView();
    $regen_form
      ->setUser($user)
      ->setAction($this->getPanelURI())
      ->setWorkflow(true)
      ->appendChild(
        '<p class="aphront-form-instructions">You can regenerate this '.
        'certificate, which will invalidate the old certificate and create '.
        'a new one.</p>')
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Regenerate Certificate'));

    $regen = new AphrontPanelView();
    $regen->setHeader('Regenerate Certificate');
    $regen->appendChild($regen_form);
    $regen->setWidth(AphrontPanelView::WIDTH_FORM);

    return array(
      $notice,
      $cert,
      $regen,
    );
  }
}
