<?php

/*
 * Copyright 2011 Facebook, Inc.
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

  public function getApplicationName() {
    return 'aphront-default';
  }

  public function getURIMap() {
    return array(
      '/' => array(
        '$'                     => 'PhabricatorDirectoryMainController',
      ),
      '/directory/' => array(
        'item/$'
          => 'PhabricatorDirectoryItemListController',
        'item/edit/(?:(?P<id>\d+)/)?$'
          => 'PhabricatorDirectoryItemEditController',
        'item/delete/(?P<id>\d+)/'
          => 'PhabricatorDirectoryItemDeleteController',
        'category/$'
          => 'PhabricatorDirectoryCategoryListController',
        'category/edit/(?:(?P<id>\d+)/)?$'
          => 'PhabricatorDirectoryCategoryEditController',
        'category/delete/(?P<id>\d+)/'
          => 'PhabricatorDirectoryCategoryDeleteController',
      ),
      '/file/' => array(
        '$' => 'PhabricatorFileListController',
        'upload/$' => 'PhabricatorFileUploadController',
        '(?P<view>info)/(?P<phid>[^/]+)/' => 'PhabricatorFileViewController',
        '(?P<view>view)/(?P<phid>[^/]+)/' => 'PhabricatorFileViewController',
        '(?P<view>download)/(?P<phid>[^/]+)/' => 'PhabricatorFileViewController',
      ),
      '/phid/' => array(
        '$' => 'PhabricatorPHIDLookupController',
        'list/$' => 'PhabricatorPHIDListController',
        'type/$' => 'PhabricatorPHIDTypeListController',
        'type/edit/(?:(?P<id>\d+)/)?$' => 'PhabricatorPHIDTypeEditController',
        'new/$' => 'PhabricatorPHIDAllocateController',
      ),
      '/people/' => array(
        '$' => 'PhabricatorPeopleListController',
        'edit/(?:(?P<username>\w+)/)?$' => 'PhabricatorPeopleEditController',
      ),
      '/p/(?P<username>\w+)/$' => 'PhabricatorPeopleProfileController',
      '/profile/' => array(
        'edit/$' => 'PhabricatorPeopleProfileEditController',
      ),
      '/conduit/' => array(
        '$' => 'PhabricatorConduitConsoleController',
        'method/(?P<method>[^/]+)$' => 'PhabricatorConduitConsoleController',
        'log/$' => 'PhabricatorConduitLogController',
      ),
      '/api/(?P<method>[^/]+)$' => 'PhabricatorConduitAPIController',


      '/D(?P<id>\d+)' => 'DifferentialRevisionViewController',
      '/differential/' => array(
        '$' => 'DifferentialRevisionListController',
        'filter/(?P<filter>\w+)/$' => 'DifferentialRevisionListController',
        'diff/' => array(
          '(?P<id>\d+)/$' => 'DifferentialDiffViewController',
          'create/$' => 'DifferentialDiffCreateController',
        ),
        'changeset/$'  => 'DifferentialChangesetViewController',
        'revision/edit/(?:(?P<id>\d+)/)?$'
          => 'DifferentialRevisionEditController',
        'comment/' => array(
          'preview/(?P<id>\d+)/$' => 'DifferentialCommentPreviewController',
          'save/$' => 'DifferentialCommentSaveController',
          'inline/' => array(
            'preview/(?P<id>\d+)/$' =>
              'DifferentialInlineCommentPreviewController',
            'edit/(?P<id>\d+)/$' => 'DifferentialInlineCommentEditController',
          ),
        ),
        'attach/(?P<id>\d+)/(?P<type>\w+)/$' => 'DifferentialAttachController',
        'subscribe/(?P<action>add|rem)/(?P<id>\d+)/$'
          => 'DifferentialSubscribeController',
      ),

      '/res/' => array(
        '(?P<package>pkg/)?(?P<hash>[a-f0-9]{8})/(?P<path>.+\.(?:css|js))$'
          => 'CelerityResourceController',
      ),

      '/typeahead/' => array(
        'common/(?P<type>\w+)/$'
          => 'PhabricatorTypeaheadCommonDatasourceController',
      ),

      '/mail/' => array(
        '$' => 'PhabricatorMetaMTAListController',
        'send/$' => 'PhabricatorMetaMTASendController',
        'view/(?P<id>\d+)/$' => 'PhabricatorMetaMTAViewController',
        'lists/$' => 'PhabricatorMetaMTAMailingListsController',
        'lists/edit/(?:(?P<id>\d+)/)?$'
          => 'PhabricatorMetaMTAMailingListEditController',
      ),

      '/login/' => array(
        '$' => 'PhabricatorLoginController',
        'email/$' => 'PhabricatorEmailLoginController',
        'etoken/(?P<token>\w+)/$' => 'PhabricatorEmailTokenController',
      ),
      '/logout/$' => 'PhabricatorLogoutController',
      '/facebook-auth/' => array(
        '$' => 'PhabricatorFacebookAuthController',
        'diagnose/$' => 'PhabricatorFacebookAuthDiagnosticsController',
      ),

      '/xhprof/' => array(
        'profile/(?P<phid>[^/]+)/$' => 'PhabricatorXHProfProfileController',
      ),

      '/~/' => 'DarkConsoleController',

      '/settings/' => array(
        '(?:page/(?P<page>[^/]+)/)?$' => 'PhabricatorUserSettingsController',
      ),

      '/maniphest/' => array(
        '$' => 'ManiphestTaskListController',
        'view/(?P<view>\w+)/$' => 'ManiphestTaskListController',
        'task/' => array(
          'create/$' => 'ManiphestTaskEditController',
          'edit/(?P<id>\d+)/$' => 'ManiphestTaskEditController',
        ),
        'transaction/' => array(
          'save/' => 'ManiphestTransactionSaveController',
        ),
        'select/search/$' => 'ManiphestTaskSelectorSearchController',
      ),

      '/T(?P<id>\d+)$' => 'ManiphestTaskDetailController',

      '/github-post-receive/(?P<id>\d+)/(?P<token>[^/]+)/$'
        => 'PhabricatorRepositoryGitHubPostReceiveController',

      '/repository/' => array(
        '$'                     => 'PhabricatorRepositoryListController',
        'create/$'              => 'PhabricatorRepositoryCreateController',
        'edit/(?P<id>\d+)/$'    => 'PhabricatorRepositoryEditController',
        'delete/(?P<id>\d+)/$'  => 'PhabricatorRepositoryDeleteController',
      ),

      '/search/' => array(
        '$' => 'PhabricatorSearchController',
        '(?P<id>\d+)/$' => 'PhabricatorSearchController',
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

    $class    = phutil_escape_html(get_class($ex));
    $message  = phutil_escape_html($ex->getMessage());

    $content =
      '<div class="aphront-unhandled-exception">'.
        '<h1>Unhandled Exception "'.$class.'": '.$message.'</h1>'.
        '<code>'.phutil_escape_html((string)$ex).'</code>'.
      '</div>';

    if ($this->getRequest()->isAjax()) {
      $dialog = new AphrontDialogView();
      $dialog
        ->setTitle('Exception!')
        ->setClass('aphront-exception-dialog')
        ->setUser($this->getRequest()->getUser())
        ->appendChild($content)
        ->addCancelButton('/');

      $response = new AphrontDialogResponse();
      $response->setDialog($dialog);

      return $response;
    }

    $view = new PhabricatorStandardPageView();
    $view->setRequest($this->getRequest());
    $view->appendChild($content);

    $response = new AphrontWebpageResponse();
    $response->setContent($view->render());

    return $response;
  }

  public function willSendResponse(AphrontResponse $response) {
    $request = $this->getRequest();
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
    } else if ($response instanceof Aphront404Response) {

      $failure = new AphrontRequestFailureView();
      $failure->setHeader('404 Not Found');
      $failure->appendChild(
        '<p>The page you requested was not found.</p>');

      $view = new PhabricatorStandardPageView();
      $view->setTitle('404 Not Found');
      $view->setRequest($this->getRequest());
      $view->appendChild($failure);

      $response = new AphrontWebpageResponse();
      $response->setContent($view->render());
      $response->setHTTPResponseCode(404);
      return $response;
    }

    return $response;
  }

  public function build404Controller() {
    return array(new Phabricator404Controller($this->getRequest()), array());
  }


}
