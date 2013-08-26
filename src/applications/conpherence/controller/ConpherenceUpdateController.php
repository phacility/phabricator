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
      ->needFilePHIDs(true)
      ->executeOne();

    $action = $request->getStr('action', ConpherenceUpdateActions::METADATA);
    $latest_transaction_id = null;
    $response_mode = 'ajax';
    $error_view = null;
    $e_file = array();
    $errors = array();
    if ($request->isFormPost()) {
      $editor = id(new ConpherenceEditor())
        ->setContinueOnNoEffect($request->isContinueRequest())
        ->setContentSourceFromRequest($request)
        ->setActor($user);

      switch ($action) {
        case ConpherenceUpdateActions::MESSAGE:
          $message = $request->getStr('text');
          $xactions = $editor->generateTransactionsFromText(
            $conpherence,
            $message);
          break;
        case ConpherenceUpdateActions::ADD_PERSON:
          $xactions = array();
          $person_phids = $request->getArr('add_person');
          if (!empty($person_phids)) {
            $xactions[] = id(new ConpherenceTransaction())
              ->setTransactionType(
                ConpherenceTransactionType::TYPE_PARTICIPANTS)
              ->setNewValue(array('+' => $person_phids));
          }
          break;
        case ConpherenceUpdateActions::REMOVE_PERSON:
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
        case ConpherenceUpdateActions::NOTIFICATIONS:
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
        case ConpherenceUpdateActions::METADATA:
          $xactions = array();
          $updated = false;
          // all metadata updates are continue requests
          if (!$request->isContinueRequest()) {
            break;
          }

          $title = $request->getStr('title');
          if ($title != $conpherence->getTitle()) {
            $xactions[] = id(new ConpherenceTransaction())
              ->setTransactionType(ConpherenceTransactionType::TYPE_TITLE)
              ->setNewValue($title);
            $updated = true;
            $response_mode = 'redirect';
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

    $form = id(new PHUIFormLayoutView())
      ->setUser($user)
      ->setFullWidth(true)
      ->appendChild(
        id(new AphrontFormTokenizerControl())
        ->setName('add_person')
        ->setUser($user)
        ->setDatasource('/typeahead/common/users/'));

    require_celerity_resource('conpherence-update-css');
    return id(new AphrontDialogView())
      ->setTitle(pht('Add Participants'))
      ->addHiddenInput('action', 'add_person')
      ->appendChild($form);
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
      ->setTitle(pht('Remove Participants'))
      ->addHiddenInput('action', 'remove_person')
      ->addHiddenInput('__continue__', true)
      ->addHiddenInput('remove_person', $remove_person)
      ->appendChild($body);
  }

  private function renderMetadataDialogue(
    ConpherenceThread $conpherence,
    $error_view) {

    $form = id(new PHUIFormLayoutView())
      ->appendChild($error_view)
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel(pht('Title'))
        ->setName('title')
        ->setValue($conpherence->getTitle()));

    require_celerity_resource('conpherence-update-css');
    return id(new AphrontDialogView())
      ->setTitle(pht('Update Conpherence'))
      ->addHiddenInput('action', 'metadata')
      ->addHiddenInput('__continue__', true)
      ->appendChild($form);
  }

  private function loadAndRenderUpdates(
    $action,
    $conpherence_id,
    $latest_transaction_id) {

    $need_widget_data = false;
    $need_transactions = false;
    switch ($action) {
      case ConpherenceUpdateActions::METADATA:
        $need_transactions = true;
        break;
      case ConpherenceUpdateActions::MESSAGE:
      case ConpherenceUpdateActions::ADD_PERSON:
        $need_transactions = true;
        $need_widget_data = true;
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
      ->needWidgetData($need_widget_data)
      ->needTransactions($need_transactions)
      ->withIDs(array($conpherence_id))
      ->executeOne();

    if ($need_transactions) {
      $data = $this->renderConpherenceTransactions($conpherence);
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
    switch ($action) {
      case ConpherenceUpdateActions::METADATA:
        $header = $this->buildHeaderPaneContent($conpherence);
        $nav_item = id(new ConpherenceThreadListView())
          ->setUser($user)
          ->setBaseURI($this->getApplicationURI())
          ->renderSingleThread($conpherence);
        break;
      case ConpherenceUpdateActions::MESSAGE:
        $file_widget = id(new ConpherenceFileWidgetView())
          ->setUser($this->getRequest()->getUser())
          ->setConpherence($conpherence)
          ->setUpdateURI($widget_uri);
        break;
      case ConpherenceUpdateActions::ADD_PERSON:
        $people_widget = id(new ConpherencePeopleWidgetView())
          ->setUser($user)
          ->setConpherence($conpherence)
          ->setUpdateURI($widget_uri);
        break;
      case ConpherenceUpdateActions::REMOVE_PERSON:
      case ConpherenceUpdateActions::NOTIFICATIONS:
      default:
        break;
    }

    $people_html = null;
    if ($people_widget) {
      $people_html = hsprintf('%s', $people_widget->render());
    }
    $content = array(
      'transactions' => hsprintf('%s', $rendered_transactions),
      'latest_transaction_id' => $new_latest_transaction_id,
      'nav_item' => hsprintf('%s', $nav_item),
      'conpherence_phid' => $conpherence->getPHID(),
      'header' => hsprintf('%s', $header),
      'file_widget' => $file_widget ? $file_widget->render() : null,
      'people_widget' => $people_html,
    );

    return $content;
  }

}
