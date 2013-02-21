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
    $conpherence = id(new ConpherenceThreadQuery())
      ->setViewer($user)
      ->withIDs(array($conpherence_id))
      ->needHeaderPics(true)
      ->executeOne();
    $this->setConpherence($conpherence);

    $participant = $conpherence->getParticipant($user->getPHID());
    $transactions = $conpherence->getTransactions();
    $latest_transaction = end($transactions);
    $write_guard = AphrontWriteGuard::beginScopedUnguardedWrites();
    $participant->markUpToDate($latest_transaction);
    unset($write_guard);

    $header = $this->renderHeaderPaneContent();
    $messages = $this->renderMessagePaneContent();
    $content = $header + $messages;
    return id(new AphrontAjaxResponse())->setContent($content);
  }

  private function renderHeaderPaneContent() {
    require_celerity_resource('conpherence-header-pane-css');
    $user = $this->getRequest()->getUser();
    $conpherence = $this->getConpherence();
    $display_data = $conpherence->getDisplayData(
      $user,
      ConpherenceImageData::SIZE_HEAD);
    $edit_href = $this->getApplicationURI('update/'.$conpherence->getID().'/');
    $class_mod = $display_data['image_class'];

    $header =
    phutil_tag(
      'div',
      array(
        'class' => 'upload-photo'
      ),
      pht('Drop photo here to change this Conpherence photo.')).
    javelin_tag(
      'a',
      array(
        'class' => 'edit',
        'href' => $edit_href,
        'sigil' => 'workflow edit-action',
      ),
      '').
    phutil_tag(
      'div',
      array(
        'class' => $class_mod.'header-image',
        'style' => 'background-image: url('.$display_data['image'].');'
      ),
      '').
    phutil_tag(
      'div',
      array(
        'class' => $class_mod.'title',
      ),
      $display_data['title']).
    phutil_tag(
      'div',
      array(
        'class' => $class_mod.'subtitle',
      ),
      $display_data['subtitle']);

    return array('header' => $header);
  }

  private function renderMessagePaneContent() {
    require_celerity_resource('conpherence-message-pane-css');
    $user = $this->getRequest()->getUser();
    $conpherence = $this->getConpherence();
    $handles = $conpherence->getHandles();
    $rendered_transactions = array();


    $transactions = $conpherence->getTransactionsFrom(0, 100);

    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($user);
    foreach ($transactions as $transaction) {
      if ($transaction->getComment()) {
        $engine->addObject(
          $transaction->getComment(),
          PhabricatorApplicationTransactionComment::MARKUP_FIELD_COMMENT);
      }
    }
    $engine->process();
    foreach ($transactions as $transaction) {
      if ($transaction->shouldHide()) {
        continue;
      }
      $rendered_transactions[] = id(new ConpherenceTransactionView())
        ->setUser($user)
        ->setConpherenceTransaction($transaction)
        ->setHandles($handles)
        ->setMarkupEngine($engine)
        ->render();
    }
    $transactions = phutil_implode_html(' ', $rendered_transactions);

    $form =
      id(new AphrontFormView())
      ->setWorkflow(true)
      ->setAction($this->getApplicationURI('update/'.$conpherence->getID().'/'))
      ->setFlexible(true)
      ->setUser($user)
      ->addHiddenInput('action', 'message')
      ->appendChild(
        id(new PhabricatorRemarkupControl())
        ->setUser($user)
        ->setName('text'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
        ->setValue(pht('Pontificate')))->render();

    $scrollbutton = javelin_tag(
      'a',
      array(
        'href' => '#',
        'mustcapture' => true,
        'sigil' => 'show-older-messages',
        'class' => 'conpherence-show-older-messages',
      ),
      pht('Show Older Messages'));

    return array(
      'messages' => $scrollbutton.$transactions,
      'form' => $form
    );

  }

}
