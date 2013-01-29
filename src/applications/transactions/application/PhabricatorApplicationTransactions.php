<?php

final class PhabricatorApplicationTransactions extends PhabricatorApplication {

  public function shouldAppearInLaunchView() {
    return false;
  }

  public function canUninstall() {
    return false;
  }

  public function getRoutes() {
    return array(
      '/transactions/' => array(
        'edit/(?<phid>[^/]+)/'
          => 'PhabricatorApplicationTransactionCommentEditController',
        'history/(?<phid>[^/]+)/'
          => 'PhabricatorApplicationTransactionCommentHistoryController',
      ),
    );
  }

}

