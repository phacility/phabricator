<?php

/**
 * @group phriction
 */
final class PhrictionDocumentController
  extends PhrictionController {

  private $slug;

  public function willProcessRequest(array $data) {
    $this->slug = $data['slug'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $slug = PhabricatorSlug::normalize($this->slug);
    if ($slug != $this->slug) {
      $uri = PhrictionDocument::getSlugURI($slug);
      // Canonicalize pages to their one true URI.
      return id(new AphrontRedirectResponse())->setURI($uri);
    }

    require_celerity_resource('phriction-document-css');

    $document = id(new PhrictionDocument())->loadOneWhere(
      'slug = %s',
      $slug);

    $version_note = null;

    if (!$document) {

      $document = new PhrictionDocument();

      if (PhrictionDocument::isProjectSlug($slug)) {
        $project = id(new PhabricatorProject())->loadOneWhere(
          'phrictionSlug = %s',
          PhrictionDocument::getProjectSlugIdentifier($slug));
        if (!$project) {
          return new Aphront404Response();
        }
      }
      $create_uri = '/phriction/edit/?slug='.$slug;
      $page_content = hsprintf(
        '<div class="phriction-content">'.
          '<em>No content here!</em><br />'.
          'No document found at <tt>%s</tt>. '.
          'You can <strong><a href="%s">create a new document</a></strong>.'.
        '</div>',
        $slug,
        $create_uri);
      $page_title = pht('Page Not Found');
    } else {
      $version = $request->getInt('v');
      if ($version) {
        $content = id(new PhrictionContent())->loadOneWhere(
          'documentID = %d AND version = %d',
          $document->getID(),
          $version);
        if (!$content) {
          return new Aphront404Response();
        }

        if ($content->getID() != $document->getContentID()) {
          $vdate = phabricator_datetime($content->getDateCreated(), $user);
          $version_note = new AphrontErrorView();
          $version_note->setSeverity(AphrontErrorView::SEVERITY_NOTICE);
          $version_note->setTitle('Older Version');
          $version_note->appendChild(
            pht('You are viewing an older version of this document, as it '.
            'appeared on %s.', $vdate));
        }
      } else {
        $content = id(new PhrictionContent())->load($document->getContentID());
      }
      $page_title = $content->getTitle();

      $project_phid = null;
      if (PhrictionDocument::isProjectSlug($slug)) {
        $project = id(new PhabricatorProject())->loadOneWhere(
          'phrictionSlug = %s',
          PhrictionDocument::getProjectSlugIdentifier($slug));
        if ($project) {
          $project_phid = $project->getPHID();
        }
      }

      $phids = array_filter(
        array(
          $content->getAuthorPHID(),
          $project_phid,
        ));
      $handles = $this->loadViewerHandles($phids);

      $age = time() - $content->getDateCreated();
      $age = floor($age / (60 * 60 * 24));

      if ($age < 1) {
        $when = 'today';
      } else if ($age == 1) {
        $when = 'yesterday';
      } else {
        $when = "{$age} days ago";
      }


      $project_info = null;
      if ($project_phid) {
        $project_info = hsprintf(
          '<br />This document is about the project %s.',
          $handles[$project_phid]->renderLink());
      }

      $index_link = phutil_tag(
        'a',
        array(
          'href' => '/phriction/',
        ),
        pht('Document Index'));

      $byline = hsprintf(
        '<div class="phriction-byline">Last updated %s by %s.%s</div>',
        $when,
        $handles[$content->getAuthorPHID()]->renderLink(),
        $project_info);


      $doc_status = $document->getStatus();
      if ($doc_status == PhrictionDocumentStatus::STATUS_EXISTS) {
        $core_content = $content->renderContent($user);
      } else if ($doc_status == PhrictionDocumentStatus::STATUS_DELETED) {
        $notice = new AphrontErrorView();
        $notice->setSeverity(AphrontErrorView::SEVERITY_NOTICE);
        $notice->setTitle('Document Deleted');
        $notice->appendChild(
          pht('This document has been deleted. You can edit it to put new '.
          'content here, or use history to revert to an earlier version.'));
        $core_content = $notice->render();
      } else {
        throw new Exception("Unknown document status '{$doc_status}'!");
      }

      $page_content = hsprintf(
        '<div class="phriction-content">%s%s%s</div>',
        $index_link,
        $byline,
        $core_content);
    }

    if ($version_note) {
      $version_note = $version_note->render();
    }

    $children = $this->renderDocumentChildren($slug);

    $crumbs = $this->buildApplicationCrumbs();
    $crumb_views = $this->renderBreadcrumbs($slug);
    foreach ($crumb_views as $view) {
      $crumbs->addCrumb($view);
    }

    $actions = $this->buildActionView($user, $document);

    $header = id(new PhabricatorHeaderView())
      ->setHeader($page_title);

    return $this->buildApplicationPage(
      array(
        $crumbs->render(),
        $header->render(),
        $actions->render(),
        $version_note,
        $page_content,
        $children,
      ),
      array(
        'title'   => $page_title,
        'device'  => true,
      ));

  }

  private function buildActionView(
    PhabricatorUser $user,
    PhrictionDocument $document) {
    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $user,
      $document,
      PhabricatorPolicyCapability::CAN_EDIT);

    $slug = PhabricatorSlug::normalize($this->slug);

    $action_view = id(new PhabricatorActionListView())
      ->setUser($user)
      ->setObject($document)
      ->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Edit Document'))
          ->setIcon('edit')
          ->setHref('/phriction/edit/'.$document->getID().'/'));

    if ($document->getStatus() == PhrictionDocumentStatus::STATUS_EXISTS) {
      $action_view->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Delete Document'))
          ->setIcon('delete')
          ->setHref('/phriction/delete/'.$document->getID().'/')
          ->setWorkflow(true));
    }

    return
      $action_view->addAction(
        id(new PhabricatorActionView())
        ->setName(pht('View History'))
        ->setIcon('history')
        ->setHref(PhrictionDocument::getSlugURI($slug, 'history')));
  }

  private function renderDocumentChildren($slug) {
    $document_dao = new PhrictionDocument();
    $content_dao = new PhrictionContent();
    $conn = $document_dao->establishConnection('r');

    $limit = 50;
    $d_child = PhabricatorSlug::getDepth($slug) + 1;
    $d_grandchild = PhabricatorSlug::getDepth($slug) + 2;

    // Select children and grandchildren.
    $children = queryfx_all(
      $conn,
      'SELECT d.slug, d.depth, c.title FROM %T d JOIN %T c
        ON d.contentID = c.id
        WHERE d.slug LIKE %> AND d.depth IN (%d, %d)
          AND d.status = %d
        ORDER BY d.depth, c.title LIMIT %d',
      $document_dao->getTableName(),
      $content_dao->getTableName(),
      ($slug == '/' ? '' : $slug),
      $d_child,
      $d_grandchild,
      PhrictionDocumentStatus::STATUS_EXISTS,
      $limit);

    if (!$children) {
      return;
    }

    // We're going to render in one of three modes to try to accommodate
    // different information scales:
    //
    //  - If we found fewer than $limit rows, we know we have all the children
    //    and grandchildren and there aren't all that many. We can just render
    //    everything.
    //  - If we found $limit rows but the results included some grandchildren,
    //    we just throw them out and render only the children, as we know we
    //    have them all.
    //  - If we found $limit rows and the results have no grandchildren, we
    //    have a ton of children. Render them and then let the user know that
    //    this is not an exhaustive list.

    if (count($children) == $limit) {
      $more_children = true;
      foreach ($children as $child) {
        if ($child['depth'] == $d_grandchild) {
          $more_children = false;
        }
      }
      $show_grandchildren = false;
    } else {
      $show_grandchildren = true;
      $more_children = false;
    }

    $grandchildren = array();
    foreach ($children as $key => $child) {
      if ($child['depth'] == $d_child) {
        continue;
      } else {
        unset($children[$key]);
        if ($show_grandchildren) {
          $ancestors = PhabricatorSlug::getAncestry($child['slug']);
          $grandchildren[end($ancestors)][] = $child;
        }
      }
    }

    // Fill in any missing children.
    $known_slugs = ipull($children, null, 'slug');
    foreach ($grandchildren as $slug => $ignored) {
      if (empty($known_slugs[$slug])) {
        $children[] = array(
          'slug'    => $slug,
          'depth'   => $d_child,
          'title'   => PhabricatorSlug::getDefaultTitle($slug),
          'empty'   => true,
        );
      }
    }

    $children = isort($children, 'title');

    $list = array();
    foreach ($children as $child) {
      $list[] = hsprintf('<li>');
      $list[] = $this->renderChildDocumentLink($child);
      $grand = idx($grandchildren, $child['slug'], array());
      if ($grand) {
        $list[] = hsprintf('<ul>');
        foreach ($grand as $grandchild) {
          $list[] = hsprintf('<li>');
          $list[] = $this->renderChildDocumentLink($grandchild);
          $list[] = hsprintf('</li>');
        }
        $list[] = hsprintf('</ul>');
      }
      $list[] = hsprintf('</li>');
    }
    if ($more_children) {
      $list[] = phutil_tag('li', array(), pht('More...'));
    }

    return hsprintf(
      '<div class="phriction-children">'.
        '<div class="phriction-children-header">%s</div>'.
        '%s'.
      '</div>',
      pht('Document Hierarchy'),
      phutil_tag('ul', array(), $list));
  }

  private function renderChildDocumentLink(array $info) {
    $title = nonempty($info['title'], pht('(Untitled Document)'));
    $item = phutil_tag(
      'a',
      array(
        'href' => PhrictionDocument::getSlugURI($info['slug']),
      ),
      $title);

    if (isset($info['empty'])) {
      $item = phutil_tag('em', array(), $item);
    }

    return $item;
  }

}
