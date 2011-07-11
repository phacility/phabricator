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

    if (!$document) {
      $page_content = '<em>No content here!</em>';
      $page_title = 'Page Not Found';
      $button = phutil_render_tag(
        'a',
        array(
          'href' => '/phriction/edit/?slug='.$slug,
          'class' => 'green button',
        ),
        'Create Page');
    } else {
      $content = id(new PhrictionContent())->load($document->getContentID());
      $page_content = phutil_escape_html($content->getContent());
      $page_title = $content->getTitle();
      $button = phutil_render_tag(
        'a',
        array(
          'href' => '/phriction/edit/'.$document->getID().'/',
          'class' => 'button',
        ),
        'Edit Page');
    }

    $page =
      '<div class="phriction-header">'.
        $button.
        '<h1>'.phutil_escape_html($page_title).'</h1>'.
      '</div>'.
      $page_content;

    return $this->buildStandardPageResponse(
      $page,
      array(
        'title' => 'Phriction - '.$page_title,
      ));

  }

}
