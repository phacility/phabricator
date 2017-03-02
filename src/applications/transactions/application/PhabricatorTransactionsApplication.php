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
        'remarkuppreview/'
          => 'PhabricatorApplicationTransactionRemarkupPreviewController',
        'editengine/' => array(
          $this->getQueryRoutePattern()
            => 'PhabricatorEditEngineListController',
          '(?P<engineKey>[^/]+)/' => array(
            $this->getQueryRoutePattern() =>
              'PhabricatorEditEngineConfigurationListController',
            $this->getEditRoutePattern('edit/') =>
              'PhabricatorEditEngineConfigurationEditController',
            'sort/(?P<type>edit|create)/' =>
              'PhabricatorEditEngineConfigurationSortController',
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
            'subtype/(?P<key>[^/]+)/' =>
              'PhabricatorEditEngineConfigurationSubtypeController',
            'defaultcreate/(?P<key>[^/]+)/' =>
              'PhabricatorEditEngineConfigurationDefaultCreateController',
            'defaultedit/(?P<key>[^/]+)/' =>
              'PhabricatorEditEngineConfigurationIsEditController',
            'disable/(?P<key>[^/]+)/' =>
              'PhabricatorEditEngineConfigurationDisableController',
          ),
        ),
      ),
    );
  }

}
