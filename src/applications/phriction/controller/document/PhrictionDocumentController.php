<?php

/*
 * Copyright 2011 Facebook, Inc.
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

class PhrictionDocumentController
  extends PhrictionController {

  private $slug;

  public function willProcessRequest(array $data) {
    $this->slug = $data['slug'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $slug = PhrictionDocument::normalizeSlug($this->slug);
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
      $create_uri = '/phriction/edit/?slug='.$slug;

      $page_content =
        '<div class="phriction-content">'.
          '<em>No content here!</em><br />'.
          'No document found at <tt>'.phutil_escape_html($slug).'</tt>. '.
          'You can <strong>'.
          phutil_render_tag(
            'a',
            array(
              'href' => $create_uri,
            ),
            'create a new document').'</strong>.'.
        '</div>';
      $page_title = 'Page Not Found';
      $button = phutil_render_tag(
        'a',
        array(
          'href' => $create_uri,
          'class' => 'green button',
        ),
        'Create Page');
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

      $phids = array($content->getAuthorPHID());
      $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();

      $age = time() - $content->getDateCreated();
      $age = floor($age / (60 * 60 * 24));

      if ($age < 1) {
        $when = 'today';
      } else if ($age == 1) {
        $when = 'yesterday';
      } else {
        $when = "{$age} days ago";
      }

      $byline =
        '<div class="phriction-byline">'.
          "Last updated {$when} by ".
          $handles[$content->getAuthorPHID()]->renderLink().'.'.
        '</div>';

      $engine = PhabricatorMarkupEngine::newPhrictionMarkupEngine();

      $page_content =
        '<div class="phriction-content">'.
          $byline.
          '<div class="phabricator-remarkup">'.
            $engine->markupText($content->getContent()).
          '</div>'.
        '</div>';

      $button = phutil_render_tag(
        'a',
        array(
          'href' => '/phriction/edit/'.$document->getID().'/',
          'class' => 'button',
        ),
        'Edit Page');
    }

    if ($version_note) {
      $version_note = $version_note->render();
    }

    $page =
      '<div class="phriction-header">'.
        $button.
        '<h1>'.phutil_escape_html($page_title).'</h1>'.
        $breadcrumbs.
      '</div>'.
      $version_note.
      $page_content;

    return $this->buildStandardPageResponse(
      $page,
      array(
        'title'   => 'Phriction - '.$page_title,
        'history' => PhrictionDocument::getSlugURI($slug, 'history'),
      ));

  }

  private function renderBreadcrumbs($slug) {

    $ancestor_handles = array();
    $ancestral_slugs = PhrictionDocument::getSlugAncestry($slug);
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
        $handles = id(new PhabricatorObjectHandleData($ancestor_phids))
          ->loadHandles();
      }

      $ancestor_handles = array();
      foreach ($ancestral_slugs as $slug) {
        if (isset($ancestors[$slug])) {
          $ancestor_handles[] = $handles[$ancestors[$slug]->getPHID()];
        } else {
          $handle = new PhabricatorObjectHandle();
          $handle->setName(PhrictionDocument::getDefaultSlugTitle($slug));
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

}
