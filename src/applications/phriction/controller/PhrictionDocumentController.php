<?php

final class PhrictionDocumentController
  extends PhrictionController {

  private $slug;

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $this->slug = $request->getURIData('slug');

    $slug = PhabricatorSlug::normalize($this->slug);
    if ($slug != $this->slug) {
      $uri = PhrictionDocument::getSlugURI($slug);
      // Canonicalize pages to their one true URI.
      return id(new AphrontRedirectResponse())->setURI($uri);
    }

    require_celerity_resource('phriction-document-css');

    $document = id(new PhrictionDocumentQuery())
      ->setViewer($viewer)
      ->withSlugs(array($slug))
      ->executeOne();

    $version_note = null;
    $core_content = '';
    $move_notice = '';
    $properties = null;
    $content = null;
    $toc = null;

    if (!$document) {

      $document = PhrictionDocument::initializeNewDocument($viewer, $slug);

      if ($slug == '/') {
        $title = pht('Welcome to Phriction');
        $subtitle = pht('Phriction is a simple and easy to use wiki for '.
          'keeping track of documents and their changes.');
        $page_title = pht('Welcome');
        $create_text = pht('Edit this Document');

      } else {
        $title = pht('No Document Here');
        $subtitle = pht('There is no document here, but you may create it.');
        $page_title = pht('Page Not Found');
        $create_text = pht('Create this Document');
      }

      $create_uri = '/phriction/edit/?slug='.$slug;
      $create_button = id(new PHUIButtonView())
        ->setTag('a')
        ->setText($create_text)
        ->setHref($create_uri)
        ->setColor(PHUIButtonView::GREEN);

      $core_content = id(new PHUIBigInfoView())
        ->setIcon('fa-book')
        ->setTitle($title)
        ->setDescription($subtitle)
        ->addAction($create_button);

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
          $vdate = phabricator_datetime($content->getDateCreated(), $viewer);
          $version_note = new PHUIInfoView();
          $version_note->setSeverity(PHUIInfoView::SEVERITY_NOTICE);
          $version_note->appendChild(
            pht('You are viewing an older version of this document, as it '.
            'appeared on %s.', $vdate));
        }
      } else {
        $content = id(new PhrictionContent())->load($document->getContentID());
      }
      $page_title = $content->getTitle();
      $properties = $this
        ->buildPropertyListView($document, $content, $slug);

      $doc_status = $document->getStatus();
      $current_status = $content->getChangeType();
      if ($current_status == PhrictionChangeType::CHANGE_EDIT ||
        $current_status == PhrictionChangeType::CHANGE_MOVE_HERE) {

        $core_content = $content->renderContent($viewer);
        $toc = $this->getToc($content);

      } else if ($current_status == PhrictionChangeType::CHANGE_DELETE) {
        $notice = new PHUIInfoView();
        $notice->setSeverity(PHUIInfoView::SEVERITY_NOTICE);
        $notice->setTitle(pht('Document Deleted'));
        $notice->appendChild(
          pht('This document has been deleted. You can edit it to put new '.
          'content here, or use history to revert to an earlier version.'));
        $core_content = $notice->render();
      } else if ($current_status == PhrictionChangeType::CHANGE_STUB) {
        $notice = new PHUIInfoView();
        $notice->setSeverity(PHUIInfoView::SEVERITY_NOTICE);
        $notice->setTitle(pht('Empty Document'));
        $notice->appendChild(
          pht('This document is empty. You can edit it to put some proper '.
          'content here.'));
        $core_content = $notice->render();
      } else if ($current_status == PhrictionChangeType::CHANGE_MOVE_AWAY) {
        $new_doc_id = $content->getChangeRef();
        $slug_uri = null;

        // If the new document exists and the viewer can see it, provide a link
        // to it. Otherwise, render a generic message.
        $new_docs = id(new PhrictionDocumentQuery())
          ->setViewer($viewer)
          ->withIDs(array($new_doc_id))
          ->execute();
        if ($new_docs) {
          $new_doc = head($new_docs);
          $slug_uri = PhrictionDocument::getSlugURI($new_doc->getSlug());
        }

        $notice = id(new PHUIInfoView())
          ->setSeverity(PHUIInfoView::SEVERITY_NOTICE);

        if ($slug_uri) {
          $notice->appendChild(
            phutil_tag(
              'p',
              array(),
              pht(
                'This document has been moved to %s. You can edit it to put '.
                'new content here, or use history to revert to an earlier '.
                'version.',
                phutil_tag('a', array('href' => $slug_uri), $slug_uri))));
        } else {
          $notice->appendChild(
            phutil_tag(
              'p',
              array(),
              pht(
                'This document has been moved. You can edit it to put new '.
                'content here, or use history to revert to an earlier '.
                'version.')));
        }

        $core_content = $notice->render();
      } else {
        throw new Exception(pht("Unknown document status '%s'!", $doc_status));
      }

      $move_notice = null;
      if ($current_status == PhrictionChangeType::CHANGE_MOVE_HERE) {
        $from_doc_id = $content->getChangeRef();

        $slug_uri = null;

        // If the old document exists and is visible, provide a link to it.
        $from_docs = id(new PhrictionDocumentQuery())
          ->setViewer($viewer)
          ->withIDs(array($from_doc_id))
          ->execute();
        if ($from_docs) {
          $from_doc = head($from_docs);
          $slug_uri = PhrictionDocument::getSlugURI($from_doc->getSlug());
        }

        $move_notice = id(new PHUIInfoView())
          ->setSeverity(PHUIInfoView::SEVERITY_NOTICE);

        if ($slug_uri) {
          $move_notice->appendChild(
            pht(
              'This document was moved from %s.',
              phutil_tag('a', array('href' => $slug_uri), $slug_uri)));
        } else {
          // Render this for consistency, even though it's a bit silly.
          $move_notice->appendChild(
            pht('This document was moved from elsewhere.'));
        }
      }
    }

    $children = $this->renderDocumentChildren($slug);

    $actions = $this->buildActionView($viewer, $document);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->setBorder(true);
    $crumb_views = $this->renderBreadcrumbs($slug);
    foreach ($crumb_views as $view) {
      $crumbs->addCrumb($view);
    }

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setPolicyObject($document)
      ->setHeader($page_title)
      ->setActionList($actions);

    if ($content) {
      $header->setEpoch($content->getDateCreated());
    }

    $prop_list = null;
    if ($properties) {
      $prop_list = new PHUIPropertyGroupView();
      $prop_list->addPropertyList($properties);
    }
    $prop_list = phutil_tag_div('phui-document-view-pro-box', $prop_list);

    $page_content = id(new PHUIDocumentViewPro())
      ->setHeader($header)
      ->setToc($toc)
      ->appendChild(
        array(
          $version_note,
          $move_notice,
          $core_content,
        ));

    return $this->newPage()
      ->setTitle($page_title)
      ->setCrumbs($crumbs)
      ->setPageObjectPHIDs(array($document->getPHID()))
      ->appendChild(array(
        $page_content,
        $prop_list,
        $children,
      ));

  }

  private function buildPropertyListView(
    PhrictionDocument $document,
    PhrictionContent $content,
    $slug) {

    $viewer = $this->getRequest()->getUser();
    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($document);

    $view->addProperty(
      pht('Last Author'),
      $viewer->renderHandle($content->getAuthorPHID()));

    return $view;
  }

  private function buildActionView(
    PhabricatorUser $viewer,
    PhrictionDocument $document) {
    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $document,
      PhabricatorPolicyCapability::CAN_EDIT);

    $slug = PhabricatorSlug::normalize($this->slug);

    $action_view = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($document);

    if (!$document->getID()) {
      return $action_view->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Create This Document'))
          ->setIcon('fa-plus-square')
          ->setHref('/phriction/edit/?slug='.$slug));
    }

    $action_view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Document'))
        ->setDisabled(!$can_edit)
        ->setIcon('fa-pencil')
        ->setHref('/phriction/edit/'.$document->getID().'/'));

    if ($document->getStatus() == PhrictionDocumentStatus::STATUS_EXISTS) {
      $action_view->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Move Document'))
          ->setDisabled(!$can_edit)
          ->setIcon('fa-arrows')
          ->setHref('/phriction/move/'.$document->getID().'/')
          ->setWorkflow(true));

      $action_view->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Delete Document'))
          ->setDisabled(!$can_edit)
          ->setIcon('fa-times')
          ->setHref('/phriction/delete/'.$document->getID().'/')
          ->setWorkflow(true));
    }

    return
      $action_view->addAction(
        id(new PhabricatorActionView())
        ->setName(pht('View History'))
        ->setIcon('fa-list')
        ->setHref(PhrictionDocument::getSlugURI($slug, 'history')));
  }

  private function renderDocumentChildren($slug) {

    $d_child = PhabricatorSlug::getDepth($slug) + 1;
    $d_grandchild = PhabricatorSlug::getDepth($slug) + 2;
    $limit = 250;

    $query = id(new PhrictionDocumentQuery())
      ->setViewer($this->getRequest()->getUser())
      ->withDepths(array($d_child, $d_grandchild))
      ->withSlugPrefix($slug == '/' ? '' : $slug)
      ->withStatuses(array(
        PhrictionDocumentStatus::STATUS_EXISTS,
        PhrictionDocumentStatus::STATUS_STUB,
      ))
      ->setLimit($limit)
      ->setOrder(PhrictionDocumentQuery::ORDER_HIERARCHY)
      ->needContent(true);

    $children = $query->execute();
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
        if ($child->getDepth() == $d_grandchild) {
          $more_children = false;
        }
      }
      $show_grandchildren = false;
    } else {
      $show_grandchildren = true;
      $more_children = false;
    }

    $children_dicts = array();
    $grandchildren_dicts = array();
    foreach ($children as $key => $child) {
      $child_dict = array(
        'slug' => $child->getSlug(),
        'depth' => $child->getDepth(),
        'title' => $child->getContent()->getTitle(),
      );
      if ($child->getDepth() == $d_child) {
        $children_dicts[] = $child_dict;
        continue;
      } else {
        unset($children[$key]);
        if ($show_grandchildren) {
          $ancestors = PhabricatorSlug::getAncestry($child->getSlug());
          $grandchildren_dicts[end($ancestors)][] = $child_dict;
        }
      }
    }

    // Fill in any missing children.
    $known_slugs = mpull($children, null, 'getSlug');
    foreach ($grandchildren_dicts as $slug => $ignored) {
      if (empty($known_slugs[$slug])) {
        $children_dicts[] = array(
          'slug'    => $slug,
          'depth'   => $d_child,
          'title'   => PhabricatorSlug::getDefaultTitle($slug),
          'empty'   => true,
        );
      }
    }

    $children_dicts = isort($children_dicts, 'title');

    $list = array();
    foreach ($children_dicts as $child) {
      $list[] = hsprintf('<li class="remarkup-list-item">');
      $list[] = $this->renderChildDocumentLink($child);
      $grand = idx($grandchildren_dicts, $child['slug'], array());
      if ($grand) {
        $list[] = hsprintf('<ul class="remarkup-list">');
        foreach ($grand as $grandchild) {
          $list[] = hsprintf('<li class="remarkup-list-item">');
          $list[] = $this->renderChildDocumentLink($grandchild);
          $list[] = hsprintf('</li>');
        }
        $list[] = hsprintf('</ul>');
      }
      $list[] = hsprintf('</li>');
    }
    if ($more_children) {
      $list[] = phutil_tag(
        'li',
        array(
          'class' => 'remarkup-list-item',
        ),
        pht('More...'));
    }

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Document Hierarchy'));

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->appendChild(phutil_tag(
        'div',
        array(
          'class' => 'phabricator-remarkup mlt mlb',
        ),
        phutil_tag(
          'ul',
          array(
            'class' => 'remarkup-list',
          ),
          $list)));

     return phutil_tag_div('phui-document-view-pro-box', $box);
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

  protected function getToc(PhrictionContent $content) {
    $toc = $content->getRenderedTableOfContents();
    if ($toc) {
      $toc = phutil_tag_div('phui-document-toc-content', array(
        phutil_tag_div(
          'phui-document-toc-header',
          pht('Contents')),
        $toc,
      ));
    }
    return $toc;
  }

}
