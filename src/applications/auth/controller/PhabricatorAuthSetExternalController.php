<?php

final class PhabricatorAuthSetExternalController
  extends PhabricatorAuthController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $configs = id(new PhabricatorAuthProviderConfigQuery())
      ->setViewer($viewer)
      ->withIsEnabled(true)
      ->execute();

    $linkable = array();
    foreach ($configs as $config) {
      if (!$config->getShouldAllowLink()) {
        continue;
      }

      // For now, only buttons get to appear here: for example, we can't
      // reasonably embed an entire LDAP form into this UI.
      $provider = $config->getProvider();
      if (!$provider->isLoginFormAButton()) {
        continue;
      }

      $linkable[] = $config;
    }

    if (!$linkable) {
      return $this->newDialog()
        ->setTitle(pht('No Linkable External Providers'))
        ->appendParagraph(
          pht(
            'Currently, there are no configured external auth providers '.
            'which you can link your account to.'))
        ->addCancelButton('/');
    }

    $text = PhabricatorAuthMessage::loadMessageText(
      $viewer,
      PhabricatorAuthLinkMessageType::MESSAGEKEY);
    if (!phutil_nonempty_string($text)) {
      $text = pht(
        'You can link your %s account to an external account to '.
        'allow you to log in more easily in the future. To continue, choose '.
        'an account to link below. If you prefer not to link your account, '.
        'you can skip this step.',
        PlatformSymbols::getPlatformServerName());
    }

    $remarkup_view = new PHUIRemarkupView($viewer, $text);
    $remarkup_view = phutil_tag(
      'div',
      array(
        'class' => 'phui-object-box-instructions',
      ),
      $remarkup_view);

    PhabricatorCookies::setClientIDCookie($request);

    $view = array();
    foreach ($configs as $config) {
      $provider = $config->getProvider();

      $form = $provider->buildLinkForm($this);

      if ($provider->isLoginFormAButton()) {
        require_celerity_resource('auth-css');
        $form = phutil_tag(
          'div',
          array(
            'class' => 'phabricator-link-button pl',
          ),
          $form);
      }

      $view[] = $form;
    }

    $form = id(new AphrontFormView())
      ->setViewer($viewer)
      ->appendControl(
        id(new AphrontFormSubmitControl())
          ->addCancelButton('/', pht('Skip This Step')));

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Link External Account'));

    $box = id(new PHUIObjectBoxView())
      ->setViewer($viewer)
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($remarkup_view)
      ->appendChild($view)
      ->appendChild($form);

    $main_view = id(new PHUITwoColumnView())
      ->setFooter($box);

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb(pht('Link External Account'))
      ->setBorder(true);

    return $this->newPage()
      ->setTitle(pht('Link External Account'))
      ->setCrumbs($crumbs)
      ->appendChild($main_view);
  }

}
