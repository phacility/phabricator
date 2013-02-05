<?php

/**
 * @group conduit
 */
final class PhabricatorConduitTokenController
  extends PhabricatorConduitController {

  public function processRequest() {

    $user = $this->getRequest()->getUser();

    // Ideally we'd like to verify this, but it's fine to leave it unguarded
    // for now and verifying it would need some Ajax junk or for the user to
    // click a button or similar.
    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

    $old_token = id(new PhabricatorConduitCertificateToken())
      ->loadOneWhere(
        'userPHID = %s',
        $user->getPHID());
    if ($old_token) {
      $old_token->delete();
    }

    $token = id(new PhabricatorConduitCertificateToken())
      ->setUserPHID($user->getPHID())
      ->setToken(Filesystem::readRandomCharacters(40))
      ->save();

    $panel = new AphrontPanelView();
    $panel->setHeader('Certificate Install Token');
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);

    $panel->appendChild(hsprintf(
      '<p class="aphront-form-instructions">Copy and paste this token into '.
      'the prompt given to you by "arc install-certificate":</p>'.
      '<p style="padding: 0 0 1em 4em;">'.
        '<strong>%s</strong>'.
      '</p>'.
      '<p class="aphront-form-instructions">arc will then complete the '.
      'install process for you.</p>',
      $token->getToken()));

    $this->setShowSideNav(false);

    return $this->buildStandardPageResponse(
      $panel,
      array(
        'title' => 'Certificate Install Token',
      ));
  }
}
