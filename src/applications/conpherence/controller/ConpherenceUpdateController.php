<?php

final class ConpherenceUpdateController
  extends ConpherenceController {

  public function handleRequest(AphrontRequest $request) {
    $user = $request->getUser();
    $conpherence_id = $request->getURIData('id');
    if (!$conpherence_id) {
      return new Aphront404Response();
    }

    $need_participants = false;
    $needed_capabilities = array(PhabricatorPolicyCapability::CAN_VIEW);
    $action = $request->getStr('action', ConpherenceUpdateActions::METADATA);
    switch ($action) {
      case ConpherenceUpdateActions::REMOVE_PERSON:
        $person_phid = $request->getStr('remove_person');
        if ($person_phid != $user->getPHID()) {
          $needed_capabilities[] = PhabricatorPolicyCapability::CAN_EDIT;
        }
        break;
      case ConpherenceUpdateActions::ADD_PERSON:
      case ConpherenceUpdateActions::METADATA:
        $needed_capabilities[] = PhabricatorPolicyCapability::CAN_EDIT;
        break;
      case ConpherenceUpdateActions::JOIN_ROOM:
        $needed_capabilities[] = PhabricatorPolicyCapability::CAN_JOIN;
        break;
      case ConpherenceUpdateActions::NOTIFICATIONS:
        $need_participants = true;
        break;
      case ConpherenceUpdateActions::LOAD:
        break;
    }
    $conpherence = id(new ConpherenceThreadQuery())
      ->setViewer($user)
      ->withIDs(array($conpherence_id))
      ->needFilePHIDs(true)
      ->needOrigPics(true)
      ->needCropPics(true)
      ->needParticipants($need_participants)
      ->requireCapabilities($needed_capabilities)
      ->executeOne();

    $latest_transaction_id = null;
    $response_mode = $request->isAjax() ? 'ajax' : 'redirect';
    $error_view = null;
    $e_file = array();
    $errors = array();
    $delete_draft = false;
    $xactions = array();
    if ($request->isFormPost() || ($action == ConpherenceUpdateActions::LOAD)) {
      $editor = id(new ConpherenceEditor())
        ->setContinueOnNoEffect($request->isContinueRequest())
        ->setContentSourceFromRequest($request)
        ->setActor($user);

      switch ($action) {
        case ConpherenceUpdateActions::DRAFT:
          $draft = PhabricatorDraft::newFromUserAndKey(
            $user,
            $conpherence->getPHID());
          $draft->setDraft($request->getStr('text'));
          $draft->replaceOrDelete();
          return new AphrontAjaxResponse();
        case ConpherenceUpdateActions::JOIN_ROOM:
          $xactions[] = id(new ConpherenceTransaction())
            ->setTransactionType(
              ConpherenceTransactionType::TYPE_PARTICIPANTS)
            ->setNewValue(array('+' => array($user->getPHID())));
          $delete_draft = true;
          $message = $request->getStr('text');
          if ($message) {
            $message_xactions = $editor->generateTransactionsFromText(
              $user,
              $conpherence,
              $message);
            $xactions = array_merge($xactions, $message_xactions);
          }
          // for now, just redirect back to the conpherence so everything
          // will work okay...!
          $response_mode = 'redirect';
          break;
        case ConpherenceUpdateActions::MESSAGE:
          $message = $request->getStr('text');
          if (strlen($message)) {
            $xactions = $editor->generateTransactionsFromText(
              $user,
              $conpherence,
              $message);
            $delete_draft = true;
          } else {
            $action = ConpherenceUpdateActions::LOAD;
            $updated = false;
            $response_mode = 'ajax';
          }
          break;
        case ConpherenceUpdateActions::ADD_PERSON:
          $person_phids = $request->getArr('add_person');
          if (!empty($person_phids)) {
            $xactions[] = id(new ConpherenceTransaction())
              ->setTransactionType(
                ConpherenceTransactionType::TYPE_PARTICIPANTS)
              ->setNewValue(array('+' => $person_phids));
          }
          break;
        case ConpherenceUpdateActions::REMOVE_PERSON:
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
        case ConpherenceUpdateActions::NOTIFICATIONS:
          $notifications = $request->getStr('notifications');
          $participant = $conpherence->getParticipantIfExists($user->getPHID());
          if (!$participant) {
            return id(new Aphront404Response());
          }
          $participant->setSettings(array('notifications' => $notifications));
          $participant->save();
          $result = pht(
            'Updated notification settings to "%s".',
            ConpherenceSettings::getHumanString($notifications));
          return id(new AphrontAjaxResponse())
            ->setContent($result);
          break;
        case ConpherenceUpdateActions::METADATA:
          $top = $request->getInt('image_y');
          $left = $request->getInt('image_x');
          $file_id = $request->getInt('file_id');
          $title = $request->getStr('title');
          if ($file_id) {
            $orig_file = id(new PhabricatorFileQuery())
              ->setViewer($user)
              ->withIDs(array($file_id))
              ->executeOne();
            $xactions[] = id(new ConpherenceTransaction())
              ->setTransactionType(ConpherenceTransactionType::TYPE_PICTURE)
              ->setNewValue($orig_file);
            $okay = $orig_file->isTransformableImage();
            if ($okay) {
              $xformer = new PhabricatorImageTransformer();
              $crop_file = $xformer->executeConpherenceTransform(
                $orig_file,
                0,
                0,
                ConpherenceImageData::CROP_WIDTH,
                ConpherenceImageData::CROP_HEIGHT);
              $xactions[] = id(new ConpherenceTransaction())
                ->setTransactionType(
                  ConpherenceTransactionType::TYPE_PICTURE_CROP)
                ->setNewValue($crop_file->getPHID());
            }
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
              ConpherenceImageData::CROP_WIDTH,
              ConpherenceImageData::CROP_HEIGHT);
            $image_phid = $xformed->getPHID();

            $xactions[] = id(new ConpherenceTransaction())
              ->setTransactionType(
                ConpherenceTransactionType::TYPE_PICTURE_CROP)
              ->setNewValue($image_phid);
          }
          $title = $request->getStr('title');
          $xactions[] = id(new ConpherenceTransaction())
            ->setTransactionType(ConpherenceTransactionType::TYPE_TITLE)
            ->setNewValue($title);
          if ($conpherence->getIsRoom()) {
            $xactions[] = id(new ConpherenceTransaction())
              ->setTransactionType(PhabricatorTransactions::TYPE_VIEW_POLICY)
              ->setNewValue($request->getStr('viewPolicy'));
            $xactions[] = id(new ConpherenceTransaction())
              ->setTransactionType(PhabricatorTransactions::TYPE_EDIT_POLICY)
              ->setNewValue($request->getStr('editPolicy'));
            $xactions[] = id(new ConpherenceTransaction())
              ->setTransactionType(PhabricatorTransactions::TYPE_JOIN_POLICY)
              ->setNewValue($request->getStr('joinPolicy'));
          }
          if (!$request->getExists('force_ajax')) {
            $response_mode = 'redirect';
          }
          break;
        case ConpherenceUpdateActions::LOAD:
          $updated = false;
          $response_mode = 'ajax';
          break;
        default:
          throw new Exception('Unknown action: '.$action);
          break;
      }

      if ($xactions) {
        try {
          $xactions = $editor->applyTransactions($conpherence, $xactions);
          if ($delete_draft) {
            $draft = PhabricatorDraft::newFromUserAndKey(
              $user,
              $conpherence->getPHID());
            $draft->delete();
          }
        } catch (PhabricatorApplicationTransactionNoEffectException $ex) {
          return id(new PhabricatorApplicationTransactionNoEffectResponse())
            ->setCancelURI($this->getApplicationURI($conpherence_id.'/'))
            ->setException($ex);
        }
        // xactions had no effect...!
        if (empty($xactions)) {
          $errors[] = pht(
            'That was a non-update. Try cancel.');
        }
      }

      if ($xactions || ($action == ConpherenceUpdateActions::LOAD)) {
        switch ($response_mode) {
          case 'ajax':
            $latest_transaction_id = $request->getInt('latest_transaction_id');
            $content = $this->loadAndRenderUpdates(
              $action,
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
              ->setURI('/'.$conpherence->getMonogram());
            break;
        }
      }
    }

    if ($errors) {
      $error_view = id(new PHUIInfoView())
        ->setErrors($errors);
    }

    switch ($action) {
      case ConpherenceUpdateActions::ADD_PERSON:
        $dialogue = $this->renderAddPersonDialogue($conpherence);
        break;
      case ConpherenceUpdateActions::REMOVE_PERSON:
        $dialogue = $this->renderRemovePersonDialogue($conpherence);
        break;
      case ConpherenceUpdateActions::METADATA:
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

  private function renderAddPersonDialogue(
    ConpherenceThread $conpherence) {

    $request = $this->getRequest();
    $user = $request->getUser();
    $add_person = $request->getStr('add_person');

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->setFullWidth(true)
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setName('add_person')
          ->setUser($user)
          ->setDatasource(new PhabricatorPeopleDatasource()));

    require_celerity_resource('conpherence-update-css');
    $view = id(new AphrontDialogView())
      ->setTitle(pht('Add Participants'))
      ->addHiddenInput('action', 'add_person')
      ->addHiddenInput(
        'latest_transaction_id',
        $request->getInt('latest_transaction_id'))
      ->appendForm($form);

    if ($request->getExists('minimal_display')) {
      $view->addHiddenInput('minimal_display', true);
    }
    return $view;
  }

  private function renderRemovePersonDialogue(
    ConpherenceThread $conpherence) {

    $request = $this->getRequest();
    $user = $request->getUser();
    $remove_person = $request->getStr('remove_person');
    $participants = $conpherence->getParticipants();
    if ($conpherence->getIsRoom()) {
      $message = pht(
        'Are you sure you want to remove yourself from this room?');
    } else {
      $message = pht(
        'Are you sure you want to remove yourself from this thread?');
      if (count($participants) == 1) {
        $message .= pht(
          'The thread will be inaccessible forever and ever.');
      } else {
        $message .= pht(
          'Someone else in the thread can add you back later.');
      }
    }
    $body = phutil_tag(
      'p',
      array(
      ),
      $message);

    require_celerity_resource('conpherence-update-css');
    return id(new AphrontDialogView())
      ->setTitle(pht('Remove Participants'))
      ->addHiddenInput('action', 'remove_person')
      ->addHiddenInput('remove_person', $remove_person)
      ->addHiddenInput(
        'latest_transaction_id',
        $request->getInt('latest_transaction_id'))
      ->addHiddenInput('__continue__', true)
      ->appendChild($body);
  }

  private function renderMetadataDialogue(
    ConpherenceThread $conpherence,
    $error_view) {

    $request = $this->getRequest();
    $user = $request->getUser();

    $form = id(new PHUIFormLayoutView())
      ->appendChild($error_view)
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel(pht('Title'))
        ->setName('title')
        ->setValue($conpherence->getTitle()));

    $nopic = $this->getRequest()->getExists('nopic');
    $image = $conpherence->getImage(ConpherenceImageData::SIZE_ORIG);
    if ($nopic) {
      // do not render any pic related controls
    } else if ($image) {
      $crop_uri = $conpherence->loadImageURI(ConpherenceImageData::SIZE_CROP);
      $form
        ->appendChild(
          id(new AphrontFormMarkupControl())
          ->setLabel(pht('Image'))
          ->setValue(phutil_tag(
            'img',
            array(
              'src' => $crop_uri,
              ))))
        ->appendChild(
          id(new ConpherencePicCropControl())
          ->setLabel(pht('Crop Image'))
          ->setValue($image))
        ->appendChild(
          id(new ConpherenceFormDragAndDropUploadControl())
          ->setLabel(pht('Change Image')));
    } else {
      $form
        ->appendChild(
          id(new ConpherenceFormDragAndDropUploadControl())
          ->setLabel(pht('Image')));
    }

    if ($conpherence->getIsRoom()) {
      $title = pht('Update Room');
      $policies = id(new PhabricatorPolicyQuery())
        ->setViewer($user)
        ->setObject($conpherence)
        ->execute();

      $form->appendChild(
        id(new AphrontFormPolicyControl())
        ->setName('viewPolicy')
        ->setPolicyObject($conpherence)
        ->setCapability(PhabricatorPolicyCapability::CAN_VIEW)
        ->setPolicies($policies))
      ->appendChild(
        id(new AphrontFormPolicyControl())
        ->setName('editPolicy')
        ->setPolicyObject($conpherence)
        ->setCapability(PhabricatorPolicyCapability::CAN_EDIT)
        ->setPolicies($policies))
      ->appendChild(
        id(new AphrontFormPolicyControl())
        ->setName('joinPolicy')
        ->setPolicyObject($conpherence)
        ->setCapability(PhabricatorPolicyCapability::CAN_JOIN)
        ->setPolicies($policies));
    } else {
      $title = pht('Update Thread');
    }

    require_celerity_resource('conpherence-update-css');
    $view = id(new AphrontDialogView())
      ->setTitle($title)
      ->addHiddenInput('action', 'metadata')
      ->addHiddenInput(
        'latest_transaction_id',
        $request->getInt('latest_transaction_id'))
      ->addHiddenInput('__continue__', true)
      ->appendChild($form);

    if ($request->getExists('minimal_display')) {
      $view->addHiddenInput('minimal_display', true);
    }
    if ($request->getExists('force_ajax')) {
      $view->addHiddenInput('force_ajax', true);
    }

    return $view;
  }

  private function loadAndRenderUpdates(
    $action,
    $conpherence_id,
    $latest_transaction_id) {

    $minimal_display = $this->getRequest()->getExists('minimal_display');
    $need_widget_data = false;
    $need_transactions = false;
    $need_participant_cache = true;
    switch ($action) {
      case ConpherenceUpdateActions::METADATA:
      case ConpherenceUpdateActions::LOAD:
        $need_transactions = true;
        break;
      case ConpherenceUpdateActions::MESSAGE:
      case ConpherenceUpdateActions::ADD_PERSON:
        $need_transactions = true;
        $need_widget_data = !$minimal_display;
        break;
      case ConpherenceUpdateActions::REMOVE_PERSON:
      case ConpherenceUpdateActions::NOTIFICATIONS:
      default:
        break;

    }
    $user = $this->getRequest()->getUser();
    $conpherence = id(new ConpherenceThreadQuery())
      ->setViewer($user)
      ->setAfterTransactionID($latest_transaction_id)
      ->needCropPics(true)
      ->needParticipantCache($need_participant_cache)
      ->needWidgetData($need_widget_data)
      ->needTransactions($need_transactions)
      ->withIDs(array($conpherence_id))
      ->executeOne();

    $non_update = false;
    if ($need_transactions && $conpherence->getTransactions()) {
      $data = ConpherenceTransactionRenderer::renderTransactions(
        $user,
        $conpherence,
        !$minimal_display);
      $participant_obj = $conpherence->getParticipant($user->getPHID());
      $participant_obj->markUpToDate($conpherence, $data['latest_transaction']);
    } else if ($need_transactions) {
      $non_update = true;
      $data = array();
    } else {
      $data = array();
    }
    $rendered_transactions = idx($data, 'transactions');
    $new_latest_transaction_id = idx($data, 'latest_transaction_id');

    $widget_uri = $this->getApplicationURI('update/'.$conpherence->getID().'/');
    $nav_item = null;
    $header = null;
    $people_widget = null;
    $file_widget = null;
    if (!$minimal_display) {
      switch ($action) {
        case ConpherenceUpdateActions::METADATA:
          $policy_objects = id(new PhabricatorPolicyQuery())
            ->setViewer($user)
            ->setObject($conpherence)
            ->execute();
          $header = $this->buildHeaderPaneContent(
            $conpherence,
            $policy_objects);
          $header = hsprintf('%s', $header);
          $nav_item = id(new ConpherenceThreadListView())
            ->setUser($user)
            ->setBaseURI($this->getApplicationURI())
            ->renderSingleThread($conpherence);
          $nav_item = hsprintf('%s', $nav_item);
          break;
        case ConpherenceUpdateActions::MESSAGE:
          $file_widget = id(new ConpherenceFileWidgetView())
            ->setUser($this->getRequest()->getUser())
            ->setConpherence($conpherence)
            ->setUpdateURI($widget_uri);
          $file_widget = $file_widget->render();
          break;
        case ConpherenceUpdateActions::ADD_PERSON:
          $people_widget = id(new ConpherencePeopleWidgetView())
            ->setUser($user)
            ->setConpherence($conpherence)
            ->setUpdateURI($widget_uri);
          $people_widget = $people_widget->render();
          break;
        case ConpherenceUpdateActions::REMOVE_PERSON:
        case ConpherenceUpdateActions::NOTIFICATIONS:
        default:
          break;
      }
    }
    $data = $conpherence->getDisplayData($user);
    $dropdown_query = id(new AphlictDropdownDataQuery())
      ->setViewer($user);
    $dropdown_query->execute();
    $content = array(
      'non_update' => $non_update,
      'transactions' => hsprintf('%s', $rendered_transactions),
      'conpherence_title' => (string) $data['title'],
      'latest_transaction_id' => $new_latest_transaction_id,
      'nav_item' => $nav_item,
      'conpherence_phid' => $conpherence->getPHID(),
      'header' => $header,
      'file_widget' => $file_widget,
      'people_widget' => $people_widget,
      'aphlictDropdownData' => array(
        $dropdown_query->getNotificationData(),
        $dropdown_query->getConpherenceData(),
      ),
    );

    return $content;
  }

}
