<?php

final class PhabricatorTransactionsApplication extends PhabricatorApplication {

  public function getName() {
    return pht('Transactions');
  }

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
        'raw/(?<phid>[^/]+)/'
          => 'PhabricatorApplicationTransactionCommentRawController',
        'detail/(?<phid>[^/]+)/'
          => 'PhabricatorApplicationTransactionDetailController',
        'showolder/(?<phid>[^/]+)/'
          => 'PhabricatorApplicationTransactionShowOlderController',
        '(?P<value>old|new)/(?<phid>[^/]+)/'
          => 'PhabricatorApplicationTransactionValueController',
        'editengine/' => array(
          $this->getQueryRoutePattern()
            => 'PhabricatorEditEngineListController',
          '(?P<engineKey>[^/]+)/' => array(
            $this->getQueryRoutePattern() =>
              'PhabricatorEditEngineConfigurationListController',
            $this->getEditRoutePattern('edit/') =>
              'PhabricatorEditEngineConfigurationEditController',
            'view/(?P<key>[^/]+)/' =>
              'PhabricatorEditEngineConfigurationViewController',
            'save/(?P<key>[^/]+)/' =>
              'PhabricatorEditEngineConfigurationSaveController',
            'reorder/(?P<key>[^/]+)/' =>
              'PhabricatorEditEngineConfigurationReorderController',
            'defaults/(?P<key>[^/]+)/' =>
              'PhabricatorEditEngineConfigurationDefaultsController',
            'lock/(?P<key>[^/]+)/' =>
              'PhabricatorEditEngineConfigurationLockController',
            'defaultcreate/(?P<key>[^/]+)/' =>
              'PhabricatorEditEngineConfigurationDefaultCreateController',
            'disable/(?P<key>[^/]+)/' =>
              'PhabricatorEditEngineConfigurationDisableController',
          ),
        ),
      ),
    );
  }

}
