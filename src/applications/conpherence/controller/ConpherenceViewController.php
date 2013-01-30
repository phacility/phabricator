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
    if (!$request->isAjax()) {
      return id(new AphrontRedirectResponse())
        ->setURI($this->getApplicationURI($conpherence_id.'/'));
    }
    $conpherence = id(new ConpherenceThreadQuery())
      ->setViewer($user)
      ->withIDs(array($conpherence_id))
      ->needWidgetData(true)
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
    $widgets = $this->renderWidgetPaneContent();
    $content = $header + $widgets + $messages;

    return id(new AphrontAjaxResponse())->setContent($content);
  }

  private function renderHeaderPaneContent() {
    require_celerity_resource('conpherence-header-pane-css');
    $user = $this->getRequest()->getUser();
    $conpherence = $this->getConpherence();
    $display_data = $conpherence->getDisplayData($user);
    $edit_href = $this->getApplicationURI('update/'.$conpherence->getID().'/');

    $header =
    javelin_render_tag(
      'a',
      array(
        'class' => 'edit',
        'href' => $edit_href,
        'sigil' => 'workflow',
      ),
      ''
    ).
    phutil_render_tag(
      'div',
      array(
        'class' => 'header-image',
        'style' => 'background-image: url('.$display_data['image'].');'
      ),
      ''
    ).
    phutil_render_tag(
      'div',
      array(
        'class' => 'title',
      ),
      phutil_escape_html($display_data['title'])
    ).
    phutil_render_tag(
      'div',
      array(
        'class' => 'subtitle',
      ),
      phutil_escape_html($display_data['subtitle'])
    );

    return array('header' => $header);
  }

  private function renderMessagePaneContent() {
    require_celerity_resource('conpherence-message-pane-css');
    $user = $this->getRequest()->getUser();
    $conpherence = $this->getConpherence();
    $handles = $conpherence->getHandles();
    $rendered_transactions = array();

    $transactions = $conpherence->getTransactions();
    foreach ($transactions as $transaction) {
      if ($transaction->shouldHide()) {
        continue;
      }
      $rendered_transactions[] = id(new ConpherenceTransactionView())
        ->setUser($user)
        ->setConpherenceTransaction($transaction)
        ->setHandles($handles)
        ->render();
    }
    $transactions = implode(' ', $rendered_transactions);

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
        ->setName('text')
      )
      ->appendChild(
        id(new AphrontFormSubmitControl())
        ->setValue(pht('Pontificate'))
      )->render();

    return array(
      'messages' => $transactions,
      'form' => $form
    );

  }

  private function renderWidgetPaneContent() {
    require_celerity_resource('conpherence-widget-pane-css');
    Javelin::initBehavior(
      'conpherence-widget-pane',
      array(
        'widgetRegistery' => array(
          'widgets-files' => 1,
          'widgets-tasks' => 1,
          'widgets-calendar' => 1,
        )
      )
    );

    $conpherence = $this->getConpherence();

    $widgets = phutil_render_tag(
      'div',
      array(
        'class' => 'widgets-header'
      ),
      javelin_render_tag(
        'a',
        array(
          'sigil' => 'conpherence-change-widget',
          'meta'  => array('widget' => 'widgets-files')
        ),
        pht('Files')
      ).' | '.
      javelin_render_tag(
        'a',
        array(
          'sigil' => 'conpherence-change-widget',
          'meta'  => array('widget' => 'widgets-tasks')
        ),
        pht('Tasks')
      ).' | '.
      javelin_render_tag(
        'a',
        array(
          'sigil' => 'conpherence-change-widget',
          'meta'  => array('widget' => 'widgets-calendar')
        ),
        pht('Calendar')
      )
    ).
    phutil_render_tag(
      'div',
      array(
        'class' => 'widgets-body',
        'id' => 'widgets-files',
        'style' => 'display: none;'
      ),
      $this->renderFilesWidgetPaneContent()
    ).
    phutil_render_tag(
      'div',
      array(
        'class' => 'widgets-body',
        'id' => 'widgets-tasks',
      ),
      $this->renderTaskWidgetPaneContent()
    ).
    phutil_render_tag(
      'div',
      array(
        'class' => 'widgets-body',
        'id' => 'widgets-calendar',
        'style' => 'display: none;'
      ),
      $this->renderCalendarWidgetPaneContent()
    );

    return array('widgets' => $widgets);
  }

  private function renderFilesWidgetPaneContent() {
    $conpherence = $this->getConpherence();
    $widget_data = $conpherence->getWidgetData();
    $files = $widget_data['files'];

    $table_data = array();
    foreach ($files as $file) {
      $thumb = $file->getThumb60x45URI();
      $table_data[] = array(
        phutil_render_tag(
          'img',
          array(
            'src' => $thumb
          ),
          ''
        ),
        $file->getName()
      );
    }
    $header = id(new PhabricatorHeaderView())
      ->setHeader(pht('Attached Files'));
    $table = id(new AphrontTableView($table_data))
        ->setNoDataString(pht('No files attached to conpherence.'))
        ->setHeaders(array('', pht('Name')))
        ->setColumnClasses(array('', 'wide'));
    return $header->render() . $table->render();
  }

  private function renderTaskWidgetPaneContent() {
    $conpherence = $this->getConpherence();
    $widget_data = $conpherence->getWidgetData();
    $tasks = $widget_data['tasks'];
    $priority_map = ManiphestTaskPriority::getTaskPriorityMap();
    $handles = $conpherence->getHandles();
    $content = array();
    foreach ($tasks as $owner_phid => $actual_tasks) {
      $handle = $handles[$owner_phid];
      $content[] = id(new PhabricatorHeaderView())
        ->setHeader($handle->getName())
        ->render();
      $actual_tasks = msort($actual_tasks, 'getPriority');
      $actual_tasks = array_reverse($actual_tasks);
      $data = array();
      foreach ($actual_tasks as $task) {
        $data[] = array(
          idx($priority_map, $task->getPriority(), pht('???')),
          phutil_render_tag(
            'a',
            array(
              'href' => '/T'.$task->getID()
            ),
            phutil_escape_html($task->getTitle())
          )
        );
      }
      $table = id(new AphrontTableView($data))
        ->setNoDataString(pht('No open tasks.'))
        ->setHeaders(array(pht('Pri'), pht('Title')))
        ->setColumnClasses(array('', 'wide'));
      $content[] = $table->render();
    }
    return implode('', $content);
  }

  private function renderCalendarWidgetPaneContent() {
    $header = id(new PhabricatorHeaderView())
      ->setHeader(pht('Calendar'));
    return $header->render() . 'TODO';
  }

}
