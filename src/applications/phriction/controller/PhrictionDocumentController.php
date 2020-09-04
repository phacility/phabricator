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

    $version_note = null;
    $core_content = '';
    $move_notice = '';
    $properties = null;
    $content = null;
    $toc = null;

    $is_draft = false;

    $document = id(new PhrictionDocumentQuery())
      ->setViewer($viewer)
      ->withSlugs(array($slug))
      ->needContent(true)
      ->executeOne();
    if (!$document) {
      $document = PhrictionDocument::initializeNewDocument($viewer, $slug);
      if ($slug == '/') {
        $title = pht('Welcome to Phriction');
        $subtitle = pht('Phriction is a simple and easy to use wiki for '.
          'keeping track of documents and their changes.');
        $page_title = pht('Welcome');
        $create_text = pht('Edit this Document');
        $this->setShowingWelcomeDocument(true);


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
      $max_version = (int)$document->getMaxVersion();

      $version = $request->getInt('v');
      if ($version) {
        $content = id(new PhrictionContentQuery())
          ->setViewer($viewer)
          ->withDocumentPHIDs(array($document->getPHID()))
          ->withVersions(array($version))
          ->executeOne();
        if (!$content) {
          return new Aphront404Response();
        }

        // When the "v" parameter exists, the user is in history mode so we
        // show this header even if they're looking at the current version
        // of the document. This keeps the next/previous links working.

        $view_version = (int)$content->getVersion();
        $published_version = (int)$document->getContent()->getVersion();

        if ($view_version < $published_version) {
          $version_note = pht(
            'You are viewing an older version of this document, as it '.
            'appeared on %s.',
            phabricator_datetime($content->getDateCreated(), $viewer));
        } else if ($view_version > $published_version) {
          $is_draft = true;
          $version_note = pht(
            'You are viewing an unpublished draft of this document.');
        } else {
          $version_note = pht(
            'You are viewing the current published version of this document.');
        }

        $version_note = array(
          phutil_tag(
            'strong',
            array(),
            pht('Version %d of %d: ', $view_version, $max_version)),
          ' ',
          $version_note,
        );

        $version_note = id(new PHUIInfoView())
          ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
          ->appendChild($version_note);

        $document_uri = new PhutilURI($document->getURI());

        if ($view_version > 1) {
          $previous_uri = $document_uri->alter('v', ($view_version - 1));
        } else {
          $previous_uri = null;
        }

        if ($view_version !== $published_version) {
          $current_uri = $document_uri->alter('v', $published_version);
        } else {
          $current_uri = null;
        }

        if ($view_version < $max_version) {
          $next_uri = $document_uri->alter('v', ($view_version + 1));
        } else {
          $next_uri = null;
        }

        if ($view_version !== $max_version) {
          $draft_uri = $document_uri->alter('v', $max_version);
        } else {
          $draft_uri = null;
        }

        $button_bar = id(new PHUIButtonBarView())
          ->addButton(
            id(new PHUIButtonView())
              ->setTag('a')
              ->setColor('grey')
              ->setIcon('fa-backward')
              ->setDisabled(!$previous_uri)
              ->setHref($previous_uri)
              ->setText(pht('Previous')))
          ->addButton(
            id(new PHUIButtonView())
              ->setTag('a')
              ->setColor('grey')
              ->setIcon('fa-file-o')
              ->setDisabled(!$current_uri)
              ->setHref($current_uri)
              ->setText(pht('Published')))
          ->addButton(
            id(new PHUIButtonView())
              ->setTag('a')
              ->setColor('grey')
              ->setIcon('fa-forward', false)
              ->setDisabled(!$next_uri)
              ->setHref($next_uri)
              ->setText(pht('Next')))
          ->addButton(
            id(new PHUIButtonView())
              ->setTag('a')
              ->setColor('grey')
              ->setIcon('fa-fast-forward', false)
              ->setDisabled(!$draft_uri)
              ->setHref($draft_uri)
              ->setText(pht('Draft')));

        require_celerity_resource('phui-document-view-css');

        $version_note = array(
          $version_note,
          phutil_tag(
            'div',
            array(
              'class' => 'phui-document-version-navigation',
            ),
            $button_bar),
        );
      } else {
        $content = $document->getContent();

        if ($content->getVersion() < $document->getMaxVersion()) {
          $can_edit = PhabricatorPolicyFilter::hasCapability(
            $viewer,
            $document,
            PhabricatorPolicyCapability::CAN_EDIT);
          if ($can_edit) {
            $document_uri = new PhutilURI($document->getURI());
            $draft_uri = $document_uri->alter('v', $document->getMaxVersion());

            $draft_link = phutil_tag(
              'a',
              array(
                'href' => $draft_uri,
              ),
              pht('View Draft Version'));

            $draft_link = phutil_tag('strong', array(), $draft_link);

            $version_note = id(new PHUIInfoView())
              ->setSeverity(PHUIInfoView::SEVERITY_NOTICE)
              ->appendChild(
                array(
                  pht('This document has unpublished draft changes.'),
                  ' ',
                  $draft_link,
                ));
          }
        }
      }

      $page_title = $content->getTitle();
      $properties = $this
        ->buildPropertyListView($document, $content, $slug);

      $doc_status = $document->getStatus();
      $current_status = $content->getChangeType();
      if ($current_status == PhrictionChangeType::CHANGE_EDIT ||
        $current_status == PhrictionChangeType::CHANGE_MOVE_HERE) {

        $remarkup_view = $content->newRemarkupView($viewer);

        $core_content = $remarkup_view->render();

        $toc = $remarkup_view->getTableOfContents();
        $toc = $this->getToc($toc);

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
    }

    $children = $this->renderDocumentChildren($slug);

    $curtain = null;
    if ($document->getID()) {
      $curtain = $this->buildCurtain($document, $content);
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->setBorder(true);
    $crumb_views = $this->renderBreadcrumbs($slug);
    foreach ($crumb_views as $view) {
      $crumbs->addCrumb($view);
    }

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setPolicyObject($document)
      ->setHeader($page_title);

    if ($is_draft) {
      $draft_tag = id(new PHUITagView())
        ->setName(pht('Draft'))
        ->setIcon('fa-spinner')
        ->setColor('pink')
        ->setType(PHUITagView::TYPE_SHADE);

      $header->addTag($draft_tag);
    } else if ($content) {
      $header->setEpoch($content->getDateCreated());
    }

    $prop_list = null;
    if ($properties) {
      $prop_list = new PHUIPropertyGroupView();
      $prop_list->addPropertyList($properties);
    }
    $prop_list = phutil_tag_div('phui-document-view-pro-box', $prop_list);

    $page_content = id(new PHUIDocumentView())
      ->setBanner($version_note)
      ->setHeader($header)
      ->setToc($toc)
      ->appendChild(
        array(
          $move_notice,
          $core_content,
        ));

    if ($curtain) {
      $page_content->setCurtain($curtain);
    }

    if ($document->getPHID()) {
      $timeline = $this->buildTransactionTimeline(
        $document,
        new PhrictionTransactionQuery());

      $edit_engine = id(new PhrictionDocumentEditEngine())
        ->setViewer($viewer)
        ->setTargetObject($document);

      $comment_view = $edit_engine
        ->buildEditEngineCommentView($document);
    } else {
      $timeline = null;
      $comment_view = null;
    }

    return $this->newPage()
      ->setTitle($page_title)
      ->setCrumbs($crumbs)
      ->setPageObjectPHIDs(array($document->getPHID()))
      ->appendChild(
        array(
          $page_content,
          $prop_list,
          phutil_tag(
            'div',
            array(
              'class' => 'phui-document-view-pro-box',
            ),
            array(
              $children,
              $timeline,
              $comment_view,
            )),
        ));

  }

  private function buildPropertyListView(
    PhrictionDocument $document,
    PhrictionContent $content,
    $slug) {

    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $view->addProperty(
      pht('Last Author'),
      $viewer->renderHandle($content->getAuthorPHID()));

    $view->addProperty(
      pht('Last Edited'),
      phabricator_dual_datetime($content->getDateCreated(), $viewer));

    return $view;
  }

  private function buildCurtain(
    PhrictionDocument $document,
    PhrictionContent $content) {
    $viewer = $this->getViewer();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $document,
      PhabricatorPolicyCapability::CAN_EDIT);

    $slug = PhabricatorSlug::normalize($this->slug);
    $id = $document->getID();

    $curtain = $this->newCurtainView($document);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Document'))
        ->setDisabled(!$can_edit)
        ->setIcon('fa-pencil')
        ->setHref('/phriction/edit/'.$document->getID().'/'));

    $curtain->addAction(
      id(new PhabricatorActionView())
      ->setName(pht('View History'))
      ->setIcon('fa-history')
      ->setHref(PhrictionDocument::getSlugURI($slug, 'history')));

    $is_current = false;
    $content_id = null;
    $is_draft = false;
    if ($content) {
      if ($content->getPHID() == $document->getContentPHID()) {
        $is_current = true;
      }
      $content_id = $content->getID();

      $current_version = $document->getContent()->getVersion();
      $is_draft = ($content->getVersion() >= $current_version);
    }
    $can_publish = ($can_edit && $content && !$is_current);

    if ($is_draft) {
      $publish_name = pht('Publish Draft');
    } else {
      $publish_name = pht('Publish Older Version');
    }

    // If you're looking at the current version; and it's an unpublished
    // draft; and you can publish it, add a UI hint that this might be an
    // interesting action to take.
    $hint_publish = false;
    if ($is_draft) {
      if ($can_publish) {
        if ($document->getMaxVersion() == $content->getVersion()) {
          $hint_publish = true;
        }
      }
    }

    $publish_uri = "/phriction/publish/{$id}/{$content_id}/";

    $curtain->addAction(
      id(new PhabricatorActionView())
      ->setName($publish_name)
      ->setIcon('fa-upload')
      ->setSelected($hint_publish)
      ->setDisabled(!$can_publish)
      ->setWorkflow(true)
      ->setHref($publish_uri));

    if ($document->getStatus() == PhrictionDocumentStatus::STATUS_EXISTS) {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Move Document'))
          ->setDisabled(!$can_edit)
          ->setIcon('fa-arrows')
          ->setHref('/phriction/move/'.$document->getID().'/')
          ->setWorkflow(true));

      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Delete Document'))
          ->setDisabled(!$can_edit)
          ->setIcon('fa-times')
          ->setHref('/phriction/delete/'.$document->getID().'/')
          ->setWorkflow(true));
    }

    $print_uri = PhrictionDocument::getSlugURI($slug).'?__print__=1';

    $curtain->addAction(
      id(new PhabricatorActionView())
      ->setName(pht('Printable Page'))
      ->setIcon('fa-print')
      ->setOpenInNewWindow(true)
      ->setHref($print_uri));

    return $curtain;
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

    return $box;
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

  protected function getToc($toc) {

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
