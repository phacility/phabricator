<?php

final class PhabricatorApplicationTransactions extends PhabricatorApplication {

  public function isLaunchable() {
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
        'remove/(?<phid>[^/]+)/'
          => 'PhabricatorApplicationTransactionCommentRemoveController',
        'history/(?<phid>[^/]+)/'
          => 'PhabricatorApplicationTransactionCommentHistoryController',
        'quote/(?<phid>[^/]+)/'
          => 'PhabricatorApplicationTransactionCommentQuoteController',
        'detail/(?<phid>[^/]+)/'
          => 'PhabricatorApplicationTransactionDetailController',
        '(?P<value>old|new)/(?<phid>[^/]+)/'
          => 'PhabricatorApplicationTransactionValueController',
      ),
    );
  }

}
