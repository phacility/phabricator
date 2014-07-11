<?php

final class PhabricatorConduitTokenController
  extends PhabricatorConduitController {

  public function processRequest() {
    $user = $this->getRequest()->getUser();

    id(new PhabricatorAuthSessionEngine())->requireHighSecuritySession(
      $user,
      $this->getRequest(),
      '/');

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

    unset($unguarded);

    $pre_instructions = pht(
      'Copy and paste this token into the prompt given to you by '.
      '`arc install-certificate`');

    $post_instructions = pht(
      'After you copy and paste this token, `arc` will complete '.
      'the certificate install process for you.');

    Javelin::initBehavior('select-on-click');

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendRemarkupInstructions($pre_instructions)
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel(pht('Token'))
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_SHORT)
          ->setReadonly(true)
          ->setSigil('select-on-click')
          ->setValue($token->getToken()))
      ->appendRemarkupInstructions($post_instructions);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Install Certificate'));

    $object_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Certificate Token'))
      ->setForm($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
      ),
      array(
        'title' => pht('Certificate Install Token'),
      ));
  }

}
