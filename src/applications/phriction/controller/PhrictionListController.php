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
      'active'  => pht('Active Documents'),
      'all'     => pht('All Documents'),
      'updates' => pht('Recently Updated'),
    );

    if (empty($views[$this->view])) {
      $this->view = 'active';
    }

    $nav = $this->buildSideNavView($this->view);

    $header = id(new PhabricatorHeaderView())
      ->setHeader($views[$this->view]);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(id(new PhabricatorCrumbView())
      ->setName($views[$this->view])
      ->setHref($this->getApplicationURI('list/' . $this->view)));

    $nav->appendChild(
      array(
        $crumbs,
        $header,
      ));

    $pager = id(new AphrontCursorPagerView())
      ->readFromRequest($request);

    $query = id(new PhrictionDocumentQuery())
      ->setViewer($user);

    switch ($this->view) {
      case 'active':
        $query->withStatus(PhrictionDocumentQuery::STATUS_OPEN);
        break;
      case 'all':
        $query->withStatus(PhrictionDocumentQuery::STATUS_NONSTUB);
        break;
      case 'updates':
        $query->withStatus(PhrictionDocumentQuery::STATUS_NONSTUB);
        $query->setOrder(PhrictionDocumentQuery::ORDER_UPDATED);
        break;
      default:
        throw new Exception("Unknown view '{$this->view}'!");
    }

    $documents = $query->executeWithCursorPager($pager);

    $phids = array();
    foreach ($documents as $document) {
      $phids[] = $document->getContent()->getAuthorPHID();
      if ($document->hasProject()) {
        $phids[] = $document->getProject()->getPHID();
      }
    }

    $handles = $this->loadViewerHandles($phids);

    $rows = array();
    foreach ($documents as $document) {
      $content = $document->getContent();
      $rows[] = array(
        $handles[$content->getAuthorPHID()]->renderLink(),
        phutil_tag(
          'a',
          array(
            'href' => PhrictionDocument::getSlugURI($document->getSlug()),
          ),
          $content->getTitle()),
        phabricator_date($content->getDateCreated(), $user),
        phabricator_time($content->getDateCreated(), $user),
      );
    }

    $document_table = new AphrontTableView($rows);
    $document_table->setHeaders(
      array(
        pht('Last Editor'),
        pht('Title'),
        pht('Last Update'),
        pht('Time'),
      ));

    $document_table->setColumnClasses(
      array(
        '',
        'wide pri',
        'right',
        'right',
      ));

    $view_header = $views[$this->view];

    $panel = new AphrontPanelView();
    $panel->setNoBackground();
    $panel->appendChild($document_table);
    $panel->appendChild($pager);

    $nav->appendChild($panel);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => pht('Phriction Main'),
      ));
  }

}
