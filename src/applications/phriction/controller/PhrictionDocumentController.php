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
    $core_content = '';
    $move_notice = '';
    $properties = null;

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

      $notice = new AphrontErrorView();
      $notice->setSeverity(AphrontErrorView::SEVERITY_NODATA);
      $notice->setTitle(pht('No content here!'));
      $notice->appendChild(
        pht(
          'No document found at %s. You can <strong>'.
            '<a href="%s">create a new document here</a></strong>.',
          phutil_tag('tt', array(), $slug),
          $create_uri));
      $core_content = $notice;

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

      $subscribers = PhabricatorSubscribersQuery::loadSubscribersForPHID(
        $document->getPHID());
      $properties = $this
        ->buildPropertyListView($document, $content, $slug, $subscribers);

      $doc_status = $document->getStatus();
      $current_status = $content->getChangeType();
      if ($current_status == PhrictionChangeType::CHANGE_EDIT ||
        $current_status == PhrictionChangeType::CHANGE_MOVE_HERE) {

        $core_content = $content->renderContent($user);
      } else if ($current_status == PhrictionChangeType::CHANGE_DELETE) {
        $notice = new AphrontErrorView();
        $notice->setSeverity(AphrontErrorView::SEVERITY_NOTICE);
        $notice->setTitle(pht('Document Deleted'));
        $notice->appendChild(
          pht('This document has been deleted. You can edit it to put new '.
          'content here, or use history to revert to an earlier version.'));
        $core_content = $notice->render();
      } else if ($current_status == PhrictionChangeType::CHANGE_STUB) {
        $notice = new AphrontErrorView();
        $notice->setSeverity(AphrontErrorView::SEVERITY_NOTICE);
        $notice->setTitle(pht('Empty Document'));
        $notice->appendChild(
          pht('This document is empty. You can edit it to put some proper '.
          'content here.'));
        $core_content = $notice->render();
      } else if ($current_status == PhrictionChangeType::CHANGE_MOVE_AWAY) {
        $new_doc_id = $content->getChangeRef();
        $new_doc = new PhrictionDocument();
        $new_doc->load($new_doc_id);

        $slug_uri = PhrictionDocument::getSlugURI($new_doc->getSlug());

        $notice = new AphrontErrorView();
        $notice->setSeverity(AphrontErrorView::SEVERITY_NOTICE);
        $notice->setTitle(pht('Document Moved'));
        $notice->appendChild(phutil_tag('p', array(),
          pht('This document has been moved to %s. You can edit it to put new '.
          'content here, or use history to revert to an earlier version.',
            phutil_tag('a', array('href' => $slug_uri), $slug_uri))));
        $core_content = $notice->render();
      } else {
        throw new Exception("Unknown document status '{$doc_status}'!");
      }

      $move_notice = null;
      if ($current_status == PhrictionChangeType::CHANGE_MOVE_HERE) {
        $from_doc_id = $content->getChangeRef();
        $from_doc = id(new PhrictionDocument())->load($from_doc_id);
        $slug_uri = PhrictionDocument::getSlugURI($from_doc->getSlug());

        $move_notice = id(new AphrontErrorView())
          ->setSeverity(AphrontErrorView::SEVERITY_NOTICE)
          ->appendChild(pht('This document was moved from %s',
            phutil_tag('a', array('href' => $slug_uri), $slug_uri)))
          ->render();
      }
    }

    if ($version_note) {
      $version_note = $version_note->render();
    }

    $children = $this->renderDocumentChildren($slug);

    $actions = $this->buildActionView($user, $document);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->setActionList($actions);
    $crumb_views = $this->renderBreadcrumbs($slug);
    foreach ($crumb_views as $view) {
      $crumbs->addCrumb($view);
    }

    $header = id(new PhabricatorHeaderView())
      ->setHeader($page_title);

    $page_content = hsprintf(
      '<div class="phriction-wrap">
        <div class="phriction-content">
          %s%s%s%s%s
        </div>
        <div class="phriction-fake-space"></div>
      </div>',
      $header,
      $actions,
      $properties,
      $move_notice,
      $core_content);

    $core_page = phutil_tag(
      'div',
        array(
          'class' => 'phriction-offset'
        ),
        array(
          $page_content,
          $children,
        ));

    return $this->buildApplicationPage(
      array(
        $crumbs->render(),
        $core_page,
      ),
      array(
        'title'   => $page_title,
        'device'  => true,
        'dust'    => true,
      ));

  }

  private function buildPropertyListView(
    PhrictionDocument $document,
    PhrictionContent $content,
    $slug,
    array $subscribers) {

    $viewer = $this->getRequest()->getUser();
    $view = id(new PhabricatorPropertyListView())
      ->setUser($viewer)
      ->setObject($document);

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

    if ($subscribers) {
      $phids = array_merge($phids, $subscribers);
    }

    $this->loadHandles($phids);

    $project_info = null;
    if ($project_phid) {
      $view->addProperty(
        pht('Project Info'),
        $this->getHandle($project_phid)->renderLink());
    }

    $view->addProperty(
      pht('Last Author'),
      $this->getHandle($content->getAuthorPHID())->renderLink());

    $age = time() - $content->getDateCreated();
    $age = floor($age / (60 * 60 * 24));
    if ($age < 1) {
      $when = pht('Today');
    } else if ($age == 1) {
      $when = pht('Yesterday');
    } else {
      $when = pht("%d Days Ago", $age);
    }
    $view->addProperty(pht('Last Updated'), $when);

    if ($subscribers) {
      $subscribers = $this->renderHandlesForPHIDs($subscribers);
      $view->addProperty(pht('Subscribers'), $subscribers);
    }

    return $view;
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
      ->setObject($document);

    if (!$document->getID()) {
      return $action_view->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Create This Document'))
          ->setIcon('create')
          ->setHref('/phriction/edit/?slug='.$slug));
    }

    $action_view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Document'))
        ->setIcon('edit')
        ->setHref('/phriction/edit/'.$document->getID().'/'));

    if ($document->getStatus() == PhrictionDocumentStatus::STATUS_EXISTS) {
      $action_view->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Move Document'))
          ->setIcon('move')
          ->setHref('/phriction/move/'.$document->getID().'/')
          ->setWorkflow(true));

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
          AND d.status IN (%Ld)
        ORDER BY d.depth, c.title LIMIT %d',
      $document_dao->getTableName(),
      $content_dao->getTableName(),
      ($slug == '/' ? '' : $slug),
      $d_child,
      $d_grandchild,
      array(
        PhrictionDocumentStatus::STATUS_EXISTS,
        PhrictionDocumentStatus::STATUS_STUB,
      ),
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
      '<div class="phriction-wrap">
        <div class="phriction-children">
        <div class="phriction-children-header">%s</div>
        %s
        </div>
      </div>',
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

  protected function getDocumentSlug() {
    return $this->slug;
  }

}
