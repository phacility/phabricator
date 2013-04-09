<?php

/**
 * @group phriction
 */
final class PhrictionListController
  extends PhrictionController {

  private $view;

  private $documents;
  private $handles;

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

    $this->documents = $query->executeWithCursorPager($pager);

    $changeref_docs = array();
    if ($this->view == 'updates') {
      // Loading some documents here since they may not appear in the query
      // results.
      $changeref_ids = array_filter(mpull(
        mpull($this->documents, 'getContent'), 'getChangeRef'));
      if ($changeref_ids) {
        $changeref_docs = id(new PhrictionDocumentQuery())
          ->setViewer($user)
          ->withIDs($changeref_ids)
          ->execute();
      }
    }

    $phids = array();
    foreach ($this->documents as $document) {
      $phids[] = $document->getContent()->getAuthorPHID();
      if ($document->hasProject()) {
        $phids[] = $document->getProject()->getPHID();
      }
    }

    $this->handles = $this->loadViewerHandles($phids);

    $list = new PhabricatorObjectItemListView();

    foreach ($this->documents as $document) {
      if ($this->view == 'updates') {
        $list->addItem(
          $this->buildItemForUpdates($document, $changeref_docs));
      } else {
        $list->addItem(
          $this->buildItemTheCasualWay($document));
      }
    }

    $nav->appendChild($list);
    $nav->appendChild($pager);

    return $this->buildApplicationPage(
      $nav,
      array(
        'title' => pht('Document Index'),
        'dust' => true,
      ));
  }

  private function buildItemTheCasualWay(PhrictionDocument $document) {
    $user = $this->getRequest()->getUser();

    $project_link = null;
    if ($document->hasProject()) {
      $project_phid = $document->getProject()->getPHID();
      $project_link = $this->handles[$project_phid]->renderLink();
    }

    $content = $document->getContent();
    $author = $this->handles[$content->getAuthorPHID()]->renderLink();
    $title = $content->getTitle();

    $slug = $document->getSlug();
    $slug_uri = PhrictionDocument::getSlugURI($slug);
    $edit_uri = '/phriction/edit/' . $document->getID() . '/';
    $history_uri = PhrictionDocument::getSlugURI($slug, 'history');

    $item = id(new PhabricatorObjectItemView())
      ->setHeader($title)
      ->setHref($slug_uri)
      ->addAttribute(pht('By %s', $author))
      ->addAttribute(pht('Updated: %s',
        phabricator_datetime($content->getDateCreated(), $user)))
      ->addAttribute($slug_uri);

    if ($project_link) {
      $item->addAttribute(pht('Project %s', $project_link));
    }

    return $item;
  }

  private function buildItemForUpdates(PhrictionDocument $document,
    array $docs_from_refs) {

    $user = $this->getRequest()->getUser();

    $content = $document->getContent();
    $version = $content->getVersion();
    $author = $this->handles[$content->getAuthorPHID()]->renderLink();
    $title = $content->getTitle();

    $slug = $document->getSlug();
    $slug_uri = PhrictionDocument::getSlugURI($slug);
    $document_link = hsprintf('<a href="%s">%s</a>', $slug_uri, $title);

    $change_type = $content->getChangeType();
    switch ($content->getChangeType()) {
      case PhrictionChangeType::CHANGE_DELETE:
        $change_type = pht('%s deleted %s', $author, $document_link);
        $color = 'red';
        break;
      case PhrictionChangeType::CHANGE_EDIT:
        $change_type = pht('%s edited %s', $author, $document_link);
        $color = 'blue';
        break;
      case PhrictionChangeType::CHANGE_MOVE_HERE:
      case PhrictionChangeType::CHANGE_MOVE_AWAY:
        $change_ref = $content->getChangeRef();
        $ref_doc = $docs_from_refs[$change_ref];
        $ref_doc_slug = PhrictionDocument::getSlugURI(
          $ref_doc->getSlug());
        $ref_doc_link = hsprintf('<a href="%s">%1$s</a>', $ref_doc_slug);

        if ($change_type == PhrictionChangeType::CHANGE_MOVE_HERE) {
          $change_type = pht('%s moved %s from %s', $author, $document_link,
            $ref_doc_link);
          $color = 'yellow';
        } else {
          $change_type = pht('%s moved %s to %s', $author, $document_link,
            $ref_doc_link);
          $color = 'orange';
        }
        break;
      default:
        throw new Exception("Unknown change type!");
        break;
    }

    $item = id(new PhabricatorObjectItemView())
      ->setHeader($change_type)
      ->setBarColor($color)
      ->addAttribute(phabricator_datetime($content->getDateCreated(), $user))
      ->addAttribute($slug_uri);

    if ($content->getDescription()) {
      $item->addAttribute($content->getDescription());
    }

    if ($version > 1) {
      $diff_uri = new PhutilURI('/phriction/diff/'.$document->getID().'/');
      $uri = $diff_uri->alter('l', $version - 1)->alter('r', $version);
      $item->addIcon('history', pht('View Change'), $uri);
    } else {
      $item->addIcon('history', pht('No diff available'));
    }

    return $item;
  }

}
