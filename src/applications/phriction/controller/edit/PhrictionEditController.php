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

class PhrictionEditController
  extends PhrictionController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    if ($this->id) {
      $document = id(new PhrictionDocument())->load($this->id);
      if (!$document) {
        return new Aphront404Response();
      }

      $revert = $request->getInt('revert');
      if ($revert) {
        $content = id(new PhrictionContent())->loadOneWhere(
          'documentID = %d AND version = %d',
          $document->getID(),
          $revert);
        if (!$content) {
          return new Aphront404Response();
        }
      } else {
        $content = id(new PhrictionContent())->load($document->getContentID());
      }

    } else {
      $slug = $request->getStr('slug');
      $slug = PhrictionDocument::normalizeSlug($slug);
      if (!$slug) {
        return new Aphront404Response();
      }

      $document = id(new PhrictionDocument())->loadOneWhere(
        'slug = %s',
        $slug);

      if ($document) {
        $content = id(new PhrictionContent())->load($document->getContentID());
      } else {
        $document = new PhrictionDocument();
        $document->setSlug($slug);

        $content  = new PhrictionContent();
        $content->setSlug($slug);

        $default_title = PhrictionDocument::getDefaultSlugTitle($slug);
        $content->setTitle($default_title);
      }
    }

    require_celerity_resource('phriction-document-css');

    $e_title = true;
    $errors = array();

    if ($request->isFormPost()) {
      $title = $request->getStr('title');

      if (!strlen($title)) {
        $e_title = 'Required';
        $errors[] = 'Document title is required.';
      } else {
        $e_title = null;
      }

      if (!count($errors)) {
        $editor = id(PhrictionDocumentEditor::newForSlug($document->getSlug()))
          ->setUser($user)
          ->setTitle($title)
          ->setContent($request->getStr('content'))
          ->setDescription($request->getStr('description'));

        $editor->save();

        $uri = PhrictionDocument::getSlugURI($document->getSlug());
        return id(new AphrontRedirectResponse())->setURI($uri);
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle('Form Errors')
        ->setErrors($errors);
    }

    if ($document->getID()) {
      $panel_header = 'Edit Phriction Document';
      $submit_button = 'Save Changes';
    } else {
      $panel_header = 'Create New Phriction Document';
      $submit_button = 'Create Document';
    }

    $uri = $document->getSlug();
    $uri = PhrictionDocument::getSlugURI($uri);
    $uri = PhabricatorEnv::getProductionURI($uri);

    $remarkup_reference = phutil_render_tag(
      'a',
      array(
        'href' => PhabricatorEnv::getDoclink('article/Remarkup_Reference.html'),
      ),
      'Formatting Reference');

    $cancel_uri = PhrictionDocument::getSlugURI($document->getSlug());

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->setAction($request->getRequestURI()->getPath())
      ->addHiddenInput('slug', $document->getSlug())
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Title')
          ->setValue($content->getTitle())
          ->setError($e_title)
          ->setName('title'))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Description')
          ->setValue($content->getDescription())
          ->setError(null)
          ->setName('description'))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('URI')
          ->setValue($uri))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Content')
          ->setValue($content->getContent())
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_TALL)
          ->setName('content')
          ->setID('document-textarea')
          ->setEnableDragAndDropFileUploads(true)
          ->setCaption($remarkup_reference))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($cancel_uri)
          ->setValue($submit_button));

    $panel = id(new AphrontPanelView())
      ->setWidth(AphrontPanelView::WIDTH_WIDE)
      ->setHeader($panel_header)
      ->appendChild($form);

    $preview_panel =
      '<div class="aphront-panel-preview aphront-panel-preview-wide">
        <div class="phriction-document-preview-header">
          Document Preview
        </div>
        <div id="document-preview">
          <div class="aphront-panel-preview-loading-text">
            Loading preview...
          </div>
        </div>
      </div>';

    Javelin::initBehavior(
      'phriction-document-preview',
      array(
        'preview'   => 'document-preview',
        'textarea'  => 'document-textarea',
        'uri'       => '/phriction/preview/',
      ));

    return $this->buildStandardPageResponse(
      array(
        $error_view,
        $panel,
        $preview_panel,
      ),
      array(
        'title' => 'Edit Document',
      ));
  }

}
