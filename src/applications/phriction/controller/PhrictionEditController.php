<?php

final class PhrictionEditController
  extends PhrictionController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $current_version = null;
    if ($this->id) {
      $document = id(new PhrictionDocumentQuery())
        ->setViewer($user)
        ->withIDs(array($this->id))
        ->needContent(true)
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_VIEW,
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$document) {
        return new Aphront404Response();
      }

      $current_version = $document->getContent()->getVersion();

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
        $content = $document->getContent();
      }

    } else {
      $slug = $request->getStr('slug');
      $slug = PhabricatorSlug::normalize($slug);
      if (!$slug) {
        return new Aphront404Response();
      }

      $document = id(new PhrictionDocumentQuery())
        ->setViewer($user)
        ->withSlugs(array($slug))
        ->needContent(true)
        ->executeOne();

      if ($document) {
        $content = $document->getContent();
        $current_version = $content->getVersion();
      } else {
        if (PhrictionDocument::isProjectSlug($slug)) {
          $project = id(new PhabricatorProjectQuery())
            ->setViewer($user)
            ->withPhrictionSlugs(array(
              PhrictionDocument::getProjectSlugIdentifier($slug)))
            ->executeOne();
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

      $overwrite = $request->getBool('overwrite');
      if (!$overwrite) {
        $edit_version = $request->getStr('contentVersion');
        if ($edit_version != $current_version) {
          $dialog = $this->newDialog()
            ->setTitle(pht('Edit Conflict!'))
            ->appendParagraph(
              pht(
                'Another user made changes to this document after you began '.
                'editing it. Do you want to overwrite their changes?'))
            ->appendParagraph(
              pht(
                'If you choose to overwrite their changes, you should review '.
                'the document edit history to see what you overwrote, and '.
                'then make another edit to merge the changes if necessary.'))
            ->addSubmitButton(pht('Overwrite Changes'))
            ->addCancelButton($request->getRequestURI());

          $dialog->addHiddenInput('overwrite', 'true');
          foreach ($request->getPassthroughRequestData() as $key => $value) {
            $dialog->addHiddenInput($key, $value);
          }

          return $dialog;
        }
      }


      $title = $request->getStr('title');
      $notes = $request->getStr('description');

      if (!strlen($title)) {
        $e_title = pht('Required');
        $errors[] = pht('Document title is required.');
      } else {
        $e_title = null;
      }

      if ($document->getID()) {
        if ($content->getTitle() == $title &&
            $content->getContent() == $request->getStr('content')) {

          $dialog = new AphrontDialogView();
          $dialog->setUser($user);
          $dialog->setTitle(pht('No Edits'));
          $dialog->appendChild(phutil_tag('p', array(), pht(
            'You did not make any changes to the document.')));
          $dialog->addCancelButton($request->getRequestURI());

          return id(new AphrontDialogResponse())->setDialog($dialog);
        }
      } else if (!strlen($request->getStr('content'))) {

        // We trigger this only for new pages. For existing pages, deleting
        // all the content counts as deleting the page.

        $dialog = new AphrontDialogView();
        $dialog->setUser($user);
        $dialog->setTitle(pht('Empty Page'));
        $dialog->appendChild(phutil_tag('p', array(), pht(
          'You can not create an empty document.')));
        $dialog->addCancelButton($request->getRequestURI());

        return id(new AphrontDialogResponse())->setDialog($dialog);
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

    if ($document->getID()) {
      $panel_header = pht('Edit Phriction Document');
      $submit_button = pht('Save Changes');
    } else {
      $panel_header = pht('Create New Phriction Document');
      $submit_button = pht('Create Document');
    }

    $uri = $document->getSlug();
    $uri = PhrictionDocument::getSlugURI($uri);
    $uri = PhabricatorEnv::getProductionURI($uri);

    $cancel_uri = PhrictionDocument::getSlugURI($document->getSlug());

    if ($draft &&
        strlen($draft->getDraft()) &&
        ($draft->getDraft() != $content->getContent())) {
      $content_text = $draft->getDraft();

      $discard = phutil_tag(
        'a',
        array(
          'href' => $request->getRequestURI()->alter('nodraft', true),
        ),
        pht('discard this draft'));

      $draft_note = new AphrontErrorView();
      $draft_note->setSeverity(AphrontErrorView::SEVERITY_NOTICE);
      $draft_note->setTitle('Recovered Draft');
      $draft_note->appendChild(hsprintf(
        '<p>Showing a saved draft of your edits, you can %s.</p>',
        $discard));
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
      ->addHiddenInput('contentVersion', $current_version)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Title'))
          ->setValue($content->getTitle())
          ->setError($e_title)
          ->setName('title'))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel(pht('URI'))
          ->setValue($uri))
      ->appendChild(
        id(new PhabricatorRemarkupControl())
          ->setLabel(pht('Content'))
          ->setValue($content_text)
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_TALL)
          ->setName('content')
          ->setID('document-textarea')
          ->setUser($user))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Edit Notes'))
          ->setValue($notes)
          ->setError(null)
          ->setName('description'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($cancel_uri)
          ->setValue($submit_button));

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Edit Document'))
      ->setFormErrors($errors)
      ->setForm($form);

    $preview = id(new PHUIRemarkupPreviewPanel())
      ->setHeader(pht('Document Preview'))
      ->setPreviewURI('/phriction/preview/')
      ->setControlID('document-textarea')
      ->setSkin('document');

    $crumbs = $this->buildApplicationCrumbs();
    if ($document->getID()) {
      $crumbs->addTextCrumb(
        $content->getTitle(),
        PhrictionDocument::getSlugURI($document->getSlug()));
      $crumbs->addTextCrumb(pht('Edit'));
    } else {
      $crumbs->addTextCrumb(pht('Create'));
    }

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $draft_note,
        $form_box,
        $preview,
      ),
      array(
        'title'   => pht('Edit Document'),
      ));
  }

}
