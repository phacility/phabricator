<?php

/**
 * @group conpherence
 */
final class ConpherenceUpdateController
  extends ConpherenceController {

  private $conpherenceID;

  public function setConpherenceID($conpherence_id) {
    $this->conpherenceID = $conpherence_id;
    return $this;
  }
  public function getConpherenceID() {
    return $this->conpherenceID;
  }
  public function willProcessRequest(array $data) {
    $this->setConpherenceID(idx($data, 'id'));
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();
    $conpherence_id = $this->getConpherenceID();
    if (!$conpherence_id) {
      return new Aphront404Response();
    }

    $conpherence = id(new ConpherenceThreadQuery())
      ->setViewer($user)
      ->withIDs(array($conpherence_id))
      ->needOrigPics(true)
      ->needHeaderPics(true)
      ->needAllTransactions(true)
      ->executeOne();
    $supported_formats = PhabricatorFile::getTransformableImageFormats();

    $action = $request->getStr('action', 'metadata');
    $latest_transaction_id = null;
    $response_mode = 'ajax';
    $error_view = null;
    $e_file = array();
    $errors = array();
    if ($request->isFormPost()) {
      $content_source = PhabricatorContentSource::newForSource(
        PhabricatorContentSource::SOURCE_WEB,
        array(
          'ip' => $request->getRemoteAddr()
        ));
      $editor = id(new ConpherenceEditor())
        ->setContinueOnNoEffect($request->isContinueRequest())
        ->setContentSource($content_source)
        ->setActor($user);

      switch ($action) {
        case 'message':
          $message = $request->getStr('text');
          $xactions = $editor->generateTransactionsFromText(
            $conpherence,
            $message);
          break;
        case 'add_person':
          $xactions = array();
          $person_tokenizer = $request->getArr('add_person');
          $person_phid = reset($person_tokenizer);
          if ($person_phid) {
            $xactions[] = id(new ConpherenceTransaction())
              ->setTransactionType(
                ConpherenceTransactionType::TYPE_PARTICIPANTS)
              ->setNewValue(array('+' => array($person_phid)));
          }
          break;
        case 'remove_person':
          $xactions = array();
          if (!$request->isContinueRequest()) {
            // do nothing; we'll display a confirmation dialogue instead
            break;
          }
          $person_phid = $request->getStr('remove_person');
          if ($person_phid && $person_phid == $user->getPHID()) {
            $xactions[] = id(new ConpherenceTransaction())
              ->setTransactionType(
                ConpherenceTransactionType::TYPE_PARTICIPANTS)
              ->setNewValue(array('-' => array($person_phid)));
            $response_mode = 'go-home';
          }
          break;
        case 'notifications':
          $notifications = $request->getStr('notifications');
          $participant = $conpherence->getParticipant($user->getPHID());
          $participant->setSettings(array('notifications' => $notifications));
          $participant->save();
          $result = pht(
            'Updated notification settings to "%s".',
            ConpherenceSettings::getHumanString($notifications));
          return id(new AphrontAjaxResponse())
            ->setContent($result);
          break;
        case 'metadata':
          $xactions = array();
          $top = $request->getInt('image_y');
          $left = $request->getInt('image_x');
          $file_id = $request->getInt('file_id');
          $title = $request->getStr('title');
          $updated = false;
          if ($file_id) {
            $orig_file = id(new PhabricatorFileQuery())
              ->setViewer($user)
              ->withIDs(array($file_id))
              ->executeOne();
            $okay = $orig_file->isTransformableImage();
            if ($okay) {
              $xactions[] = id(new ConpherenceTransaction())
                ->setTransactionType(ConpherenceTransactionType::TYPE_PICTURE)
                ->setNewValue($orig_file->getPHID());
              // do a transformation "crudely"
              $xformer = new PhabricatorImageTransformer();
              $header_file = $xformer->executeConpherenceTransform(
                $orig_file,
                0,
                0,
                ConpherenceImageData::HEAD_WIDTH,
                ConpherenceImageData::HEAD_HEIGHT);
              // this is handled outside the editor for now. no particularly
              // good reason to move it inside
              $conpherence->setImagePHIDs(
                array(
                  ConpherenceImageData::SIZE_HEAD => $header_file->getPHID(),
                ));
              $conpherence->setImages(
                array(
                  ConpherenceImageData::SIZE_HEAD => $header_file,
                ));
            } else {
              $e_file[] = $orig_file;
              $errors[] =
                pht('This server only supports these image formats: %s.',
                  implode(', ', $supported_formats));
            }
            // use the existing title in this image upload case
            $title = $conpherence->getTitle();
            $updated = true;
            $response_mode = 'redirect';
          }

          // all other metadata updates are continue requests
          if (!$request->isContinueRequest()) {
            break;
          }

          if ($top !== null || $left !== null) {
            $file = $conpherence->getImage(ConpherenceImageData::SIZE_ORIG);
            $xformer = new PhabricatorImageTransformer();
            $xformed = $xformer->executeConpherenceTransform(
              $file,
              $top,
              $left,
              ConpherenceImageData::HEAD_WIDTH,
              ConpherenceImageData::HEAD_HEIGHT);
            $image_phid = $xformed->getPHID();

            $xactions[] = id(new ConpherenceTransaction())
              ->setTransactionType(
                ConpherenceTransactionType::TYPE_PICTURE_CROP)
                ->setNewValue($image_phid);
            $updated = true;
          }
          if ($title != $conpherence->getTitle()) {
            $xactions[] = id(new ConpherenceTransaction())
              ->setTransactionType(ConpherenceTransactionType::TYPE_TITLE)
              ->setNewValue($title);
            $updated = true;
          }
          if (!$updated) {
            $errors[] = pht(
              'That was a non-update. Try cancel.');
          }
          break;
        default:
          throw new Exception('Unknown action: '.$action);
          break;
      }
      if ($xactions) {
        try {
          $xactions = $editor->applyTransactions($conpherence, $xactions);
        } catch (PhabricatorApplicationTransactionNoEffectException $ex) {
          return id(new PhabricatorApplicationTransactionNoEffectResponse())
            ->setCancelURI($this->getApplicationURI($conpherence_id.'/'))
            ->setException($ex);
        }
        switch ($response_mode) {
          case 'ajax':
            $latest_transaction_id = $request->getInt('latest_transaction_id');
            $content = $this->loadAndRenderUpdates(
              $conpherence_id,
              $latest_transaction_id);
            return id(new AphrontAjaxResponse())
              ->setContent($content);
            break;
          case 'go-home':
            return id(new AphrontRedirectResponse())
              ->setURI($this->getApplicationURI());
            break;
          case 'redirect':
          default:
            return id(new AphrontRedirectResponse())
              ->setURI($this->getApplicationURI($conpherence->getID().'/'));
            break;
        }
      }
    }

    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle(pht('Errors editing conpherence.'))
        ->setInsideDialogue(true)
        ->setErrors($errors);
    }

    switch ($action) {
      case 'remove_person':
        $dialogue = $this->renderRemovePersonDialogue($conpherence);
        break;
      case 'metadata':
      default:
        $dialogue = $this->renderMetadataDialogue($conpherence, $error_view);
        break;
    }

    return id(new AphrontDialogResponse())
      ->setDialog($dialogue
        ->setUser($user)
        ->setWidth(AphrontDialogView::WIDTH_FORM)
        ->setSubmitURI($this->getApplicationURI('update/'.$conpherence_id.'/'))
        ->addSubmitButton()
        ->addCancelButton($this->getApplicationURI($conpherence->getID().'/')));

  }

  private function renderRemovePersonDialogue(
    ConpherenceThread $conpherence) {

    $request = $this->getRequest();
    $user = $request->getUser();
    $remove_person = $request->getStr('remove_person');
    $participants = $conpherence->getParticipants();
    $message = pht(
      'Are you sure you want to remove yourself from this conpherence? ');
    if (count($participants) == 1) {
      $message .= pht(
        'The conpherence will be inaccessible forever and ever.');
    } else {
      $message .= pht(
        'Someone else in the conpherence can add you back later.');
    }
    $body = phutil_tag(
      'p',
      array(
      ),
      $message);

    require_celerity_resource('conpherence-update-css');
    return id(new AphrontDialogView())
      ->setTitle(pht('Update Conpherence Participants'))
      ->addHiddenInput('action', 'remove_person')
      ->addHiddenInput('__continue__', true)
      ->addHiddenInput('remove_person', $remove_person)
      ->appendChild($body);
  }

  private function renderMetadataDialogue(
    ConpherenceThread $conpherence,
    $error_view) {

    $form = id(new AphrontFormLayoutView())
      ->appendChild($error_view)
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel(pht('Title'))
        ->setName('title')
        ->setValue($conpherence->getTitle()));

    $image = $conpherence->getImage(ConpherenceImageData::SIZE_ORIG);
    if ($image) {
      $form
        ->appendChild(
          id(new AphrontFormMarkupControl())
          ->setLabel(pht('Image'))
          ->setValue(phutil_tag(
            'img',
            array(
              'src' =>
              $conpherence->loadImageURI(ConpherenceImageData::SIZE_HEAD),
              ))))
        ->appendChild(
          id(new AphrontFormCropControl())
          ->setLabel(pht('Crop Image'))
          ->setValue($image)
          ->setWidth(ConpherenceImageData::HEAD_WIDTH)
          ->setHeight(ConpherenceImageData::HEAD_HEIGHT))
          ->appendChild(
            id(new ConpherenceFormDragAndDropUploadControl())
            ->setLabel(pht('Change Image')));
    } else {
      $form
        ->appendChild(
          id(new ConpherenceFormDragAndDropUploadControl())
          ->setLabel(pht('Image')));
    }

    require_celerity_resource('conpherence-update-css');
    return id(new AphrontDialogView())
      ->setTitle(pht('Update Conpherence'))
      ->addHiddenInput('action', 'metadata')
      ->addHiddenInput('__continue__', true)
      ->appendChild($form);
  }

  private function loadAndRenderUpdates(
    $conpherence_id,
    $latest_transaction_id) {

    $user = $this->getRequest()->getUser();
    $conpherence = id(new ConpherenceThreadQuery())
      ->setViewer($user)
      ->setAfterMessageID($latest_transaction_id)
      ->needHeaderPics(true)
      ->needWidgetData(true)
      ->needAllTransactions(true)
      ->withIDs(array($conpherence_id))
      ->executeOne();

    $data = $this->renderConpherenceTransactions($conpherence);
    $rendered_transactions = $data['transactions'];
    $new_latest_transaction_id = $data['latest_transaction_id'];

    $nav_item = id(new ConpherenceThreadListView())
      ->setUser($user)
      ->setBaseURI($this->getApplicationURI())
      ->renderSingleThread($conpherence);

    $header = $this->buildHeaderPaneContent($conpherence);

    $widget_uri = $this->getApplicationURI('update/'.$conpherence->getID().'/');
    $file_widget = id(new ConpherenceFileWidgetView())
      ->setUser($this->getRequest()->getUser())
      ->setConpherence($conpherence)
      ->setUpdateURI($widget_uri);
    $people_widget = id(new ConpherencePeopleWidgetView())
      ->setUser($user)
      ->setConpherence($conpherence)
      ->setUpdateURI($widget_uri);

    $content = array(
      'transactions' => $rendered_transactions,
      'latest_transaction_id' => $new_latest_transaction_id,
      'nav_item' => hsprintf('%s', $nav_item),
      'conpherence_phid' => $conpherence->getPHID(),
      'header' => hsprintf('%s', $header),

      // TODO: Fix these.
//      'file_widget' => $file_widget->render(),
//      'people_widget' => $people_widget->render()
    );
    return $content;
  }

}
