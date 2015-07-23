<?php

final class PhabricatorConduitCertificateSettingsPanel
  extends PhabricatorSettingsPanel {

  public function isEditableByAdministrators() {
    return true;
  }

  public function getPanelKey() {
    return 'conduit';
  }

  public function getPanelName() {
    return pht('Conduit Certificate');
  }

  public function getPanelGroup() {
    return pht('Authentication');
  }

  public function isEnabled() {
    if ($this->getUser()->getIsMailingList()) {
      return false;
    }

    return true;
  }

  public function processRequest(AphrontRequest $request) {
    $user = $this->getUser();
    $viewer = $request->getUser();

    id(new PhabricatorAuthSessionEngine())->requireHighSecuritySession(
      $viewer,
      $request,
      '/settings/');

    if ($request->isFormPost()) {
      if (!$request->isDialogFormPost()) {
        $dialog = new AphrontDialogView();
        $dialog->setUser($viewer);
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

      $sessions = id(new PhabricatorAuthSessionQuery())
        ->setViewer($user)
        ->withIdentityPHIDs(array($user->getPHID()))
        ->withSessionTypes(array(PhabricatorAuthSession::TYPE_CONDUIT))
        ->execute();
      foreach ($sessions as $session) {
        $session->delete();
      }

      // This implicitly regenerates the certificate.
      $user->setConduitCertificate(null);
      $user->save();
      return id(new AphrontRedirectResponse())
        ->setURI($this->getPanelURI('?regenerated=true'));
    }

    if ($request->getStr('regenerated')) {
      $notice = new PHUIInfoView();
      $notice->setSeverity(PHUIInfoView::SEVERITY_NOTICE);
      $notice->setTitle(pht('Certificate Regenerated'));
      $notice->appendChild(phutil_tag(
        'p',
        array(),
        pht(
          'Your old certificate has been destroyed and you have been issued '.
        'a new certificate. Sessions established under the old certificate '.
        'are no longer valid.')));
      $notice = $notice->render();
    } else {
      $notice = null;
    }

    Javelin::initBehavior('select-on-click');

    $cert_form = new AphrontFormView();
    $cert_form
      ->setUser($viewer)
      ->appendChild(phutil_tag(
        'p',
        array('class' => 'aphront-form-instructions'),
        pht(
          'This certificate allows you to authenticate over Conduit, '.
          'the Phabricator API. Normally, you just run %s to install it.',
          phutil_tag('tt', array(), 'arc install-certificate'))))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel(pht('Certificate'))
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_SHORT)
          ->setReadonly(true)
          ->setSigil('select-on-click')
          ->setValue($user->getConduitCertificate()));

    $cert_form = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Arcanist Certificate'))
      ->setForm($cert_form);

    $regen_instruction = pht(
      'You can regenerate this certificate, which '.
      'will invalidate the old certificate and create a new one.');

    $regen_form = new AphrontFormView();
    $regen_form
      ->setUser($viewer)
      ->setAction($this->getPanelURI())
      ->setWorkflow(true)
      ->appendChild(phutil_tag(
        'p',
        array('class' => 'aphront-form-instructions'),
        $regen_instruction))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Regenerate Certificate')));

    $regen_form = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Regenerate Certificate'))
      ->setForm($regen_form);

    return array(
      $notice,
      $cert_form,
      $regen_form,
    );
  }
}
