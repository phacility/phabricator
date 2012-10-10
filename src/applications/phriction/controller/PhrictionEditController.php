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
final class PhrictionEditController
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
      $slug = PhabricatorSlug::normalize($slug);
      if (!$slug) {
        return new Aphront404Response();
      }

      $document = id(new PhrictionDocument())->loadOneWhere(
        'slug = %s',
        $slug);

      if ($document) {
        $content = id(new PhrictionContent())->load($document->getContentID());
      } else {
        if (PhrictionDocument::isProjectSlug($slug)) {
          $project = id(new PhabricatorProject())->loadOneWhere(
            'phrictionSlug = %s',
            PhrictionDocument::getProjectSlugIdentifier($slug));
          if (!$project) {
            return new Aphront404Response();
          }
        }
        $document = new PhrictionDocument();
        $document->setSlug($slug);

        $content  = new PhrictionContent();
        $content->setSlug($slug);

        $default_title = PhabricatorSlug::getDefaultTitle($slug);
        $content->setTitle($default_title);
      }
    }

    if ($request->getBool('nodraft')) {
      $draft = null;
      $draft_key = null;
    } else {
      if ($document->getPHID()) {
        $draft_key = $document->getPHID().':'.$content->getVersion();
      } else {
        $draft_key = 'phriction:'.$content->getSlug();
      }
      $draft = id(new PhabricatorDraft())->loadOneWhere(
        'authorPHID = %s AND draftKey = %s',
        $user->getPHID(),
        $draft_key);
    }

    require_celerity_resource('phriction-document-css');

    $e_title = true;
    $notes = null;
    $errors = array();

    if ($request->isFormPost()) {
      $title = $request->getStr('title');
      $notes = $request->getStr('description');

      if (!strlen($title)) {
        $e_title = 'Required';
        $errors[] = 'Document title is required.';
      } else {
        $e_title = null;
      }

      if ($document->getID()) {
        if ($content->getTitle() == $title &&
            $content->getContent() == $request->getStr('content')) {

          $dialog = new AphrontDialogView();
          $dialog->setUser($user);
          $dialog->setTitle('No Edits');
          $dialog->appendChild(
            '<p>You did not make any changes to the document.</p>');
          $dialog->addCancelButton($request->getRequestURI());

          return id(new AphrontDialogResponse())->setDialog($dialog);
        }
      }

      if (!count($errors)) {
        $editor = id(PhrictionDocumentEditor::newForSlug($document->getSlug()))
          ->setActor($user)
          ->setTitle($title)
          ->setContent($request->getStr('content'))
          ->setDescription($notes);

        $editor->save();

        if ($draft) {
          $draft->delete();
        }

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
      $delete_button = phutil_render_tag(
        'a',
        array(
          'href' => '/phriction/delete/'.$document->getID().'/',
          'class' => 'grey button',
        ),
        'Delete Document');
    } else {
      $panel_header = 'Create New Phriction Document';
      $submit_button = 'Create Document';
      $delete_button = null;
    }

    $uri = $document->getSlug();
    $uri = PhrictionDocument::getSlugURI($uri);
    $uri = PhabricatorEnv::getProductionURI($uri);

    $cancel_uri = PhrictionDocument::getSlugURI($document->getSlug());

    if ($draft &&
        strlen($draft->getDraft()) &&
        ($draft->getDraft() != $content->getContent())) {
      $content_text = $draft->getDraft();

      $discard = phutil_render_tag(
        'a',
        array(
          'href' => $request->getRequestURI()->alter('nodraft', true),
        ),
        'discard this draft');

      $draft_note = new AphrontErrorView();
      $draft_note->setSeverity(AphrontErrorView::SEVERITY_NOTICE);
      $draft_note->setTitle('Recovered Draft');
      $draft_note->appendChild(
        '<p>Showing a saved draft of your edits, you can '.$discard.'.</p>');
    } else {
      $content_text = $content->getContent();
      $draft_note = null;
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->setWorkflow(true)
      ->setAction($request->getRequestURI()->getPath())
      ->addHiddenInput('slug', $document->getSlug())
      ->addHiddenInput('nodraft', $request->getBool('nodraft'))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Title')
          ->setValue($content->getTitle())
          ->setError($e_title)
          ->setName('title'))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('URI')
          ->setValue($uri))
      ->appendChild(
        id(new PhabricatorRemarkupControl())
          ->setLabel('Content')
          ->setValue($content_text)
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_TALL)
          ->setName('content')
          ->setID('document-textarea'))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Edit Notes')
          ->setValue($notes)
          ->setError(null)
          ->setName('description'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($cancel_uri)
          ->setValue($submit_button));

    $panel = id(new AphrontPanelView())
      ->setWidth(AphrontPanelView::WIDTH_WIDE)
      ->setHeader($panel_header)
      ->appendChild($form);

    if ($delete_button) {
      $panel->addButton($delete_button);
    }

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
        'uri'       => '/phriction/preview/?draftkey='.$draft_key,
      ));

    return $this->buildStandardPageResponse(
      array(
        $draft_note,
        $error_view,
        $panel,
        $preview_panel,
      ),
      array(
        'title' => 'Edit Document',
      ));
  }

}
