<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

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

    $breadcrumbs = $this->renderBreadcrumbs($slug);
    $version_note = null;

    if (!$document) {

      if (PhrictionDocument::isProjectSlug($slug)) {
        $project = id(new PhabricatorProject())->loadOneWhere(
          'phrictionSlug = %s',
          PhrictionDocument::getProjectSlugIdentifier($slug));
        if (!$project) {
          return new Aphront404Response();
        }
      }
      $create_uri = '/phriction/edit/?slug='.$slug;
      $create_sentence =
        'You can <strong>'.
        phutil_render_tag(
          'a',
          array(
            'href' => $create_uri,
          ),
          'create a new document').
          '</strong>.';
      $button = phutil_render_tag(
        'a',
        array(
          'href' => $create_uri,
          'class' => 'green button',
        ),
        'Create Page');

      $page_content =
        '<div class="phriction-content">'.
          '<em>No content here!</em><br />'.
          'No document found at <tt>'.phutil_escape_html($slug).'</tt>. '.
          $create_sentence.
        '</div>';
      $page_title = 'Page Not Found';
      $buttons = $button;
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
          $version_note = new AphrontErrorView();
          $version_note->setSeverity(AphrontErrorView::SEVERITY_NOTICE);
          $version_note->setTitle('Older Version');
          $version_note->appendChild(
            'You are viewing an older version of this document, as it '.
            'appeared on '.
            phabricator_datetime($content->getDateCreated(), $user).'.');
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
        $project_info =
          '<br />This document is about the project '.
          $handles[$project_phid]->renderLink().'.';
      }



      $byline =
        '<div class="phriction-byline">'.
          "Last updated {$when} by ".
          $handles[$content->getAuthorPHID()]->renderLink().'.'.
          $project_info.
        '</div>';


      $doc_status = $document->getStatus();
      if ($doc_status == PhrictionDocumentStatus::STATUS_EXISTS) {
        $core_content = $content->renderContent($user);
      } else if ($doc_status == PhrictionDocumentStatus::STATUS_DELETED) {
        $notice = new AphrontErrorView();
        $notice->setSeverity(AphrontErrorView::SEVERITY_NOTICE);
        $notice->setTitle('Document Deleted');
        $notice->appendChild(
          'This document has been deleted. You can edit it to put new content '.
          'here, or use history to revert to an earlier version.');
        $core_content = $notice->render();
      } else {
        throw new Exception("Unknown document status '{$doc_status}'!");
      }

      $page_content =
        '<div class="phriction-content">'.
          $byline.
          $core_content.
        '</div>';

      $edit_button = phutil_render_tag(
        'a',
        array(
          'href' => '/phriction/edit/'.$document->getID().'/',
          'class' => 'button',
        ),
        'Edit Document');
      $history_button = phutil_render_tag(
        'a',
        array(
          'href' => PhrictionDocument::getSlugURI($slug, 'history'),
          'class' => 'button grey',
        ),
        'View History');
      // these float right so history_button which is right most goes first
      $buttons = $history_button.$edit_button;
    }

    if ($version_note) {
      $version_note = $version_note->render();
    }

    $children = $this->renderChildren($slug);

    $page =
      '<div class="phriction-header">'.
        $buttons.
        '<h1>'.phutil_escape_html($page_title).'</h1>'.
        $breadcrumbs.
      '</div>'.
      $version_note.
      $page_content.
      $children;

    return $this->buildStandardPageResponse(
      $page,
      array(
        'title'   => 'Phriction - '.$page_title,
      ));

  }

  private function renderBreadcrumbs($slug) {

    $ancestor_handles = array();
    $ancestral_slugs = PhabricatorSlug::getAncestry($slug);
    $ancestral_slugs[] = $slug;
    if ($ancestral_slugs) {
      $empty_slugs = array_fill_keys($ancestral_slugs, null);
      $ancestors = id(new PhrictionDocument())->loadAllWhere(
        'slug IN (%Ls)',
        $ancestral_slugs);
      $ancestors = mpull($ancestors, null, 'getSlug');

      $ancestor_phids = mpull($ancestors, 'getPHID');
      $handles = array();
      if ($ancestor_phids) {
        $handles = $this->loadViewerHandles($ancestor_phids);
      }

      $ancestor_handles = array();
      foreach ($ancestral_slugs as $slug) {
        if (isset($ancestors[$slug])) {
          $ancestor_handles[] = $handles[$ancestors[$slug]->getPHID()];
        } else {
          $handle = new PhabricatorObjectHandle();
          $handle->setName(PhabricatorSlug::getDefaultTitle($slug));
          $handle->setURI(PhrictionDocument::getSlugURI($slug));
          $ancestor_handles[] = $handle;
        }
      }
    }

    $breadcrumbs = array();
    foreach ($ancestor_handles as $ancestor_handle) {
      $breadcrumbs[] = $ancestor_handle->renderLink();
    }

    $list = phutil_render_tag(
      'a',
      array(
        'href' => '/phriction/',
      ),
      'Document Index');

    return
      '<div class="phriction-breadcrumbs">'.
        $list.' &middot; '.
        '<span class="phriction-document-crumbs">'.
          implode(" \xC2\xBB ", $breadcrumbs).
        '</span>'.
      '</div>';
  }

  private function renderChildren($slug) {
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
    $list[] = '<ul>';
    foreach ($children as $child) {
      $list[] = $this->renderChildDocumentLink($child);
      $grand = idx($grandchildren, $child['slug'], array());
      if ($grand) {
        $list[] = '<ul>';
        foreach ($grand as $grandchild) {
          $list[] = $this->renderChildDocumentLink($grandchild);
        }
        $list[] = '</ul>';
      }
    }
    if ($more_children) {
      $list[] = '<li>More...</li>';
    }
    $list[] = '</ul>';
    $list = implode("\n", $list);

    return
      '<div class="phriction-children">'.
        '<div class="phriction-children-header">Document Hierarchy</div>'.
        $list.
      '</div>';
  }

  private function renderChildDocumentLink(array $info) {
    $title = nonempty($info['title'], '(Untitled Document)');
    $item = phutil_render_tag(
      'a',
      array(
        'href' => PhrictionDocument::getSlugURI($info['slug']),
      ),
      phutil_escape_html($title));

    if (isset($info['empty'])) {
      $item = '<em>'.$item.'</em>';
    }

    return '<li>'.$item.'</li>';
  }

}
