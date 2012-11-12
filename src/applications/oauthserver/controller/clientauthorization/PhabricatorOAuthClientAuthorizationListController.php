<?php

/**
 * @group oauthserver
 */
final class PhabricatorOAuthClientAuthorizationListController
extends PhabricatorOAuthClientAuthorizationBaseController {

  protected function getFilter() {
    return 'clientauthorization';
  }

  public function processRequest() {
    $title        = 'OAuth Client Authorizations';
    $request      = $this->getRequest();
    $current_user = $request->getUser();
    $offset       = $request->getInt('offset', 0);
    $page_size    = 100;
    $pager        = new AphrontPagerView();
    $request_uri  = $request->getRequestURI();
    $pager->setURI($request_uri, 'offset');
    $pager->setPageSize($page_size);
    $pager->setOffset($offset);

    $query = new PhabricatorOAuthClientAuthorizationQuery();
    $query->withUserPHIDs(array($current_user->getPHID()));
    $authorizations = $query->executeWithOffsetPager($pager);

    $client_authorizations = mpull($authorizations, null, 'getClientPHID');
    $client_phids          = array_keys($client_authorizations);
    if ($client_phids) {
      $clients = id(new PhabricatorOAuthServerClient())
        ->loadAllWhere('phid in (%Ls)',
                       $client_phids);
    } else {
      $clients = array();
    }
    $client_dict = mpull($clients, null, 'getPHID');

    $rows      = array();
    $rowc      = array();
    $highlight = $this->getHighlightPHIDs();
    foreach ($client_authorizations as $client_phid => $authorization) {
      $client  = $client_dict[$client_phid];
      $created = phabricator_datetime($authorization->getDateCreated(),
                                      $current_user);
      $updated = phabricator_datetime($authorization->getDateModified(),
        $current_user);
      $scope_doc_href = PhabricatorEnv::getDoclink(
        'article/Using_the_Phabricator_OAuth_Server.html#scopes'
      );
      $row = array(
        phutil_render_tag(
          'a',
          array(
            'href' => $client->getViewURI(),
          ),
          phutil_escape_html($client->getName())
        ),
        phutil_render_tag(
          'a',
          array(
            'href' => $scope_doc_href,
          ),
          $authorization->getScopeString()
        ),
        phabricator_datetime(
          $authorization->getDateCreated(),
          $current_user
        ),
        phabricator_datetime(
          $authorization->getDateModified(),
          $current_user
        ),
        phutil_render_tag(
          'a',
          array(
            'class' => 'small button grey',
            'href'  => $authorization->getEditURI(),
          ),
          'Edit'
        ),
      );

      $rows[] = $row;
      if (isset($highlight[$authorization->getPHID()])) {
        $rowc[] = 'highlighted';
      } else {
        $rowc[] = '';
      }
    }

    $panel = $this->buildClientAuthorizationList($rows, $rowc, $title);

    return $this->buildStandardPageResponse(
      array(
        $this->getNoticeView(),
        $panel->appendChild($pager),
      ),
      array('title' => $title)
    );
  }

  private function buildClientAuthorizationList($rows, $rowc, $title) {
    $table = new AphrontTableView($rows);
    $table->setRowClasses($rowc);
    $table->setHeaders(
      array(
        'Client',
        'Scope',
        'Created',
        'Updated',
        '',
      ));
    $table->setColumnClasses(
      array(
        'wide pri',
        '',
        '',
        '',
        'action',
      ));
    if (empty($rows)) {
      $table->setNoDataString(
        'You have not authorized any clients for this OAuthServer.'
      );
    }

    $panel = new AphrontPanelView();
    $panel->appendChild($table);
    $panel->setHeader($title);

    return $panel;
  }

  private function getNoticeView() {
    $edited  = $this->getRequest()->getStr('edited');
    $deleted = $this->getRequest()->getBool('deleted');
    if ($edited) {
      $edited = phutil_escape_html($edited);
      $title  = 'Successfully edited client authorization.';
    } else if ($deleted) {
      $title = 'Successfully deleted client authorization.';
    } else {
      $title = null;
    }

    if ($title) {
      $view   = new AphrontErrorView();
      $view->setTitle($title);
      $view->setSeverity(AphrontErrorView::SEVERITY_NOTICE);
    } else {
      $view = null;
    }

    return $view;
  }
}
