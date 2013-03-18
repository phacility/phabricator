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
    require_celerity_resource('sprite-conpherence-css');
    $can_toggle = 1;
    $cant_toggle = 0;
    Javelin::initBehavior(
      'conpherence-widget-pane',
      array(
        'form_pane' => 'conpherence-form',
        'file_widget' => 'widgets-files',
        'widgetRegistery' => array(
          'widgets-conpherence-list' => $cant_toggle,
          'widgets-conversation' => $cant_toggle,
          'widgets-people' => $can_toggle,
          'widgets-files' => $can_toggle,
          'widgets-calendar' => $can_toggle,
          'widgets-settings' => $can_toggle,
        )
      ));

    $conpherence = $this->getConpherence();

    $widgets = phutil_tag(
      'div',
      array(
        'class' => 'widgets-header'
      ),
      phutil_tag(
        'div',
        array(
          'class' => 'widgets-header-icon-holder'
        ),
        array(
          javelin_tag(
            'a',
            array(
              'sigil' => 'conpherence-change-widget',
              'meta'  => array(
                'widget' => 'widgets-conpherence-list',
                'toggleClass' => 'conpherence_list_on'
              ),
              'id' => 'widgets-conpherence-list-toggle',
              'class' => 'sprite-conpherence conpherence_list_off',
            ),
            ''),
          javelin_tag(
            'a',
            array(
              'sigil' => 'conpherence-change-widget',
              'meta'  => array(
                'widget' => 'widgets-conversation',
                'toggleClass' => 'conpherence_conversation_on'
              ),
              'id' => 'widgets-conpherence-conversation-toggle',
              'class' => 'sprite-conpherence conpherence_conversation_off',
            ),
            ''),
          javelin_tag(
            'a',
            array(
              'sigil' => 'conpherence-change-widget',
              'meta'  => array(
                'widget' => 'widgets-people',
                'toggleClass' => 'conpherence_people_on'
              ),
              'id' => 'widgets-people-toggle',
              'class' => 'sprite-conpherence conpherence_people_off'
            ),
            ''),
          javelin_tag(
            'a',
            array(
              'sigil' => 'conpherence-change-widget',
              'meta'  => array(
                'widget' => 'widgets-files',
                'toggleClass' => 'conpherence_files_on'
              ),
              'id' => 'widgets-files-toggle',
              'class' =>
              'sprite-conpherence conpherence_files_on conpherence_files_off'
            ),
            ''),
          javelin_tag(
            'a',
            array(
              'sigil' => 'conpherence-change-widget',
              'meta'  => array(
                'widget' => 'widgets-calendar',
                'toggleClass' => 'conpherence_calendar_on'
              ),
              'id' => 'widgets-calendar-toggle',
              'class' => 'sprite-conpherence conpherence_calendar_off',
            ),
            ''),
          javelin_tag(
            'a',
            array(
              'sigil' => 'conpherence-change-widget',
              'meta'  => array(
                'widget' => 'widgets-settings',
                'toggleClass' => 'conpherence_settings_on'
              ),
              'id' => 'widgets-settings-toggle',
              'class' => 'sprite-conpherence conpherence_settings_off',
            ),
            '')
        ))).
    phutil_tag(
      'div',
      array(
        'class' => 'widgets-body',
        'id' => 'widgets-people',
        'style' => 'display: none;'
      ),
      $this->renderPeopleWidgetPaneContent()).
      phutil_tag(
        'div',
        array(
          'class' => 'widgets-body',
          'id' => 'widgets-files',
        ),
        id(new ConpherenceFileWidgetView())
        ->setUser($this->getRequest()->getUser())
        ->setConpherence($conpherence)
        ->setUpdateURI(
          $this->getApplicationURI('update/'.$conpherence->getID().'/'))
          ->render()).
          phutil_tag(
            'div',
            array(
              'class' => 'widgets-body',
              'id' => 'widgets-calendar',
              'style' => 'display: none;'
            ),
            $this->renderCalendarWidgetPaneContent()).
            phutil_tag(
              'div',
              array(
                'class' => 'widgets-body',
                'id' => 'widgets-settings',
                'style' => 'display: none'
              ),
              $this->renderSettingsWidgetPaneContent());

    return array('widgets' => $widgets);
  }

  private function renderPeopleWidgetPaneContent() {
    return 'TODO - people';
  }

  private function renderSettingsWidgetPaneContent() {
    return 'TODO - settings';
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
