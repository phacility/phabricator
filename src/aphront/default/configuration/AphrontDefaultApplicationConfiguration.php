<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/**
 * @group aphront
 */
class AphrontDefaultApplicationConfiguration
  extends AphrontApplicationConfiguration {

  public function __construct() {

  }

  public function getApplicationName() {
    return 'aphront-default';
  }

  public function getURIMap() {
    return $this->getResourceURIMapRules() + array(
      '/(?:(?P<filter>jump)/)?' =>
        'PhabricatorDirectoryMainController',
      '/(?:(?P<filter>feed)/)' => array(
        'public/' => 'PhabricatorFeedPublicStreamController',
        '(?:(?P<subfilter>[^/]+)/)?' =>
          'PhabricatorDirectoryMainController',
      ),
      '/directory/' => array(
        '(?P<id>\d+)/'
          => 'PhabricatorDirectoryCategoryViewController',
        'edit/'
          => 'PhabricatorDirectoryEditController',
        'item/edit/(?:(?P<id>\d+)/)?'
          => 'PhabricatorDirectoryItemEditController',
        'item/delete/(?P<id>\d+)/'
          => 'PhabricatorDirectoryItemDeleteController',
        'category/edit/(?:(?P<id>\d+)/)?'
          => 'PhabricatorDirectoryCategoryEditController',
        'category/delete/(?P<id>\d+)/'
          => 'PhabricatorDirectoryCategoryDeleteController',
      ),
      '/file/' => array(
        '' => 'PhabricatorFileListController',
        'filter/(?P<filter>\w+)/' => 'PhabricatorFileListController',
        'upload/' => 'PhabricatorFileUploadController',
        'dropupload/' => 'PhabricatorFileDropUploadController',
        'delete/(?P<id>\d+)/' => 'PhabricatorFileDeleteController',
        'info/(?P<phid>[^/]+)/' => 'PhabricatorFileInfoController',

        'data/(?P<key>[^/]+)/(?P<phid>[^/]+)/.*'
          => 'PhabricatorFileDataController',
        // TODO: This is a deprecated version of /data/. Remove it after
        // old links have had a chance to rot.
        'alt/(?P<key>[^/]+)/(?P<phid>[^/]+)/'
          => 'PhabricatorFileDataController',

        'macro/' => array(
          '' => 'PhabricatorFileMacroListController',
          'edit/(?:(?P<id>\d+)/)?' => 'PhabricatorFileMacroEditController',
          'delete/(?P<id>\d+)/' => 'PhabricatorFileMacroDeleteController',
        ),
        'proxy/' => 'PhabricatorFileProxyController',
        'xform/(?P<transform>[^/]+)/(?P<phid>[^/]+)/'
          => 'PhabricatorFileTransformController',
      ),
      '/phid/' => array(
        '' => 'PhabricatorPHIDLookupController',
      ),
      '/people/' => array(
        '' => 'PhabricatorPeopleListController',
        'logs/' => 'PhabricatorPeopleLogsController',
        'edit/(?:(?P<id>\d+)/(?:(?P<view>\w+)/)?)?'
          => 'PhabricatorPeopleEditController',
      ),
      '/p/(?P<username>\w+)/(?:(?P<page>\w+)/)?'
        => 'PhabricatorPeopleProfileController',
      '/conduit/' => array(
        '' => 'PhabricatorConduitConsoleController',
        'method/(?P<method>[^/]+)/' => 'PhabricatorConduitConsoleController',
        'log/' => 'PhabricatorConduitLogController',
        'log/view/(?P<view>[^/]+)/' => 'PhabricatorConduitLogController',
        'token/' => 'PhabricatorConduitTokenController',
      ),
      '/api/(?P<method>[^/]+)' => 'PhabricatorConduitAPIController',

      '/D(?P<id>\d+)' => 'DifferentialRevisionViewController',
      '/differential/' => array(
        '' => 'DifferentialRevisionListController',
        'filter/(?P<filter>\w+)/(?:(?P<username>\w+)/)?' =>
          'DifferentialRevisionListController',
        'stats/(?P<filter>\w+)/' => 'DifferentialRevisionStatsController',
        'diff/' => array(
          '(?P<id>\d+)/' => 'DifferentialDiffViewController',
          'create/' => 'DifferentialDiffCreateController',
        ),
        'changeset/' => 'DifferentialChangesetViewController',
        'revision/edit/(?:(?P<id>\d+)/)?'
          => 'DifferentialRevisionEditController',
        'comment/' => array(
          'preview/(?P<id>\d+)/' => 'DifferentialCommentPreviewController',
          'save/' => 'DifferentialCommentSaveController',
          'inline/' => array(
            'preview/(?P<id>\d+)/' =>
              'DifferentialInlineCommentPreviewController',
            'edit/(?P<id>\d+)/' => 'DifferentialInlineCommentEditController',
          ),
        ),
        'subscribe/(?P<action>add|rem)/(?P<id>\d+)/'
          => 'DifferentialSubscribeController',
      ),

      '/typeahead/' => array(
        'common/(?P<type>\w+)/'
          => 'PhabricatorTypeaheadCommonDatasourceController',
      ),

      '/mail/' => array(
        '' => 'PhabricatorMetaMTAListController',
        'send/' => 'PhabricatorMetaMTASendController',
        'view/(?P<id>\d+)/' => 'PhabricatorMetaMTAViewController',
        'lists/' => 'PhabricatorMetaMTAMailingListsController',
        'lists/edit/(?:(?P<id>\d+)/)?'
          => 'PhabricatorMetaMTAMailingListEditController',
        'receive/' => 'PhabricatorMetaMTAReceiveController',
        'received/' => 'PhabricatorMetaMTAReceivedListController',
        'sendgrid/' => 'PhabricatorMetaMTASendGridReceiveController',
      ),

      '/login/' => array(
        '' => 'PhabricatorLoginController',
        'email/' => 'PhabricatorEmailLoginController',
        'etoken/(?P<token>\w+)/' => 'PhabricatorEmailTokenController',
        'refresh/' => 'PhabricatorRefreshCSRFController',
        'validate/' => 'PhabricatorLoginValidateController',
      ),

      '/logout/' => 'PhabricatorLogoutController',

      '/oauth/' => array(
        '(?P<provider>\w+)/' => array(
          'login/'     => 'PhabricatorOAuthLoginController',
          'diagnose/'  => 'PhabricatorOAuthDiagnosticsController',
          'unlink/'    => 'PhabricatorOAuthUnlinkController',
        ),
      ),

      '/oauthserver/' => array(
        'auth/'          => 'PhabricatorOAuthServerAuthController',
        'test/'          => 'PhabricatorOAuthServerTestController',
        'token/'         => 'PhabricatorOAuthServerTokenController',
        'clientauthorization/' => array(
          '' => 'PhabricatorOAuthClientAuthorizationListController',
          'delete/(?P<phid>[^/]+)/' =>
            'PhabricatorOAuthClientAuthorizationDeleteController',
          'edit/(?P<phid>[^/]+)/' =>
            'PhabricatorOAuthClientAuthorizationEditController',
        ),
        'client/' => array(
          ''                        => 'PhabricatorOAuthClientListController',
          'create/'                 => 'PhabricatorOAuthClientEditController',
          'delete/(?P<phid>[^/]+)/' => 'PhabricatorOAuthClientDeleteController',
          'edit/(?P<phid>[^/]+)/'   => 'PhabricatorOAuthClientEditController',
          'view/(?P<phid>[^/]+)/'   => 'PhabricatorOAuthClientViewController',
        ),
      ),

      '/xhprof/' => array(
        'profile/(?P<phid>[^/]+)/' => 'PhabricatorXHProfProfileController',
      ),

      '/~/' => 'DarkConsoleController',

      '/settings/' => array(
        '(?:page/(?P<page>[^/]+)/)?' => 'PhabricatorUserSettingsController',
      ),

      '/maniphest/' => array(
        '' => 'ManiphestTaskListController',
        'view/(?P<view>\w+)/' => 'ManiphestTaskListController',
        'report/(?:(?P<view>\w+)/)?' => 'ManiphestReportController',
        'batch/' => 'ManiphestBatchEditController',
        'task/' => array(
          'create/' => 'ManiphestTaskEditController',
          'edit/(?P<id>\d+)/' => 'ManiphestTaskEditController',
          'descriptionchange/(?:(?P<id>\d+)/)?' =>
            'ManiphestTaskDescriptionChangeController',
          'descriptionpreview/' =>
            'ManiphestTaskDescriptionPreviewController',
        ),
        'transaction/' => array(
          'save/' => 'ManiphestTransactionSaveController',
          'preview/(?P<id>\d+)/' => 'ManiphestTransactionPreviewController',
        ),
        'export/(?P<key>[^/]+)/' => 'ManiphestExportController',
      ),

      '/T(?P<id>\d+)' => 'ManiphestTaskDetailController',

      '/repository/' => array(
        ''                     => 'PhabricatorRepositoryListController',
        'create/'              => 'PhabricatorRepositoryCreateController',
        'edit/(?P<id>\d+)/(?:(?P<view>\w+)?/)?' =>
          'PhabricatorRepositoryEditController',
        'delete/(?P<id>\d+)/'  => 'PhabricatorRepositoryDeleteController',
        'project/(?P<id>\d+)/' =>
          'PhabricatorRepositoryArcanistProjectEditController',
      ),

      '/search/' => array(
        '' => 'PhabricatorSearchController',
        '(?P<key>[^/]+)/' => 'PhabricatorSearchController',
        'attach/(?P<phid>[^/]+)/(?P<type>\w+)/(?:(?P<action>\w+)/)?'
          => 'PhabricatorSearchAttachController',
        'select/(?P<type>\w+)/'
          => 'PhabricatorSearchSelectController',
        'index/(?P<phid>[^/]+)/' => 'PhabricatorSearchIndexController',
      ),

      '/project/' => array(
        '' => 'PhabricatorProjectListController',
        'filter/(?P<filter>[^/]+)/' => 'PhabricatorProjectListController',
        'edit/(?P<id>\d+)/' => 'PhabricatorProjectProfileEditController',
        'view/(?P<id>\d+)/(?:(?P<page>\w+)/)?'
          => 'PhabricatorProjectProfileController',
        'create/' => 'PhabricatorProjectCreateController',
        'update/(?P<id>\d+)/(?P<action>[^/]+)/'
          => 'PhabricatorProjectUpdateController',
      ),

      '/r(?P<callsign>[A-Z]+)(?P<commit>[a-z0-9]+)'
        => 'DiffusionCommitController',
      '/diffusion/' => array(
        '' => 'DiffusionHomeController',
        '(?P<callsign>[A-Z]+)/' => array(
          '' => 'DiffusionRepositoryController',

          'repository/(?P<dblob>.*)'    => 'DiffusionRepositoryController',
          'change/(?P<dblob>.*)'        => 'DiffusionChangeController',
          'history/(?P<dblob>.*)'       => 'DiffusionHistoryController',
          'browse/(?P<dblob>.*)'        => 'DiffusionBrowseController',
          'lastmodified/(?P<dblob>.*)'  => 'DiffusionLastModifiedController',
          'diff/'                       => 'DiffusionDiffController',
        ),
        'inline/(?P<phid>[^/]+)/' => 'DiffusionInlineCommentController',
        'services/' => array(
          'path/' => array(
            'complete/' => 'DiffusionPathCompleteController',
            'validate/' => 'DiffusionPathValidateController',
          ),
        ),
        'symbol/(?P<name>[^/]+)/' => 'DiffusionSymbolController',
        'external/' => 'DiffusionExternalController',
      ),

      '/daemon/' => array(
        'task/(?P<id>\d+)/' => 'PhabricatorWorkerTaskDetailController',
        'task/(?P<id>\d+)/(?P<action>[^/]+)/'
          => 'PhabricatorWorkerTaskUpdateController',
        'log/' => array(
          '' => 'PhabricatorDaemonLogListController',
          'combined/' => 'PhabricatorDaemonCombinedLogController',
          '(?P<id>\d+)/' => 'PhabricatorDaemonLogViewController',
        ),
        'timeline/' => 'PhabricatorDaemonTimelineConsoleController',
        'timeline/(?P<id>\d+)/' => 'PhabricatorDaemonTimelineEventController',
        '' => 'PhabricatorDaemonConsoleController',
      ),

      '/herald/' => array(
        '' => 'HeraldHomeController',
        'view/(?P<view>[^/]+)/' => array(
          '' => 'HeraldHomeController',
          '(?P<global>global)/' => 'HeraldHomeController'
        ),
        'new/(?:(?P<type>[^/]+)/)?' => 'HeraldNewController',
        'rule/(?:(?P<id>\d+)/)?' => 'HeraldRuleController',
        'history/(?P<id>\d+)/' => 'HeraldRuleEditHistoryController',
        'delete/(?P<id>\d+)/' => 'HeraldDeleteController',
        'test/' => 'HeraldTestConsoleController',
        'all/' => array(
          '' => 'HeraldAllRulesController',
          'view/(?P<view>[^/]+)/' => 'HeraldAllRulesController',
        ),
        'transcript/' => 'HeraldTranscriptListController',
        'transcript/(?P<id>\d+)/(?:(?P<filter>\w+)/)?'
          => 'HeraldTranscriptController',
      ),

      '/uiexample/' => array(
        '' => 'PhabricatorUIExampleRenderController',
        'view/(?P<class>[^/]+)/' => 'PhabricatorUIExampleRenderController',
      ),

      '/owners/' => array(
        '' => 'PhabricatorOwnersListController',
        'view/(?P<view>[^/]+)/' => 'PhabricatorOwnersListController',
        'edit/(?P<id>\d+)/' => 'PhabricatorOwnersEditController',
        'new/' => 'PhabricatorOwnersEditController',
        'package/(?P<id>\d+)/' => 'PhabricatorOwnersDetailController',
        'delete/(?P<id>\d+)/' => 'PhabricatorOwnersDeleteController',
      ),

      '/audit/' => array(
        '' => 'PhabricatorAuditListController',
        'view/(?P<filter>[^/]+)/(?:(?P<name>[^/]+)/)?'
          => 'PhabricatorAuditListController',
        'addcomment/' => 'PhabricatorAuditAddCommentController',
        'preview/(?P<id>\d+)/' => 'PhabricatorAuditPreviewController',
      ),

      '/xhpast/' => array(
        '' => 'PhabricatorXHPASTViewRunController',
        'view/(?P<id>\d+)/'
          => 'PhabricatorXHPASTViewFrameController',
        'frameset/(?P<id>\d+)/'
          => 'PhabricatorXHPASTViewFramesetController',
        'input/(?P<id>\d+)/'
          => 'PhabricatorXHPASTViewInputController',
        'tree/(?P<id>\d+)/'
          => 'PhabricatorXHPASTViewTreeController',
        'stream/(?P<id>\d+)/'
          => 'PhabricatorXHPASTViewStreamController',
      ),

      '/status/' => 'PhabricatorStatusController',

      '/paste/' => array(
        '' => 'PhabricatorPasteListController',
        'filter/(?P<filter>\w+)/' => 'PhabricatorPasteListController',
      ),
      '/P(?P<id>\d+)' => 'PhabricatorPasteViewController',

      '/help/' => array(
        'keyboardshortcut/' => 'PhabricatorHelpKeyboardShortcutController',
      ),

      '/countdown/' => array(
        ''
          => 'PhabricatorCountdownListController',
        '(?P<id>\d+)/'
          => 'PhabricatorCountdownViewController',
        'edit/(?:(?P<id>\d+)/)?'
          => 'PhabricatorCountdownEditController',
        'delete/(?P<id>\d+)/'
          => 'PhabricatorCountdownDeleteController'
      ),

      '/V(?P<id>\d+)' => 'PhabricatorSlowvotePollController',
      '/vote/' => array(
        '(?:view/(?P<view>\w+)/)?' => 'PhabricatorSlowvoteListController',
        'create/' => 'PhabricatorSlowvoteCreateController',
      ),

      // Match "/w/" with slug "/".
      '/w(?P<slug>/)'    => 'PhrictionDocumentController',
      // Match "/w/x/y/z/" with slug "x/y/z/".
      '/w/(?P<slug>.+/)' => 'PhrictionDocumentController',

      '/phriction/' => array(
        ''                       => 'PhrictionListController',
        'list/(?P<view>[^/]+)/'  => 'PhrictionListController',

        'history(?P<slug>/)'     => 'PhrictionHistoryController',
        'history/(?P<slug>.+/)'  => 'PhrictionHistoryController',

        'edit/(?:(?P<id>\d+)/)?' => 'PhrictionEditController',
        'delete/(?P<id>\d+)/'    => 'PhrictionDeleteController',

        'preview/' => 'PhrictionDocumentPreviewController',
        'diff/(?P<id>\d+)/' => 'PhrictionDiffController',
      ),

      '/calendar/' => array(
        '' => 'PhabricatorCalendarBrowseController',
      ),

      '/drydock/' => array(
        '' => 'DrydockResourceListController',
        'resource/' => 'DrydockResourceListController',
        'resource/allocate/' => 'DrydockResourceAllocateController',
        'host/' => array(
          '' => 'DrydockHostListController',
          'edit/' => 'DrydockHostEditController',
          'edit/(?P<id>\d+)/' => 'DrydockhostEditController',
        ),
        'lease/' => 'DrydockLeaseListController',
        'log/' => 'DrydockLogController',
      ),

      '/chatlog/' => array(
        '' =>
          'PhabricatorChatLogChannelListController',
        'channel/(?P<channel>[^/]+)/' =>
          'PhabricatorChatLogChannelLogController',
      ),

      '/aphlict/' => 'PhabricatorAphlictTestPageController',

      '/flag/' => array(
        '' => 'PhabricatorFlagListController',
        'view/(?P<view>[^/]+)/' => 'PhabricatorFlagListController',
        'edit/(?P<phid>[^/]+)/' => 'PhabricatorFlagEditController',
        'delete/(?P<id>\d+)/' => 'PhabricatorFlagDeleteController',
      ),
    );
  }

  protected function getResourceURIMapRules() {
    return array(
      '/res/' => array(
        '(?P<package>pkg/)?(?P<hash>[a-f0-9]{8})/(?P<path>.+\.(?:css|js))'
          => 'CelerityResourceController',
      ),
    );
  }

  public function buildRequest() {
    $request = new AphrontRequest($this->getHost(), $this->getPath());
    $request->setRequestData($_GET + $_POST);
    $request->setApplicationConfiguration($this);
    return $request;
  }

  public function handleException(Exception $ex) {

    // Always log the unhandled exception.
    phlog($ex);

    $class    = phutil_escape_html(get_class($ex));
    $message  = phutil_escape_html($ex->getMessage());

    if (PhabricatorEnv::getEnvConfig('phabricator.show-stack-traces')) {
      $trace = $this->renderStackTrace($ex->getTrace());
    } else {
      $trace = null;
    }

    $content =
      '<div class="aphront-unhandled-exception">'.
        '<div class="exception-message">'.$message.'</div>'.
        $trace.
      '</div>';

    $user = $this->getRequest()->getUser();
    if (!$user) {
      // If we hit an exception very early, we won't have a user.
      $user = new PhabricatorUser();
    }

    $dialog = new AphrontDialogView();
    $dialog
      ->setTitle('Unhandled Exception ("'.$class.'")')
      ->setClass('aphront-exception-dialog')
      ->setUser($user)
      ->appendChild($content);

    if ($this->getRequest()->isAjax()) {
      $dialog->addCancelButton('/', 'Close');
    }

    $response = new AphrontDialogResponse();
    $response->setDialog($dialog);

    return $response;
  }

  public function willSendResponse(AphrontResponse $response) {
    $request = $this->getRequest();
    $response->setRequest($request);
    if ($response instanceof AphrontDialogResponse) {
      if (!$request->isAjax()) {
        $view = new PhabricatorStandardPageView();
        $view->setRequest($request);
        $view->appendChild(
          '<div style="padding: 2em 0;">'.
            $response->buildResponseString().
          '</div>');
        $response = new AphrontWebpageResponse();
        $response->setContent($view->render());
        return $response;
      } else {
        return id(new AphrontAjaxResponse())
          ->setContent(array(
            'dialog' => $response->buildResponseString(),
          ));
      }
    } else if ($response instanceof AphrontRedirectResponse) {
      if ($request->isAjax()) {
        return id(new AphrontAjaxResponse())
          ->setContent(
            array(
              'redirect' => $response->getURI(),
            ));
      }
    }

    return $response;
  }

  public function build404Controller() {
    return array(new Phabricator404Controller($this->getRequest()), array());
  }

  public function buildRedirectController($uri) {
    return array(
      new PhabricatorRedirectController($this->getRequest()),
      array(
        'uri' => $uri,
      ));
  }

  private function renderStackTrace($trace) {

    $libraries = PhutilBootloader::getInstance()->getAllLibraries();

    // TODO: Make this configurable?
    $host = 'https://secure.phabricator.com';

    $browse = array(
      'arcanist' =>
        $host.'/diffusion/ARC/browse/master/src/',
      'phutil' =>
        $host.'/diffusion/PHU/browse/master/src/',
      'phabricator' =>
        $host.'/diffusion/P/browse/master/src/',
    );

    $rows = array();
    $depth = count($trace);
    foreach ($trace as $part) {
      $lib = null;
      $file = idx($part, 'file');
      $relative = $file;
      foreach ($libraries as $library) {
        $root = phutil_get_library_root($library);
        if (Filesystem::isDescendant($file, $root)) {
          $lib = $library;
          $relative = Filesystem::readablePath($file, $root);
          break;
        }
      }

      $where = '';
      if (isset($part['class'])) {
        $where .= $part['class'].'::';
      }
      if (isset($part['function'])) {
        $where .= $part['function'].'()';
      }

      if ($file) {
        if (isset($browse[$lib])) {
          $file_name = phutil_render_tag(
            'a',
            array(
              'href' => $browse[$lib].$relative.'$'.$part['line'],
              'title' => $file,
              'target' => '_blank',
            ),
            phutil_escape_html($relative));
        } else {
          $file_name = phutil_render_tag(
            'span',
            array(
              'title' => $file,
            ),
            phutil_escape_html($relative));
        }
        $file_name = $file_name.' : '.(int)$part['line'];
      } else {
        $file_name = '<em>(Internal)</em>';
      }


      $rows[] = array(
        $depth--,
        phutil_escape_html($lib),
        $file_name,
        phutil_escape_html($where),
      );
    }
    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Depth',
        'Library',
        'File',
        'Where',
      ));
    $table->setColumnClasses(
      array(
        'n',
        '',
        '',
        'wide',
      ));

    return
      '<div class="exception-trace">'.
        '<div class="exception-trace-header">Stack Trace</div>'.
        $table->render().
      '</div>';
  }

}
