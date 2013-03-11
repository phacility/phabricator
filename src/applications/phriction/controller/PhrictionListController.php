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
      $project_link = 'None';
      if ($document->hasProject()) {
        $project_phid = $document->getProject()->getPHID();
        $project_link = $handles[$project_phid]->renderLink();
      }

      $content = $document->getContent();

      $change_type = null;
      if ($this->view == 'updates') {
        $change_type = $content->getChangeType();
        switch ($content->getChangeType()) {
          case PhrictionChangeType::CHANGE_DELETE:
          case PhrictionChangeType::CHANGE_EDIT:
            $change_type = PhrictionChangeType::getChangeTypeLabel(
              $change_type);
            break;
          case PhrictionChangeType::CHANGE_MOVE_HERE:
          case PhrictionChangeType::CHANGE_MOVE_AWAY:
            $change_ref = $content->getChangeRef();
            $ref_doc = $documents[$change_ref];
            $ref_doc_slug = PhrictionDocument::getSlugURI(
              $ref_doc->getSlug());
            $ref_doc_link = hsprintf('<br /><a href="%s">%s</a>', $ref_doc_slug,
              phutil_utf8_shorten($ref_doc_slug, 15));

            if ($change_type == PhrictionChangeType::CHANGE_MOVE_HERE) {
              $change_type = pht('Moved from %s', $ref_doc_link);
            } else {
              $change_type = pht('Moved to %s', $ref_doc_link);
            }
            break;
          default:
            throw new Exception("Unknown change type!");
            break;
        }
      }

      $rows[] = array(
        $handles[$content->getAuthorPHID()]->renderLink(),
        $change_type,
        phutil_tag(
          'a',
          array(
            'href' => PhrictionDocument::getSlugURI($document->getSlug()),
          ),
          $content->getTitle()),
        $project_link,
        phabricator_date($content->getDateCreated(), $user),
        phabricator_time($content->getDateCreated(), $user),
      );
    }

    $document_table = new AphrontTableView($rows);
    $document_table->setHeaders(
      array(
        pht('Last Editor'),
        pht('Change Type'),
        pht('Title'),
        pht('Project'),
        pht('Last Update'),
        pht('Time'),
      ));

    $document_table->setColumnClasses(
      array(
        '',
        '',
        'wide pri',
        '',
        'right',
        'right',
      ));

    $document_table->setColumnVisibility(
      array(
        true,
        $this->view == 'updates',
        true,
        true,
        true,
        true,
      ));

    $panel = new AphrontPanelView();
    $panel->setNoBackground();
    $panel->appendChild($document_table);
    $panel->appendChild($pager);

    $nav->appendChild($panel);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => pht('Phriction Main'),
        'dust' => true,
      ));
  }

}
