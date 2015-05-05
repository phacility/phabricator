<?php

final class ConpherenceViewController extends
  ConpherenceController {

  const OLDER_FETCH_LIMIT = 5;

  public function handleRequest(AphrontRequest $request) {
    $user = $request->getUser();

    $conpherence_id = $request->getURIData('id');
    if (!$conpherence_id) {
      return new Aphront404Response();
    }
    $query = id(new ConpherenceThreadQuery())
      ->setViewer($user)
      ->withIDs(array($conpherence_id))
      ->needCropPics(true)
      ->needParticipantCache(true)
      ->needTransactions(true)
      ->setTransactionLimit($this->getMainQueryLimit());

    $before_transaction_id = $request->getInt('oldest_transaction_id');
    $after_transaction_id = $request->getInt('newest_transaction_id');
    $old_message_id = $request->getURIData('messageID');
    if ($before_transaction_id && ($old_message_id || $after_transaction_id)) {
      throw new Aphront400Response();
    }
    if ($old_message_id && $after_transaction_id) {
      throw new Aphront400Response();
    }

    $marker_type = 'older';
    if ($before_transaction_id) {
      $query
        ->setBeforeTransactionID($before_transaction_id);
    }
    if ($old_message_id) {
      $marker_type = 'olderandnewer';
      $query
        ->setAfterTransactionID($old_message_id - 1);
    }
    if ($after_transaction_id) {
      $marker_type = 'newer';
      $query
        ->setAfterTransactionID($after_transaction_id);
    }

    $conpherence = $query->executeOne();
    if (!$conpherence) {
      return new Aphront404Response();
    }
    $this->setConpherence($conpherence);

    $transactions = $this->getNeededTransactions(
      $conpherence,
      $old_message_id);
    $latest_transaction = head($transactions);
    $participant = $conpherence->getParticipantIfExists($user->getPHID());
    if ($participant) {
      $write_guard = AphrontWriteGuard::beginScopedUnguardedWrites();
      $participant->markUpToDate($conpherence, $latest_transaction);
      unset($write_guard);
    }

    $data = ConpherenceTransactionRenderer::renderTransactions(
      $user,
      $conpherence,
      $full_display = true,
      $marker_type);
    $messages = ConpherenceTransactionRenderer::renderMessagePaneContent(
      $data['transactions'],
      $data['oldest_transaction_id'],
      $data['newest_transaction_id']);
    if ($before_transaction_id || $after_transaction_id) {
      $header = null;
      $form = null;
      $content = array('messages' => $messages);
    } else {
      $policy_objects = id(new PhabricatorPolicyQuery())
        ->setViewer($user)
        ->setObject($conpherence)
        ->execute();
      $header = $this->buildHeaderPaneContent($conpherence, $policy_objects);
      $form = $this->renderFormContent();
      $content = array(
        'header' => $header,
        'messages' => $messages,
        'form' => $form,
      );
    }

    $d_data = $conpherence->getDisplayData($user);
    $content['title'] = $title = $d_data['title'];

    if ($request->isAjax()) {
      $content['threadID'] = $conpherence->getID();
      $content['threadPHID'] = $conpherence->getPHID();
      $content['latestTransactionID'] = $data['latest_transaction_id'];
      $content['canEdit'] = PhabricatorPolicyFilter::hasCapability(
        $user,
        $conpherence,
        PhabricatorPolicyCapability::CAN_EDIT);
      return id(new AphrontAjaxResponse())->setContent($content);
    }

    $layout = id(new ConpherenceLayoutView())
      ->setUser($user)
      ->setBaseURI($this->getApplicationURI())
      ->setThread($conpherence)
      ->setHeader($header)
      ->setMessages($messages)
      ->setReplyForm($form)
      ->setLatestTransactionID($data['latest_transaction_id'])
      ->setRole('thread');

   return $this->buildApplicationPage(
      $layout,
      array(
        'title' => $title,
        'pageObjects' => array($conpherence->getPHID()),
      ));
  }

  private function renderFormContent() {

    $conpherence = $this->getConpherence();
    $user = $this->getRequest()->getUser();
    $can_join = PhabricatorPolicyFilter::hasCapability(
      $user,
      $conpherence,
      PhabricatorPolicyCapability::CAN_JOIN);
    $participating = $conpherence->getParticipantIfExists($user->getPHID());
    if (!$can_join && !$participating) {
      return null;
    }
    $draft = PhabricatorDraft::newFromUserAndKey(
      $user,
      $conpherence->getPHID());
    if ($participating) {
      $action = ConpherenceUpdateActions::MESSAGE;
      $button_text = pht('Send');
    } else {
      $action = ConpherenceUpdateActions::JOIN_ROOM;
      $button_text = pht('Join');
    }
    $update_uri = $this->getApplicationURI('update/'.$conpherence->getID().'/');

    $this->initBehavior('conpherence-pontificate');

    $form =
      id(new AphrontFormView())
      ->setAction($update_uri)
      ->addSigil('conpherence-pontificate')
      ->setWorkflow(true)
      ->setUser($user)
      ->addHiddenInput('action', $action)
      ->appendChild(
        id(new PhabricatorRemarkupControl())
        ->setUser($user)
        ->setName('text')
        ->setValue($draft->getDraft()))
      ->appendChild(
        id(new AphrontFormSubmitControl())
        ->setValue($button_text))
      ->render();

    return $form;
  }

  private function getNeededTransactions(
    ConpherenceThread $conpherence,
    $message_id) {

    if ($message_id) {
      $newer_transactions = $conpherence->getTransactions();
      $query = id(new ConpherenceTransactionQuery())
        ->setViewer($this->getRequest()->getUser())
        ->withObjectPHIDs(array($conpherence->getPHID()))
        ->setAfterID($message_id)
        ->needHandles(true)
        ->setLimit(self::OLDER_FETCH_LIMIT);
      $older_transactions = $query->execute();
      $handles = array();
      foreach ($older_transactions as $transaction) {
        $handles += $transaction->getHandles();
      }
      $conpherence->attachHandles($conpherence->getHandles() + $handles);
      $transactions = array_merge($newer_transactions, $older_transactions);
      $conpherence->attachTransactions($transactions);
    } else {
      $transactions = $conpherence->getTransactions();
    }

    return $transactions;
  }

  private function getMainQueryLimit() {
    $request = $this->getRequest();
    $base_limit = ConpherenceThreadQuery::TRANSACTION_LIMIT;
    if ($request->getURIData('messageID')) {
      $base_limit = $base_limit - self::OLDER_FETCH_LIMIT;
    }
    return $base_limit;
  }
}
