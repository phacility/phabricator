<?php

/**
 * @group conpherence
 */
abstract class ConpherenceController extends PhabricatorController {
  private $conpherences;

  /**
   * Try for a full set of unread conpherences, and if we fail
   * load read conpherences. Additional conpherences in either category
   * are loaded asynchronously.
   */
  public function loadStartingConpherences($current_selection_epoch = null) {
    $user = $this->getRequest()->getUser();

    $read_participant_query = id(new ConpherenceParticipantQuery())
      ->withParticipantPHIDs(array($user->getPHID()));
    $read_status =  ConpherenceParticipationStatus::UP_TO_DATE;
    if ($current_selection_epoch) {
      $read_one = $read_participant_query
        ->withParticipationStatus($read_status)
        ->withDateTouched($current_selection_epoch, '>')
        ->execute();

      $read_two = $read_participant_query
        ->withDateTouched($current_selection_epoch, '<=')
        ->execute();

      $read = array_merge($read_one, $read_two);

    } else {
      $read = $read_participant_query
        ->withParticipationStatus($read_status)
        ->execute();
    }

    $unread_status = ConpherenceParticipationStatus::BEHIND;
    $unread = id(new ConpherenceParticipantQuery())
      ->withParticipantPHIDs(array($user->getPHID()))
      ->withParticipationStatus($unread_status)
      ->execute();

    $all_participation = $unread + $read;
    $all_conpherence_phids = array_keys($all_participation);
    $all_conpherences = id(new ConpherenceThreadQuery())
      ->setViewer($user)
      ->withPHIDs($all_conpherence_phids)
      ->needAllTransactions(true)
      ->execute();
    $unread_conpherences = array_select_keys(
      $all_conpherences,
      array_keys($unread));

    $read_conpherences = array_select_keys(
      $all_conpherences,
      array_keys($read));

    return array($unread_conpherences, $read_conpherences);
  }

  public function buildApplicationMenu() {
    $nav = new PhabricatorMenuView();

    $nav->newLink(
      pht('New Conversation'),
      $this->getApplicationURI('new/'));

    return $nav;
  }

  public function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $crumbs
      ->addAction(
        id(new PhabricatorMenuItemView())
          ->setName(pht('New Conversation'))
          ->setHref($this->getApplicationURI('new/'))
          ->setIcon('create'))
      ->addCrumb(
        id(new PhabricatorCrumbView())
          ->setName(pht('Conpherence')));

    return $crumbs;
  }

  protected function buildHeaderPaneContent(ConpherenceThread $conpherence) {
    $user = $this->getRequest()->getUser();
    $display_data = $conpherence->getDisplayData(
      $user,
      ConpherenceImageData::SIZE_HEAD);
    $edit_href = $this->getApplicationURI('update/'.$conpherence->getID().'/');
    $class_mod = $display_data['image_class'];

    return array(
      phutil_tag(
        'div',
        array(
          'class' => 'upload-photo'
        ),
        pht('Drop photo here to change this Conpherence photo.')),
      javelin_tag(
        'a',
        array(
          'class' => 'edit',
          'href' => $edit_href,
          'sigil' => 'conpherence-edit-metadata',
          'meta' => array(
            'action' => 'metadata'
          )
        ),
        ''),
      phutil_tag(
        'div',
        array(
          'class' => $class_mod.'header-image',
          'style' => 'background-image: url('.$display_data['image'].');'
        ),
        ''),
      phutil_tag(
        'div',
        array(
          'class' => $class_mod.'title',
        ),
        $display_data['title']),
      phutil_tag(
        'div',
        array(
          'class' => $class_mod.'subtitle',
        ),
        $display_data['subtitle']),
    );
  }

  protected function renderConpherenceTransactions(
    ConpherenceThread $conpherence) {

    $user = $this->getRequest()->getUser();
    $transactions = $conpherence->getTransactions();
    $handles = $conpherence->getHandles();
    $rendered_transactions = array();
    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($user);
    foreach ($transactions as $key => $transaction) {
      if ($transaction->shouldHide()) {
        unset($transactions[$key]);
        continue;
      }
      if ($transaction->getComment()) {
        $engine->addObject(
          $transaction->getComment(),
          PhabricatorApplicationTransactionComment::MARKUP_FIELD_COMMENT);
      }
    }
    $engine->process();
    foreach ($transactions as $transaction) {
      $rendered_transactions[] = id(new ConpherenceTransactionView())
        ->setUser($user)
        ->setConpherenceTransaction($transaction)
        ->setHandles($handles)
        ->setMarkupEngine($engine)
        ->render();
    }
    $latest_transaction_id = $transaction->getID();
    $rendered_transactions = phutil_implode_html(' ', $rendered_transactions);

    return array(
      'transactions' => $rendered_transactions,
      'latest_transaction_id' => $latest_transaction_id
    );

  }
}
