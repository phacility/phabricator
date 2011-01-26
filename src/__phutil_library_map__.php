<?php

/**
 * This file is automatically generated. Use 'phutil_mapper.php' to rebuild it.
 * @generated
 */

phutil_register_library_map(array(
  'class' =>
  array(
    'Aphront404Response' => 'aphront/response/404',
    'AphrontAjaxResponse' => 'aphront/response/ajax',
    'AphrontApplicationConfiguration' => 'aphront/applicationconfiguration',
    'AphrontController' => 'aphront/controller',
    'AphrontDatabaseConnection' => 'storage/connection/base',
    'AphrontDefaultApplicationConfiguration' => 'aphront/default/configuration',
    'AphrontDefaultApplicationController' => 'aphront/default/controller',
    'AphrontDialogResponse' => 'aphront/response/dialog',
    'AphrontDialogView' => 'view/dialog',
    'AphrontErrorView' => 'view/form/error',
    'AphrontFileResponse' => 'aphront/response/file',
    'AphrontFormCheckboxControl' => 'view/form/control/checkbox',
    'AphrontFormControl' => 'view/form/control/base',
    'AphrontFormFileControl' => 'view/form/control/file',
    'AphrontFormMarkupControl' => 'view/form/control/markup',
    'AphrontFormSelectControl' => 'view/form/control/select',
    'AphrontFormStaticControl' => 'view/form/control/static',
    'AphrontFormSubmitControl' => 'view/form/control/submit',
    'AphrontFormTextAreaControl' => 'view/form/control/textarea',
    'AphrontFormTextControl' => 'view/form/control/text',
    'AphrontFormTokenizerControl' => 'view/form/control/tokenizer',
    'AphrontFormView' => 'view/form/base',
    'AphrontMySQLDatabaseConnection' => 'storage/connection/mysql',
    'AphrontNullView' => 'view/null',
    'AphrontPageView' => 'view/page/base',
    'AphrontPanelView' => 'view/layout/panel',
    'AphrontQueryConnectionException' => 'storage/exception/connection',
    'AphrontQueryConnectionLostException' => 'storage/exception/connectionlost',
    'AphrontQueryCountException' => 'storage/exception/count',
    'AphrontQueryException' => 'storage/exception/base',
    'AphrontQueryObjectMissingException' => 'storage/exception/objectmissing',
    'AphrontQueryParameterException' => 'storage/exception/parameter',
    'AphrontQueryRecoverableException' => 'storage/exception/recoverable',
    'AphrontRedirectResponse' => 'aphront/response/redirect',
    'AphrontRequest' => 'aphront/request',
    'AphrontResponse' => 'aphront/response/base',
    'AphrontSideNavView' => 'view/layout/sidenav',
    'AphrontTableView' => 'view/control/table',
    'AphrontURIMapper' => 'aphront/mapper',
    'AphrontView' => 'view/base',
    'AphrontWebpageResponse' => 'aphront/response/webpage',
    'CelerityAPI' => 'infratructure/celerity/api',
    'CelerityResourceController' => 'infratructure/celerity/controller',
    'CelerityResourceMap' => 'infratructure/celerity/map',
    'CelerityStaticResourceResponse' => 'infratructure/celerity/response',
    'ConduitAPIMethod' => 'applications/conduit/method/base',
    'ConduitAPIRequest' => 'applications/conduit/protocol/request',
    'ConduitAPI_conduit_connect_Method' => 'applications/conduit/method/conduit/connect',
    'ConduitAPI_differential_creatediff_Method' => 'applications/conduit/method/differential/creatediff',
    'ConduitAPI_differential_setdiffproperty_Method' => 'applications/conduit/method/differential/setdiffproperty',
    'ConduitAPI_file_upload_Method' => 'applications/conduit/method/file/upload',
    'ConduitAPI_user_find_Method' => 'applications/conduit/method/user/find',
    'ConduitException' => 'applications/conduit/protocol/exception',
    'DifferentialAction' => 'applications/differential/constants/action',
    'DifferentialChangeType' => 'applications/differential/constants/changetype',
    'DifferentialChangeset' => 'applications/differential/storage/changeset',
    'DifferentialChangesetDetailView' => 'applications/differential/view/changesetdetailview',
    'DifferentialChangesetListView' => 'applications/differential/view/changesetlistview',
    'DifferentialChangesetParser' => 'applications/differential/parser/changeset',
    'DifferentialChangesetViewController' => 'applications/differential/controller/changesetview',
    'DifferentialController' => 'applications/differential/controller/base',
    'DifferentialDAO' => 'applications/differential/storage/base',
    'DifferentialDiff' => 'applications/differential/storage/diff',
    'DifferentialDiffProperty' => 'applications/differential/storage/diffproperty',
    'DifferentialDiffTableOfContentsView' => 'applications/differential/view/difftableofcontents',
    'DifferentialDiffViewController' => 'applications/differential/controller/diffview',
    'DifferentialHunk' => 'applications/differential/storage/hunk',
    'DifferentialLintStatus' => 'applications/differential/constants/lintstatus',
    'DifferentialRevision' => 'applications/differential/storage/revision',
    'DifferentialRevisionControlSystem' => 'applications/differential/constants/revisioncontrolsystem',
    'DifferentialRevisionEditController' => 'applications/differential/controller/revisionedit',
    'DifferentialRevisionListController' => 'applications/differential/controller/revisionlist',
    'DifferentialRevisionStatus' => 'applications/differential/constants/revisionstatus',
    'DifferentialUnitStatus' => 'applications/differential/constants/unitstatus',
    'Javelin' => 'infratructure/javelin/api',
    'LiskDAO' => 'storage/lisk/dao',
    'PhabricatorConduitAPIController' => 'applications/conduit/controller/api',
    'PhabricatorConduitConnectionLog' => 'applications/conduit/storage/connectionlog',
    'PhabricatorConduitConsoleController' => 'applications/conduit/controller/console',
    'PhabricatorConduitController' => 'applications/conduit/controller/base',
    'PhabricatorConduitDAO' => 'applications/conduit/storage/base',
    'PhabricatorConduitLogController' => 'applications/conduit/controller/log',
    'PhabricatorConduitMethodCallLog' => 'applications/conduit/storage/methodcalllog',
    'PhabricatorController' => 'applications/base/controller/base',
    'PhabricatorDirectoryCategory' => 'applications/directory/storage/category',
    'PhabricatorDirectoryCategoryDeleteController' => 'applications/directory/controller/categorydelete',
    'PhabricatorDirectoryCategoryEditController' => 'applications/directory/controller/categoryedit',
    'PhabricatorDirectoryCategoryListController' => 'applications/directory/controller/categorylist',
    'PhabricatorDirectoryController' => 'applications/directory/controller/base',
    'PhabricatorDirectoryDAO' => 'applications/directory/storage/base',
    'PhabricatorDirectoryItem' => 'applications/directory/storage/item',
    'PhabricatorDirectoryItemDeleteController' => 'applications/directory/controller/itemdelete',
    'PhabricatorDirectoryItemEditController' => 'applications/directory/controller/itemedit',
    'PhabricatorDirectoryItemListController' => 'applications/directory/controller/itemlist',
    'PhabricatorDirectoryMainController' => 'applications/directory/controller/main',
    'PhabricatorFile' => 'applications/files/storage/file',
    'PhabricatorFileController' => 'applications/files/controller/base',
    'PhabricatorFileDAO' => 'applications/files/storage/base',
    'PhabricatorFileListController' => 'applications/files/controller/list',
    'PhabricatorFileStorageBlob' => 'applications/files/storage/storageblob',
    'PhabricatorFileURI' => 'applications/files/uri',
    'PhabricatorFileUploadController' => 'applications/files/controller/upload',
    'PhabricatorFileViewController' => 'applications/files/controller/view',
    'PhabricatorLiskDAO' => 'applications/base/storage/lisk',
    'PhabricatorMailImplementationAdapter' => 'applications/metamta/adapter/base',
    'PhabricatorMailImplementationPHPMailerLiteAdapter' => 'applications/metamta/adapter/phpmailerlite',
    'PhabricatorMetaMTAController' => 'applications/metamta/controller/base',
    'PhabricatorMetaMTADAO' => 'applications/metamta/storage/base',
    'PhabricatorMetaMTAListController' => 'applications/metamta/controller/list',
    'PhabricatorMetaMTAMail' => 'applications/metamta/storage/mail',
    'PhabricatorMetaMTASendController' => 'applications/metamta/controller/send',
    'PhabricatorMetaMTAViewController' => 'applications/metamta/controller/view',
    'PhabricatorObjectHandle' => 'applications/phid/handle',
    'PhabricatorObjectHandleData' => 'applications/phid/handle/data',
    'PhabricatorPHID' => 'applications/phid/storage/phid',
    'PhabricatorPHIDAllocateController' => 'applications/phid/controller/allocate',
    'PhabricatorPHIDController' => 'applications/phid/controller/base',
    'PhabricatorPHIDDAO' => 'applications/phid/storage/base',
    'PhabricatorPHIDListController' => 'applications/phid/controller/list',
    'PhabricatorPHIDLookupController' => 'applications/phid/controller/lookup',
    'PhabricatorPHIDType' => 'applications/phid/storage/type',
    'PhabricatorPHIDTypeEditController' => 'applications/phid/controller/typeedit',
    'PhabricatorPHIDTypeListController' => 'applications/phid/controller/typelist',
    'PhabricatorPeopleController' => 'applications/people/controller/base',
    'PhabricatorPeopleEditController' => 'applications/people/controller/edit',
    'PhabricatorPeopleListController' => 'applications/people/controller/list',
    'PhabricatorPeopleProfileController' => 'applications/people/controller/profile',
    'PhabricatorStandardPageView' => 'view/page/standard',
    'PhabricatorTypeaheadCommonDatasourceController' => 'applications/typeahead/controller/common',
    'PhabricatorTypeaheadDatasourceController' => 'applications/typeahead/controller/base',
    'PhabricatorUser' => 'applications/people/storage/user',
    'PhabricatorUserDAO' => 'applications/people/storage/base',
  ),
  'function' =>
  array(
    '_qsprintf_check_scalar_type' => 'storage/qsprintf',
    '_qsprintf_check_type' => 'storage/qsprintf',
    'celerity_generate_unique_node_id' => 'infratructure/celerity/api',
    'celerity_register_resource_map' => 'infratructure/celerity/map',
    'javelin_render_tag' => 'infratructure/javelin/markup',
    'qsprintf' => 'storage/qsprintf',
    'queryfx' => 'storage/queryfx',
    'queryfx_all' => 'storage/queryfx',
    'queryfx_one' => 'storage/queryfx',
    'require_celerity_resource' => 'infratructure/celerity/api',
    'vqsprintf' => 'storage/qsprintf',
    'vqueryfx' => 'storage/queryfx',
    'xsprintf_query' => 'storage/qsprintf',
  ),
  'requires_class' =>
  array(
    'Aphront404Response' => 'AphrontResponse',
    'AphrontAjaxResponse' => 'AphrontResponse',
    'AphrontDefaultApplicationConfiguration' => 'AphrontApplicationConfiguration',
    'AphrontDefaultApplicationController' => 'AphrontController',
    'AphrontDialogResponse' => 'AphrontResponse',
    'AphrontDialogView' => 'AphrontView',
    'AphrontErrorView' => 'AphrontView',
    'AphrontFileResponse' => 'AphrontResponse',
    'AphrontFormCheckboxControl' => 'AphrontFormControl',
    'AphrontFormControl' => 'AphrontView',
    'AphrontFormFileControl' => 'AphrontFormControl',
    'AphrontFormMarkupControl' => 'AphrontFormControl',
    'AphrontFormSelectControl' => 'AphrontFormControl',
    'AphrontFormStaticControl' => 'AphrontFormControl',
    'AphrontFormSubmitControl' => 'AphrontFormControl',
    'AphrontFormTextAreaControl' => 'AphrontFormControl',
    'AphrontFormTextControl' => 'AphrontFormControl',
    'AphrontFormTokenizerControl' => 'AphrontFormControl',
    'AphrontFormView' => 'AphrontView',
    'AphrontMySQLDatabaseConnection' => 'AphrontDatabaseConnection',
    'AphrontNullView' => 'AphrontView',
    'AphrontPageView' => 'AphrontView',
    'AphrontPanelView' => 'AphrontView',
    'AphrontQueryConnectionException' => 'AphrontQueryException',
    'AphrontQueryConnectionLostException' => 'AphrontQueryRecoverableException',
    'AphrontQueryCountException' => 'AphrontQueryException',
    'AphrontQueryObjectMissingException' => 'AphrontQueryException',
    'AphrontQueryParameterException' => 'AphrontQueryException',
    'AphrontQueryRecoverableException' => 'AphrontQueryException',
    'AphrontRedirectResponse' => 'AphrontResponse',
    'AphrontSideNavView' => 'AphrontView',
    'AphrontTableView' => 'AphrontView',
    'AphrontWebpageResponse' => 'AphrontResponse',
    'CelerityResourceController' => 'AphrontController',
    'ConduitAPI_conduit_connect_Method' => 'ConduitAPIMethod',
    'ConduitAPI_differential_creatediff_Method' => 'ConduitAPIMethod',
    'ConduitAPI_differential_setdiffproperty_Method' => 'ConduitAPIMethod',
    'ConduitAPI_file_upload_Method' => 'ConduitAPIMethod',
    'ConduitAPI_user_find_Method' => 'ConduitAPIMethod',
    'DifferentialChangeset' => 'DifferentialDAO',
    'DifferentialChangesetDetailView' => 'AphrontView',
    'DifferentialChangesetListView' => 'AphrontView',
    'DifferentialChangesetViewController' => 'DifferentialController',
    'DifferentialController' => 'PhabricatorController',
    'DifferentialDAO' => 'PhabricatorLiskDAO',
    'DifferentialDiff' => 'DifferentialDAO',
    'DifferentialDiffProperty' => 'DifferentialDAO',
    'DifferentialDiffTableOfContentsView' => 'AphrontView',
    'DifferentialDiffViewController' => 'DifferentialController',
    'DifferentialHunk' => 'DifferentialDAO',
    'DifferentialRevision' => 'DifferentialDAO',
    'DifferentialRevisionEditController' => 'DifferentialController',
    'DifferentialRevisionListController' => 'DifferentialController',
    'PhabricatorConduitAPIController' => 'PhabricatorConduitController',
    'PhabricatorConduitConnectionLog' => 'PhabricatorConduitDAO',
    'PhabricatorConduitConsoleController' => 'PhabricatorConduitController',
    'PhabricatorConduitController' => 'PhabricatorController',
    'PhabricatorConduitDAO' => 'PhabricatorLiskDAO',
    'PhabricatorConduitLogController' => 'PhabricatorConduitController',
    'PhabricatorConduitMethodCallLog' => 'PhabricatorConduitDAO',
    'PhabricatorController' => 'AphrontController',
    'PhabricatorDirectoryCategory' => 'PhabricatorDirectoryDAO',
    'PhabricatorDirectoryCategoryDeleteController' => 'PhabricatorDirectoryController',
    'PhabricatorDirectoryCategoryEditController' => 'PhabricatorDirectoryController',
    'PhabricatorDirectoryCategoryListController' => 'PhabricatorDirectoryController',
    'PhabricatorDirectoryController' => 'PhabricatorController',
    'PhabricatorDirectoryDAO' => 'PhabricatorLiskDAO',
    'PhabricatorDirectoryItem' => 'PhabricatorDirectoryDAO',
    'PhabricatorDirectoryItemDeleteController' => 'PhabricatorDirectoryController',
    'PhabricatorDirectoryItemEditController' => 'PhabricatorDirectoryController',
    'PhabricatorDirectoryItemListController' => 'PhabricatorDirectoryController',
    'PhabricatorDirectoryMainController' => 'PhabricatorDirectoryController',
    'PhabricatorFile' => 'PhabricatorFileDAO',
    'PhabricatorFileController' => 'PhabricatorController',
    'PhabricatorFileDAO' => 'PhabricatorLiskDAO',
    'PhabricatorFileListController' => 'PhabricatorFileController',
    'PhabricatorFileStorageBlob' => 'PhabricatorFileDAO',
    'PhabricatorFileUploadController' => 'PhabricatorFileController',
    'PhabricatorFileViewController' => 'PhabricatorFileController',
    'PhabricatorLiskDAO' => 'LiskDAO',
    'PhabricatorMailImplementationPHPMailerLiteAdapter' => 'PhabricatorMailImplementationAdapter',
    'PhabricatorMetaMTAController' => 'PhabricatorController',
    'PhabricatorMetaMTADAO' => 'PhabricatorLiskDAO',
    'PhabricatorMetaMTAListController' => 'PhabricatorMetaMTAController',
    'PhabricatorMetaMTAMail' => 'PhabricatorMetaMTADAO',
    'PhabricatorMetaMTASendController' => 'PhabricatorMetaMTAController',
    'PhabricatorMetaMTAViewController' => 'PhabricatorMetaMTAController',
    'PhabricatorPHID' => 'PhabricatorPHIDDAO',
    'PhabricatorPHIDAllocateController' => 'PhabricatorPHIDController',
    'PhabricatorPHIDController' => 'PhabricatorController',
    'PhabricatorPHIDDAO' => 'PhabricatorLiskDAO',
    'PhabricatorPHIDListController' => 'PhabricatorPHIDController',
    'PhabricatorPHIDLookupController' => 'PhabricatorPHIDController',
    'PhabricatorPHIDType' => 'PhabricatorPHIDDAO',
    'PhabricatorPHIDTypeEditController' => 'PhabricatorPHIDController',
    'PhabricatorPHIDTypeListController' => 'PhabricatorPHIDController',
    'PhabricatorPeopleController' => 'PhabricatorController',
    'PhabricatorPeopleEditController' => 'PhabricatorPeopleController',
    'PhabricatorPeopleListController' => 'PhabricatorPeopleController',
    'PhabricatorPeopleProfileController' => 'PhabricatorPeopleController',
    'PhabricatorStandardPageView' => 'AphrontPageView',
    'PhabricatorTypeaheadCommonDatasourceController' => 'PhabricatorTypeaheadDatasourceController',
    'PhabricatorTypeaheadDatasourceController' => 'PhabricatorController',
    'PhabricatorUser' => 'PhabricatorUserDAO',
    'PhabricatorUserDAO' => 'PhabricatorLiskDAO',
  ),
  'requires_interface' =>
  array(
  ),
));
