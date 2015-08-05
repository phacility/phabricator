<?php

final class PhabricatorMacroEditController extends PhabricatorMacroController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $this->requireApplicationCapability(
      PhabricatorMacroManageCapability::CAPABILITY);

    if ($id) {
      $macro = id(new PhabricatorMacroQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->needFiles(true)
        ->executeOne();
      if (!$macro) {
        return new Aphront404Response();
      }
    } else {
      $macro = new PhabricatorFileImageMacro();
      $macro->setAuthorPHID($viewer->getPHID());
    }

    $errors = array();
    $e_name = true;
    $e_file = null;
    $file = null;

    if ($request->isFormPost()) {
      $original = clone $macro;

      $new_name = null;
      if ($request->getBool('name_form') || !$macro->getID()) {
        $new_name = $request->getStr('name');

        $macro->setName($new_name);

        if (!strlen($macro->getName())) {
          $errors[] = pht('Macro name is required.');
          $e_name = pht('Required');
        } else if (!preg_match('/^[a-z0-9:_-]{3,}\z/', $macro->getName())) {
          $errors[] = pht(
            'Macro must be at least three characters long and contain only '.
            'lowercase letters, digits, hyphens, colons and underscores.');
          $e_name = pht('Invalid');
        } else {
          $e_name = null;
        }
      }

      $uri = $request->getStr('url');

      $engine = new PhabricatorDestructionEngine();

      $file = null;
      if ($request->getFileExists('file')) {
        $file = PhabricatorFile::newFromPHPUpload(
          $_FILES['file'],
          array(
            'name' => $request->getStr('name'),
            'authorPHID' => $viewer->getPHID(),
            'isExplicitUpload' => true,
            'canCDN' => true,
          ));
      } else if ($uri) {
        try {
          // Rate limit outbound fetches to make this mechanism less useful for
          // scanning networks and ports.
          PhabricatorSystemActionEngine::willTakeAction(
            array($viewer->getPHID()),
            new PhabricatorFilesOutboundRequestAction(),
            1);

          $file = PhabricatorFile::newFromFileDownload(
            $uri,
            array(
              'name' => $request->getStr('name'),
              'viewPolicy' => PhabricatorPolicies::POLICY_NOONE,
              'isExplicitUpload' => true,
              'canCDN' => true,
            ));

          if (!$file->isViewableInBrowser()) {
            $mime_type = $file->getMimeType();
            $engine->destroyObject($file);
            $file = null;
            throw new Exception(
              pht(
                'The URI "%s" does not correspond to a valid image file, got '.
                'a file with MIME type "%s". You must specify the URI of a '.
                'valid image file.',
                $uri,
                $mime_type));
          } else {
            $file
              ->setAuthorPHID($viewer->getPHID())
              ->save();
          }
        } catch (HTTPFutureHTTPResponseStatus $status) {
          $errors[] = pht(
            'The URI "%s" could not be loaded, got %s error.',
            $uri,
            $status->getStatusCode());
        } catch (Exception $ex) {
          $errors[] = $ex->getMessage();
        }
      } else if ($request->getStr('phid')) {
        $file = id(new PhabricatorFileQuery())
          ->setViewer($viewer)
          ->withPHIDs(array($request->getStr('phid')))
          ->executeOne();
      }

      if ($file) {
        if (!$file->isViewableInBrowser()) {
          $errors[] = pht('You must upload an image.');
          $e_file = pht('Invalid');
        } else {
          $macro->setFilePHID($file->getPHID());
          $macro->attachFile($file);
          $e_file = null;
        }
      }

      if (!$macro->getID() && !$file) {
        $errors[] = pht('You must upload an image to create a macro.');
        $e_file = pht('Required');
      }

      if (!$errors) {
        try {
          $xactions = array();

          if ($new_name !== null) {
            $xactions[] = id(new PhabricatorMacroTransaction())
              ->setTransactionType(PhabricatorMacroTransaction::TYPE_NAME)
              ->setNewValue($new_name);
          }

          if ($file) {
            $xactions[] = id(new PhabricatorMacroTransaction())
              ->setTransactionType(PhabricatorMacroTransaction::TYPE_FILE)
              ->setNewValue($file->getPHID());
          }

          $editor = id(new PhabricatorMacroEditor())
            ->setActor($viewer)
            ->setContinueOnNoEffect(true)
            ->setContentSourceFromRequest($request);

          $xactions = $editor->applyTransactions($original, $xactions);

          $view_uri = $this->getApplicationURI('/view/'.$original->getID().'/');
          return id(new AphrontRedirectResponse())->setURI($view_uri);
        } catch (AphrontDuplicateKeyQueryException $ex) {
          throw $ex;
          $errors[] = pht('Macro name is not unique!');
          $e_name = pht('Duplicate');
        }
      }
    }

    $current_file = null;
    if ($macro->getFilePHID()) {
      $current_file = $macro->getFile();
    }

    $form = new AphrontFormView();
    $form->addHiddenInput('name_form', 1);
    $form->setUser($request->getUser());

    $form
      ->setEncType('multipart/form-data')
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Name'))
          ->setName('name')
          ->setValue($macro->getName())
          ->setCaption(
            pht('This word or phrase will be replaced with the image.'))
          ->setError($e_name));

    if (!$macro->getID()) {
      if ($current_file) {
        $current_file_view = id(new PhabricatorFileLinkView())
          ->setFilePHID($current_file->getPHID())
          ->setFileName($current_file->getName())
          ->setFileViewable(true)
          ->setFileViewURI($current_file->getBestURI())
          ->render();
        $form->addHiddenInput('phid', $current_file->getPHID());
        $form->appendChild(
          id(new AphrontFormMarkupControl())
            ->setLabel(pht('Selected File'))
            ->setValue($current_file_view));

        $other_label = pht('Change File');
      } else {
        $other_label = pht('File');
      }

      $form->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('URL'))
          ->setName('url')
          ->setValue($request->getStr('url'))
          ->setError($request->getFileExists('file') ? false : $e_file));

      $form->appendChild(
        id(new AphrontFormFileControl())
          ->setLabel($other_label)
          ->setName('file')
          ->setError($request->getStr('url') ? false : $e_file));
    }


    $view_uri = $this->getApplicationURI('/view/'.$macro->getID().'/');

    if ($macro->getID()) {
      $cancel_uri = $view_uri;
    } else {
      $cancel_uri = $this->getApplicationURI();
    }

    $form
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Save Image Macro'))
          ->addCancelButton($cancel_uri));

    $crumbs = $this->buildApplicationCrumbs();

    if ($macro->getID()) {
      $title = pht('Edit Image Macro');
      $crumb = pht('Edit Macro');

      $crumbs->addTextCrumb(pht('Macro "%s"', $macro->getName()), $view_uri);
    } else {
      $title = pht('Create Image Macro');
      $crumb = pht('Create Macro');
    }

    $crumbs->addTextCrumb($crumb, $request->getRequestURI());

    $upload = null;
    if ($macro->getID()) {
      $upload_form = id(new AphrontFormView())
        ->setEncType('multipart/form-data')
        ->setUser($request->getUser());

      $upload_form->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('URL'))
          ->setName('url')
          ->setValue($request->getStr('url')));

      $upload_form
        ->appendChild(
          id(new AphrontFormFileControl())
            ->setLabel(pht('File'))
            ->setName('file'))
        ->appendChild(
          id(new AphrontFormSubmitControl())
            ->setValue(pht('Upload File')));

      $upload = id(new PHUIObjectBoxView())
        ->setHeaderText(pht('Upload New File'))
        ->setForm($upload_form);
    }

    $form_box = id(new PHUIObjectBoxView())
      ->setHeaderText($title)
      ->setFormErrors($errors)
      ->setForm($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $form_box,
        $upload,
      ),
      array(
        'title' => $title,
      ));
  }

}
