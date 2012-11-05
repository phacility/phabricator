<?php

/**
 * @group oauthserver
 */
final class PhabricatorOAuthClientListController
extends PhabricatorOAuthClientBaseController {

  public function getFilter() {
    return 'client';
  }

  public function processRequest() {
    $title        = 'OAuth Clients';
    $request      = $this->getRequest();
    $current_user = $request->getUser();
    $offset       = $request->getInt('offset', 0);
    $page_size    = 100;
    $pager        = new AphrontPagerView();
    $request_uri  = $request->getRequestURI();
    $pager->setURI($request_uri, 'offset');
    $pager->setPageSize($page_size);
    $pager->setOffset($offset);

    $query = new PhabricatorOAuthServerClientQuery();
    $query->withCreatorPHIDs(array($current_user->getPHID()));
    $clients = $query->executeWithOffsetPager($pager);

    $rows      = array();
    $rowc      = array();
    $highlight = $this->getHighlightPHIDs();
    foreach ($clients as $client) {
      $row = array(
        phutil_render_tag(
          'a',
          array(
            'href' => $client->getViewURI(),
          ),
          phutil_escape_html($client->getName())
        ),
        $client->getPHID(),
        $client->getSecret(),
        phutil_render_tag(
          'a',
          array(
            'href' => $client->getRedirectURI(),
          ),
          phutil_escape_html($client->getRedirectURI())
        ),
        phutil_render_tag(
          'a',
          array(
            'class' => 'small button grey',
            'href'  => $client->getEditURI(),
          ),
          'Edit'
        ),
      );

      $rows[] = $row;
      if (isset($highlight[$client->getPHID()])) {
        $rowc[] = 'highlighted';
      } else {
        $rowc[] = '';
      }
    }

    $panel = $this->buildClientList($rows, $rowc, $title);

    return $this->buildStandardPageResponse(
      array(
        $this->getNoticeView(),
        $panel->appendChild($pager)
      ),
      array('title' => $title)
    );
  }

  private function buildClientList($rows, $rowc, $title) {
    $table = new AphrontTableView($rows);
    $table->setRowClasses($rowc);
    $table->setHeaders(
      array(
        'Client',
        'ID',
        'Secret',
        'Redirect URI',
        '',
      ));
    $table->setColumnClasses(
      array(
        '',
        '',
        '',
        '',
        'action',
      ));
    if (empty($rows)) {
      $table->setNoDataString(
        'You have not created any clients for this OAuthServer.'
      );
    }

    $panel = new AphrontPanelView();
    $panel->appendChild($table);
    $panel->setHeader($title);

    return $panel;
  }

  private function getNoticeView() {
    $edited  = $this->getRequest()->getStr('edited');
    $new     = $this->getRequest()->getStr('new');
    $deleted = $this->getRequest()->getBool('deleted');
    if ($edited) {
      $edited = phutil_escape_html($edited);
      $title  = 'Successfully edited client with id '.$edited.'.';
    } else if ($new) {
      $new   = phutil_escape_html($new);
      $title = 'Successfully created client with id '.$new.'.';
    } else if ($deleted) {
      $title = 'Successfully deleted client.';
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
