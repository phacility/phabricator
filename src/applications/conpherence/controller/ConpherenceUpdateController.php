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
    $action = $request->getStr('action');
    switch ($action) {
      case ConpherenceUpdateActions::REMOVE_PERSON:
        $person_phid = $request->getStr('remove_person');
        if ($person_phid != $user->getPHID()) {
          $needed_capabilities[] = PhabricatorPolicyCapability::CAN_EDIT;
        }
        break;
      case ConpherenceUpdateActions::ADD_PERSON:
        $needed_capabilities[] = PhabricatorPolicyCapability::CAN_EDIT;
        break;
      case ConpherenceUpdateActions::LOAD:
        break;
    }
    $conpherence = id(new ConpherenceThreadQuery())
      ->setViewer($user)
      ->withIDs(array($conpherence_id))
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
              ConpherenceThreadParticipantsTransaction::TRANSACTIONTYPE)
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
                ConpherenceThreadParticipantsTransaction::TRANSACTIONTYPE)
              ->setNewValue(array('+' => $person_phids));
          }
          break;
        case ConpherenceUpdateActions::REMOVE_PERSON:
          if (!$request->isContinueRequest()) {
            // do nothing; we'll display a confirmation dialog instead
            break;
          }
          $person_phid = $request->getStr('remove_person');
          if ($person_phid) {
            $xactions[] = id(new ConpherenceTransaction())
              ->setTransactionType(
                ConpherenceThreadParticipantsTransaction::TRANSACTIONTYPE)
              ->setNewValue(array('-' => array($person_phid)));
            $response_mode = 'go-home';
          }
          break;
        case ConpherenceUpdateActions::LOAD:
          $updated = false;
          $response_mode = 'ajax';
          break;
        default:
          throw new Exception(pht('Unknown action: %s', $action));
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
            $content = array(
              'href' => $this->getApplicationURI(),
            );
            return id(new AphrontAjaxResponse())
              ->setContent($content);
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
        $dialog = $this->renderAddPersonDialog($conpherence);
        break;
      case ConpherenceUpdateActions::REMOVE_PERSON:
        $dialog = $this->renderRemovePersonDialog($conpherence);
        break;
    }

    return
      $dialog
        ->setUser($user)
        ->setWidth(AphrontDialogView::WIDTH_FORM)
        ->setSubmitURI($this->getApplicationURI('update/'.$conpherence_id.'/'))
        ->addSubmitButton()
        ->addCancelButton($this->getApplicationURI($conpherence->getID().'/'));

  }

  private function renderAddPersonDialog(
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

    $view = id(new AphrontDialogView())
      ->setTitle(pht('Add Participants'))
      ->addHiddenInput('action', 'add_person')
      ->addHiddenInput(
        'latest_transaction_id',
        $request->getInt('latest_transaction_id'))
      ->appendForm($form);

    return $view;
  }

  private function renderRemovePersonDialog(
    ConpherenceThread $conpherence) {

    $request = $this->getRequest();
    $viewer = $request->getUser();
    $remove_person = $request->getStr('remove_person');
    $participants = $conpherence->getParticipants();

    $removed_user = id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($remove_person))
      ->executeOne();
    if (!$removed_user) {
      return new Aphront404Response();
    }

    $is_self = ($viewer->getPHID() == $removed_user->getPHID());
    $is_last = (count($participants) == 1);

    $test_conpherence = clone $conpherence;
    $test_conpherence->attachParticipants(array());
    $still_visible = PhabricatorPolicyFilter::hasCapability(
      $removed_user,
      $test_conpherence,
      PhabricatorPolicyCapability::CAN_VIEW);

    $body = array();

    if ($is_self) {
      $title = pht('Leave Room');
      $body[] = pht(
        'Are you sure you want to leave this room?');
    } else {
      $title = pht('Remove Participant');
      $body[] = pht(
        'Remove %s from this room?',
        phutil_tag('strong', array(), $removed_user->getUsername()));
    }

    if ($still_visible) {
      if ($is_self) {
        $body[] = pht(
          'You will be able to rejoin the room later.');
      } else {
        $body[] = pht(
          'They will be able to rejoin the room later.');
      }
    } else {
      if ($is_self) {
        if ($is_last) {
          $body[] = pht(
            'You are the last member, so you will never be able to rejoin '.
            'the room.');
        } else {
          $body[] = pht(
            'You will not be able to rejoin the room on your own, but '.
            'someone else can invite you later.');
        }
      } else {
        $body[] = pht(
          'They will not be able to rejoin the room unless invited '.
          'again.');
      }
    }

    $dialog = id(new AphrontDialogView())
      ->setTitle($title)
      ->addHiddenInput('action', 'remove_person')
      ->addHiddenInput('remove_person', $remove_person)
      ->addHiddenInput(
        'latest_transaction_id',
        $request->getInt('latest_transaction_id'))
      ->addHiddenInput('__continue__', true);

    foreach ($body as $paragraph) {
      $dialog->appendParagraph($paragraph);
    }

    return $dialog;
  }

  private function loadAndRenderUpdates(
    $action,
    $conpherence_id,
    $latest_transaction_id) {

    $need_transactions = false;
    switch ($action) {
      case ConpherenceUpdateActions::LOAD:
        $need_transactions = true;
        break;
      case ConpherenceUpdateActions::MESSAGE:
      case ConpherenceUpdateActions::ADD_PERSON:
        $need_transactions = true;
        break;
      case ConpherenceUpdateActions::REMOVE_PERSON:
      default:
        break;

    }
    $user = $this->getRequest()->getUser();
    $conpherence = id(new ConpherenceThreadQuery())
      ->setViewer($user)
      ->setAfterTransactionID($latest_transaction_id)
      ->needProfileImage(true)
      ->needParticipants(true)
      ->needTransactions($need_transactions)
      ->withIDs(array($conpherence_id))
      ->executeOne();

    $non_update = false;
    $participant = $conpherence->getParticipant($user->getPHID());

    if ($need_transactions && $conpherence->getTransactions()) {
      $data = ConpherenceTransactionRenderer::renderTransactions(
        $user,
        $conpherence);
      $key = PhabricatorConpherenceColumnMinimizeSetting::SETTINGKEY;
      $minimized = $user->getUserSetting($key);
      if (!$minimized) {
        $participant->markUpToDate($conpherence);
      }
    } else if ($need_transactions) {
      $non_update = true;
      $data = array();
    } else {
      $data = array();
    }
    $rendered_transactions = idx($data, 'transactions');
    $new_latest_transaction_id = idx($data, 'latest_transaction_id');

    $update_uri = $this->getApplicationURI('update/'.$conpherence->getID().'/');
    $nav_item = null;
    $header = null;
    $people_widget = null;
    switch ($action) {
      case ConpherenceUpdateActions::ADD_PERSON:
        $people_widget = id(new ConpherenceParticipantView())
          ->setUser($user)
          ->setConpherence($conpherence)
          ->setUpdateURI($update_uri);
        $people_widget = hsprintf('%s', $people_widget->render());
        break;
      case ConpherenceUpdateActions::REMOVE_PERSON:
      default:
        break;
    }
    $data = $conpherence->getDisplayData($user);
    $dropdown_query = id(new AphlictDropdownDataQuery())
      ->setViewer($user);
    $dropdown_query->execute();

    $map = ConpherenceRoomSettings::getSoundMap();
    $default_receive = ConpherenceRoomSettings::DEFAULT_RECEIVE_SOUND;
    $receive_sound = $map[$default_receive]['rsrc'];
    $mention_sound = null;

    // Get the user's defaults if logged in
    if ($participant) {
      $sounds = $this->getSoundForParticipant($user, $participant);
      $receive_sound = $sounds[ConpherenceRoomSettings::SOUND_RECEIVE];
      $mention_sound = $sounds[ConpherenceRoomSettings::SOUND_MENTION];
    }

    $content = array(
      'non_update' => $non_update,
      'transactions' => hsprintf('%s', $rendered_transactions),
      'conpherence_title' => (string)$data['title'],
      'latest_transaction_id' => $new_latest_transaction_id,
      'nav_item' => $nav_item,
      'conpherence_phid' => $conpherence->getPHID(),
      'header' => $header,
      'people_widget' => $people_widget,
      'aphlictDropdownData' => array(
        $dropdown_query->getNotificationData(),
        $dropdown_query->getConpherenceData(),
      ),
      'sound' => array(
        'receive' => $receive_sound,
        'mention' => $mention_sound,
      ),
    );

    return $content;
  }

  protected function getSoundForParticipant(
    PhabricatorUser $user,
    ConpherenceParticipant $participant) {

    $sound_key = PhabricatorConpherenceSoundSetting::SETTINGKEY;
    $sound_default = $user->getUserSetting($sound_key);

    $settings = $participant->getSettings();
    $sounds = idx($settings, 'sounds', array());
    $map = PhabricatorConpherenceSoundSetting::getDefaultSound($sound_default);

    $receive = idx($sounds,
      ConpherenceRoomSettings::SOUND_RECEIVE,
      $map[ConpherenceRoomSettings::SOUND_RECEIVE]);
    $mention = idx($sounds,
      ConpherenceRoomSettings::SOUND_MENTION,
      $map[ConpherenceRoomSettings::SOUND_MENTION]);

    $sound_map = ConpherenceRoomSettings::getSoundMap();

    return array(
      ConpherenceRoomSettings::SOUND_RECEIVE => $sound_map[$receive]['rsrc'],
      ConpherenceRoomSettings::SOUND_MENTION => $sound_map[$mention]['rsrc'],
    );

  }

}
