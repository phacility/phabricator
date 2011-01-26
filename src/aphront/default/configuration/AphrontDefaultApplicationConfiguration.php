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
      '/repository/' => array(
        '$'                     => 'RepositoryListController',
        'new/$'                 => 'RepositoryEditController',
        'edit/(?<id>\d+)/$'     => 'RepositoryEditController',
        'delete/(?<id>\d+)/$'   => 'RepositoryDeleteController',
      ),
      '/' => array(
        '$'                     => 'PhabricatorDirectoryMainController',
      ),
      '/directory/' => array(
        'item/$'
          => 'PhabricatorDirectoryItemListController',
        'item/edit/(?:(?<id>\d+)/)?$'
          => 'PhabricatorDirectoryItemEditController',
        'item/delete/(?<id>\d+)/'
          => 'PhabricatorDirectoryItemDeleteController',
        'category/$'
          => 'PhabricatorDirectoryCategoryListController',
        'category/edit/(?:(?<id>\d+)/)?$'
          => 'PhabricatorDirectoryCategoryEditController',
        'category/delete/(?<id>\d+)/'
          => 'PhabricatorDirectoryCategoryDeleteController',
      ),
      '/file/' => array(
        '$' => 'PhabricatorFileListController',
        'upload/$' => 'PhabricatorFileUploadController',
        '(?<view>info)/(?<phid>[^/]+)/' => 'PhabricatorFileViewController',
        '(?<view>view)/(?<phid>[^/]+)/' => 'PhabricatorFileViewController',
        '(?<view>download)/(?<phid>[^/]+)/' => 'PhabricatorFileViewController',
      ),
      '/phid/' => array(
        '$' => 'PhabricatorPHIDLookupController',
        'list/$' => 'PhabricatorPHIDListController',
        'type/$' => 'PhabricatorPHIDTypeListController',
        'type/edit/(?:(?<id>\d+)/)?$' => 'PhabricatorPHIDTypeEditController',
        'new/$' => 'PhabricatorPHIDAllocateController',
      ),
      '/people/' => array(
        '$' => 'PhabricatorPeopleListController',
        'edit/(?:(?<username>\w+)/)?$' => 'PhabricatorPeopleEditController',
      ),
      '/p/(?<username>\w+)/$' => 'PhabricatorPeopleProfileController',
      '/conduit/' => array(
        '$' => 'PhabricatorConduitConsoleController',
        'method/(?<method>[^/]+)$' => 'PhabricatorConduitConsoleController',
        'log/$' => 'PhabricatorConduitLogController',
      ),
      '/api/(?<method>[^/]+)$' => 'PhabricatorConduitAPIController',

      '/differential/' => array(
        '$' => 'DifferentialRevisionListController',
        'diff/(?<id>\d+)/$'       => 'DifferentialDiffViewController',
        'changeset/(?<id>\d+)/$'  => 'DifferentialChangesetViewController',
        'revision/edit/(?:(?<id>\d+)/)?$'
          => 'DifferentialRevisionEditController',
      ),

      '/res/' => array(
        '(?<hash>[a-f0-9]{8})/(?<path>.+\.(?:css|js))$'
          => 'CelerityResourceController',
      ),

      '/typeahead/' => array(
        'common/(?<type>\w+)/$'
          => 'PhabricatorTypeaheadCommonDatasourceController',
      ),

      '/mail/' => array(
        '$' => 'PhabricatorMetaMTAListController',
        'send/$' => 'PhabricatorMetaMTASendController',
        'view/(?<id>\d+)/$' => 'PhabricatorMetaMTAViewController',
      )
    );
  }

  public function buildRequest() {
    $request = new AphrontRequest($this->getHost(), $this->getPath());
    $request->setRequestData($_GET + $_POST);
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

    $view = new PhabricatorStandardPageView();
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
        $view->appendChild(
          '<div style="padding: 2em 0;">'.
            $response->buildResponseString().
          '</div>');
        $response = new AphrontWebpageResponse();
        $response->setContent($view->render());
        return $response;
      }
    }

    return $response;
  }


}
