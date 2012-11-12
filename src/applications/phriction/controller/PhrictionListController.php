<?php

/**
 * @group phriction
 */
final class PhrictionListController
  extends PhrictionController {

  private $view;

  public function willProcessRequest(array $data) {
    $this->view = idx($data, 'view');
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $views = array(
      'all'     => 'All Documents',
      'updates' => 'Recently Updated',
    );

    if (empty($views[$this->view])) {
      $this->view = 'all';
    }

    $nav = new AphrontSideNavView();
    foreach ($views as $view => $name) {
      $nav->addNavItem(
        phutil_render_tag(
          'a',
          array(
            'href'  => '/phriction/list/'.$view.'/',
            'class' => ($this->view == $view)
              ? 'aphront-side-nav-selected'
              : null,
          ),
          phutil_escape_html($name)));
    }

    $pager = new AphrontPagerView();
    $pager->setURI($request->getRequestURI(), 'page');
    $pager->setOffset($request->getInt('page'));

    $documents = $this->loadDocuments($pager);

    $content = mpull($documents, 'getContent');
    $phids = mpull($content, 'getAuthorPHID');

    $handles = $this->loadViewerHandles($phids);


    $rows = array();
    foreach ($documents as $document) {
      $content = $document->getContent();
      $rows[] = array(
        $handles[$content->getAuthorPHID()]->renderLink(),
        phutil_render_tag(
          'a',
          array(
            'href' => PhrictionDocument::getSlugURI($document->getSlug()),
          ),
          phutil_escape_html($content->getTitle())),
        phabricator_date($content->getDateCreated(), $user),
        phabricator_time($content->getDateCreated(), $user),
      );
    }

    $document_table = new AphrontTableView($rows);
    $document_table->setHeaders(
      array(
        'Last Editor',
        'Title',
        'Last Update',
        'Time',
      ));

    $document_table->setColumnClasses(
      array(
        '',
        'wide pri',
        'right',
        'right',
      ));

    $view_headers = array(
      'all'       => 'All Documents',
      'updates'   => 'Recently Updated Documents',
    );
    $view_header = $view_headers[$this->view];

    $panel = new AphrontPanelView();
    $panel->setHeader($view_header);
    $panel->appendChild($document_table);
    $panel->appendChild($pager);

    $nav->appendChild($panel);

    return $this->buildStandardPageResponse($nav,
        array(
          'title' => 'Phriction Main'
        ));
  }

  private function loadDocuments(AphrontPagerView $pager) {

    // TODO: Do we want/need a query object for this?

    $document_dao = new PhrictionDocument();
    $content_dao = new PhrictionContent();
    $conn = $document_dao->establishConnection('r');

    switch ($this->view) {
      case 'all':
        $data = queryfx_all(
          $conn,
          'SELECT * FROM %T ORDER BY id DESC LIMIT %d, %d',
          $document_dao->getTableName(),
          $pager->getOffset(),
          $pager->getPageSize() + 1);
        break;
      case 'updates':

        // TODO: This query is a little suspicious, verify we don't need to key
        // or change it once we get more data.

        $data = queryfx_all(
          $conn,
          'SELECT d.* FROM %T d JOIN %T c ON c.documentID = d.id
            GROUP BY c.documentID
            ORDER BY MAX(c.id) DESC LIMIT %d, %d',
          $document_dao->getTableName(),
          $content_dao->getTableName(),
          $pager->getOffset(),
          $pager->getPageSize() + 1);
        break;
      default:
        throw new Exception("Unknown view '{$this->view}'!");
    }

    $data = $pager->sliceResults($data);

    $documents = $document_dao->loadAllFromArray($data);
    if ($documents) {
      $content = $content_dao->loadAllWhere(
        'documentID IN (%Ld)',
        mpull($documents, 'getID'));
      $content = mpull($content, null, 'getDocumentID');
      foreach ($documents as $document) {
        $document->attachContent($content[$document->getID()]);
      }
    }

    return $documents;
  }

}
