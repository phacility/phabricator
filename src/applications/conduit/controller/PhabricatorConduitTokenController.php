<?php

final class PhabricatorConduitTokenController
  extends PhabricatorConduitController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

    id(new PhabricatorAuthSessionEngine())->requireHighSecuritySession(
      $viewer,
      $this->getRequest(),
      '/');

    // Ideally we'd like to verify this, but it's fine to leave it unguarded
    // for now and verifying it would need some Ajax junk or for the user to
    // click a button or similar.
    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

    $old_token = id(new PhabricatorConduitCertificateToken())
      ->loadOneWhere(
        'userPHID = %s',
        $viewer->getPHID());
    if ($old_token) {
      $old_token->delete();
    }

    $token = id(new PhabricatorConduitCertificateToken())
      ->setUserPHID($viewer->getPHID())
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
      ->setUser($viewer)
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
    $crumbs->setBorder(true);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Certificate Token'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setForm($form);

    $title = pht('Certificate Install Token');

    $header = id(new PHUIHeaderView())
      ->setHeader($title);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter($object_box);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

}
