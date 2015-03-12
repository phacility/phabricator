<?php

final class ConpherenceViewController extends
  ConpherenceController {

  public function handleRequest(AphrontRequest $request) {
    $user = $request->getUser();

    $conpherence_id = $request->getURIData('id');
    if (!$conpherence_id) {
      return new Aphront404Response();
    }
    $query = id(new ConpherenceThreadQuery())
      ->setViewer($user)
      ->withIDs(array($conpherence_id))
      ->needParticipantCache(true)
      ->needTransactions(true)
      ->setTransactionLimit(ConpherenceThreadQuery::TRANSACTION_LIMIT);
    $before_transaction_id = $request->getInt('oldest_transaction_id');
    if ($before_transaction_id) {
      $query
        ->setBeforeTransactionID($before_transaction_id);
    }
    $conpherence = $query->executeOne();
    if (!$conpherence) {
      return new Aphront404Response();
    }
    $this->setConpherence($conpherence);

    $participant = $conpherence->getParticipant($user->getPHID());
    $transactions = $conpherence->getTransactions();
    $latest_transaction = head($transactions);
    $write_guard = AphrontWriteGuard::beginScopedUnguardedWrites();
    $participant->markUpToDate($conpherence, $latest_transaction);
    unset($write_guard);

    $data = ConpherenceTransactionView::renderTransactions(
      $user,
      $conpherence);
    $messages = ConpherenceTransactionView::renderMessagePaneContent(
      $data['transactions'],
      $data['oldest_transaction_id']);
    if ($before_transaction_id) {
      $header = null;
      $form = null;
      $content = array('messages' => $messages);
    } else {
      $header = $this->buildHeaderPaneContent($conpherence);
      $form = $this->renderFormContent();
      $content = array(
        'header' => $header,
        'messages' => $messages,
        'form' => $form,
      );
    }

    $title = $this->getConpherenceTitle($conpherence);
    $content['title'] = $title;

    if ($request->isAjax()) {
      return id(new AphrontAjaxResponse())->setContent($content);
    }

    $layout = id(new ConpherenceLayoutView())
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
    $draft = PhabricatorDraft::newFromUserAndKey(
      $user,
      $conpherence->getPHID());
    $update_uri = $this->getApplicationURI('update/'.$conpherence->getID().'/');

    $this->initBehavior('conpherence-pontificate');

    $form =
      id(new AphrontFormView())
      ->setAction($update_uri)
      ->addSigil('conpherence-pontificate')
      ->setWorkflow(true)
      ->setUser($user)
      ->addHiddenInput('action', 'message')
      ->appendChild(
        id(new PhabricatorRemarkupControl())
        ->setUser($user)
        ->setName('text')
        ->setValue($draft->getDraft()))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Send')))
      ->render();

    return $form;
  }


}
