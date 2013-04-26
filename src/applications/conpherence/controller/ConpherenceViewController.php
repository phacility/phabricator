<?php

/**
 * @group conpherence
 */
final class ConpherenceViewController extends
  ConpherenceController {

  private $conpherenceID;
  private $conpherence;

  public function setConpherence(ConpherenceThread $conpherence) {
    $this->conpherence = $conpherence;
    return $this;
  }
  public function getConpherence() {
    return $this->conpherence;
  }

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
    $query = id(new ConpherenceThreadQuery())
      ->setViewer($user)
      ->withIDs(array($conpherence_id))
      ->needHeaderPics(true)
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
    $latest_transaction = end($transactions);
    $write_guard = AphrontWriteGuard::beginScopedUnguardedWrites();
    $participant->markUpToDate($conpherence, $latest_transaction);
    unset($write_guard);

    $data = $this->renderConpherenceTransactions($conpherence);
    $messages = $this->renderMessagePaneContent(
      $data['transactions'],
      $data['oldest_transaction_id']);
    if ($before_transaction_id) {
      $header = null;
      $form = null;
      $content = array('messages' => $messages);
    } else {
      $header = $this->renderHeaderPaneContent();
      $form = $this->renderFormContent($data['latest_transaction_id']);
      $content = array(
        'header' => $header,
        'messages' => $messages,
        'form' => $form
      );
    }

    if ($request->isAjax()) {
      return id(new AphrontAjaxResponse())->setContent($content);
    }

    $layout = id(new ConpherenceLayoutView())
      ->setBaseURI($this->getApplicationURI())
      ->setThread($conpherence)
      ->setHeader($header)
      ->setMessages($messages)
      ->setReplyForm($form)
      ->setRole('thread');

    return $this->buildApplicationPage(
      $layout,
      array(
        'title' => $conpherence->getTitle(),
        'device' => true,
      ));
  }

  private function renderHeaderPaneContent() {
    require_celerity_resource('conpherence-header-pane-css');
    $conpherence = $this->getConpherence();
    $header = $this->buildHeaderPaneContent($conpherence);
    return hsprintf('%s', $header);
  }


  private function renderMessagePaneContent(
    array $transactions,
    $oldest_transaction_id) {

    require_celerity_resource('conpherence-message-pane-css');

    $scrollbutton = '';
    if ($oldest_transaction_id) {
      $scrollbutton = javelin_tag(
        'a',
        array(
          'href' => '#',
          'mustcapture' => true,
          'sigil' => 'show-older-messages',
          'class' => 'conpherence-show-older-messages',
          'meta' => array(
            'oldest_transaction_id' => $oldest_transaction_id
          )
        ),
        pht('Show Older Messages'));
    }

    return hsprintf('%s%s', $scrollbutton, $transactions);
  }

  private function renderFormContent($latest_transaction_id) {

    $conpherence = $this->getConpherence();
    $user = $this->getRequest()->getUser();
    $update_uri = $this->getApplicationURI('update/'.$conpherence->getID().'/');

    Javelin::initBehavior('conpherence-pontificate');

    $form =
      id(new AphrontFormView())
      ->setAction($update_uri)
      ->setFlexible(true)
      ->addSigil('conpherence-pontificate')
      ->setWorkflow(true)
      ->setUser($user)
      ->addHiddenInput('action', 'message')
      ->appendChild(
        id(new PhabricatorRemarkupControl())
        ->setUser($user)
        ->setName('text'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Pontificate')))
      ->appendChild(
        javelin_tag(
          'input',
          array(
            'type' => 'hidden',
            'name' => 'latest_transaction_id',
            'value' => $latest_transaction_id,
            'sigil' => 'latest-transaction-id',
            'meta' => array(
              'id' => $latest_transaction_id
            )
          ),
        ''))
      ->render();

    return $form;
  }

}
