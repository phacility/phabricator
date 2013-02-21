<?php

/**
 * @group conpherence
 */
final class ConpherenceWidgetController extends
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
      ->needWidgetData(true)
      ->executeOne();
    $this->setConpherence($conpherence);

    $widgets = $this->renderWidgetPaneContent();
    $content = $widgets;
    return id(new AphrontAjaxResponse())->setContent($content);
  }

  private function renderWidgetPaneContent() {
    require_celerity_resource('conpherence-widget-pane-css');
    require_celerity_resource('sprite-conpher-css');
    Javelin::initBehavior(
      'conpherence-widget-pane',
      array(
        'widgetRegistery' => array(
          'widgets-files' => 1,
          'widgets-tasks' => 1,
          'widgets-calendar' => 1,
        )
      ));

    $conpherence = $this->getConpherence();

    $widgets = phutil_tag(
      'div',
      array(
        'class' => 'widgets-header'
      ),
      array(
        javelin_tag(
          'a',
          array(
            'sigil' => 'conpherence-change-widget',
            'meta'  => array(
              'widget' => 'widgets-files',
              'toggleClass' => 'conpher_files_on'
            ),
            'id' => 'widgets-files-toggle',
            'class' => 'sprite-conpher conpher_files_off first-icon'
          ),
          ''),
        javelin_tag(
          'a',
          array(
            'sigil' => 'conpherence-change-widget',
            'meta'  => array(
              'widget' => 'widgets-tasks',
              'toggleClass' => 'conpher_list_on'
            ),
            'id' => 'widgets-tasks-toggle',
            'class' => 'sprite-conpher conpher_list_off conpher_list_on',
          ),
          ''),
        javelin_tag(
          'a',
          array(
            'sigil' => 'conpherence-change-widget',
            'meta'  => array(
              'widget' => 'widgets-calendar',
              'toggleClass' => 'conpher_calendar_on'
            ),
            'id' => 'widgets-calendar-toggle',
            'class' => 'sprite-conpher conpher_calendar_off',
          ),
          '')
      )).
    phutil_tag(
      'div',
      array(
        'class' => 'widgets-body',
        'id' => 'widgets-files',
        'style' => 'display: none;'
      ),
      $this->renderFilesWidgetPaneContent()).
    phutil_tag(
      'div',
      array(
        'class' => 'widgets-body',
        'id' => 'widgets-tasks',
      ),
      $this->renderTaskWidgetPaneContent()).
    phutil_tag(
      'div',
      array(
        'class' => 'widgets-body',
        'id' => 'widgets-calendar',
        'style' => 'display: none;'
      ),
      $this->renderCalendarWidgetPaneContent());

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
        phutil_tag(
          'img',
          array(
            'src' => $thumb
          ),
          ''),
        $file->getName()
      );
    }
    $header = id(new PhabricatorHeaderView())
      ->setHeader(pht('Attached Files'));
    $table = id(new AphrontTableView($table_data))
        ->setNoDataString(pht('No files attached to conpherence.'))
        ->setHeaders(array('', pht('Name')))
        ->setColumnClasses(array('', 'wide'));
    return new PhutilSafeHTML($header->render() . $table->render());
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
          phutil_tag(
            'a',
            array(
              'href' => '/T'.$task->getID()
            ),
            $task->getTitle())
        );
      }
      $table = id(new AphrontTableView($data))
        ->setNoDataString(pht('No open tasks.'))
        ->setHeaders(array(pht('Pri'), pht('Title')))
        ->setColumnClasses(array('', 'wide'));
      $content[] = $table->render();
    }
    return new PhutilSafeHTML(implode('', $content));
  }

  private function renderCalendarWidgetPaneContent() {
    $user = $this->getRequest()->getUser();

    $conpherence = $this->getConpherence();
    $widget_data = $conpherence->getWidgetData();
    $statuses = $widget_data['statuses'];
    $handles = $conpherence->getHandles();
    $content = array();
    $timestamps = $this->getCalendarWidgetWeekTimestamps();
    $one_day = 24 * 60 * 60;
    foreach ($timestamps as $time => $day) {
      // build a header for the new day
      $content[] = id(new PhabricatorHeaderView())
        ->setHeader($day->format('l'))
        ->render();

      $day->setTime(0, 0, 0);
      $epoch_start = $day->format('U');
      $day->modify('+1 day');
      $epoch_end = $day->format('U');

      // keep looking through statuses where we last left off
      foreach ($statuses as $status) {
        if ($status->getDateFrom() >= $epoch_end) {
          // This list is sorted, so we can stop looking.
          break;
        }
        if ($status->getDateFrom() < $epoch_end &&
            $status->getDateTo() > $epoch_start) {
          $timespan = $status->getDateTo() - $status->getDateFrom();
          if ($timespan > $one_day) {
            $time_str = 'm/d';
          } else {
            $time_str = 'h:i A';
          }
          $epoch_range = phabricator_format_local_time(
            $status->getDateFrom(),
            $user,
            $time_str) . ' - ' . phabricator_format_local_time(
            $status->getDateTo(),
            $user,
            $time_str);

          $content[] = phutil_tag(
            'div',
            array(
              'class' => 'user-status '.$status->getTextStatus(),
            ),
            array(
              phutil_tag(
                'div',
                array(
                  'class' => 'epoch-range'
                ),
              $epoch_range),
              phutil_tag(
                'div',
                array(
                  'class' => 'icon',
                ),
                ''),
              phutil_tag(
                'div',
                array(
                  'class' => 'description'
                ),
                $status->getTerseSummary($user)),
              phutil_tag(
                'div',
                array(
                  'class' => 'participant'
                ),
                $handles[$status->getUserPHID()]->getName())
            ));
        }
      }
    }

    return new PhutilSafeHTML(implode('', $content));
  }

  private function getCalendarWidgetWeekTimestamps() {
    $user = $this->getRequest()->getUser();
    $timezone = new DateTimeZone($user->getTimezoneIdentifier());

    $timestamps = array();
    for ($day = 0; $day < 7; $day++) {
      $timestamps[] = new DateTime(
        sprintf('today +%d days', $day),
        $timezone
      );
    }

    return $timestamps;
  }

}
