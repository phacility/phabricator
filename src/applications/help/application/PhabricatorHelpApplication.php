<?php

final class PhabricatorHelpApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Help');
  }

  public function canUninstall() {
    return false;
  }

  public function isUnlisted() {
    return true;
  }

  public function getRoutes() {
    return array(
      '/help/' => array(
        'keyboardshortcut/' => 'PhabricatorHelpKeyboardShortcutController',
        'documentation/(?P<application>\w+)/'
          => 'PhabricatorHelpDocumentationController',
      ),
    );
  }

}
