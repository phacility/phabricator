<?php

abstract class PhabricatorOAuthServerController
  extends PhabricatorController {

  const CONTEXT_AUTHORIZE = 'oauthserver.authorize';

  protected function buildApplicationCrumbs() {
    // We're specifically not putting an "OAuth Server" application crumb
    // on these pages because it doesn't make sense to send users there on
    // the auth workflows.
    return new PHUICrumbsView();
  }

}
