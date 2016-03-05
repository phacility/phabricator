<?php

final class HarbormasterUnitPropertyView extends AphrontView {

  private $pathURIMap = array();
  private $unitMessages = array();
  private $limit;
  private $fullResultsURI;
  private $notice;

  public function setPathURIMap(array $map) {
    $this->pathURIMap = $map;
    return $this;
  }

  public function setUnitMessages(array $messages) {
    assert_instances_of($messages, 'HarbormasterBuildUnitMessage');
    $this->unitMessages = $messages;
    return $this;
  }

  public function setLimit($limit) {
    $this->limit = $limit;
    return $this;
  }

  public function setFullResultsURI($full_results_uri) {
    $this->fullResultsURI = $full_results_uri;
    return $this;
  }

  public function setNotice($notice) {
    $this->notice = $notice;
    return $this;
  }


  public function render() {
    require_celerity_resource('harbormaster-css');

    $messages = $this->unitMessages;
    $messages = msort($messages, 'getSortKey');

    $limit = $this->limit;

    if ($this->limit) {
      $display_messages = array_slice($messages, 0, $limit);
    } else {
      $display_messages = $messages;
    }

    $rows = array();
    $any_duration = false;
    foreach ($display_messages as $message) {
      $status = $message->getResult();

      $icon_icon = HarbormasterUnitStatus::getUnitStatusIcon($status);
      $icon_color = HarbormasterUnitStatus::getUnitStatusColor($status);
      $icon_label = HarbormasterUnitStatus::getUnitStatusLabel($status);

      $result_icon = id(new PHUIIconView())
        ->setIcon("{$icon_icon} {$icon_color}")
        ->addSigil('has-tooltip')
        ->setMetadata(
          array(
            'tip' => $icon_label,
          ));

      $duration = $message->getDuration();
      if ($duration !== null) {
        $any_duration = true;
        $duration = pht('%s ms', new PhutilNumber((int)(1000 * $duration)));
      }

      $name = $message->getUnitMessageDisplayName();
      $id = $message->getID();

      if ($id) {
        $name = phutil_tag(
          'a',
          array(
            'href' => "/harbormaster/unit/view/{$id}/",
          ),
          $name);
      }

      $details = $message->getUnitMessageDetails();
      if (strlen($details)) {
        $name = array(
          $name,
          $this->renderUnitTestDetails($details),
        );
      }

      $rows[] = array(
        $result_icon,
        $duration,
        $name,
      );
    }

    $full_uri = $this->fullResultsURI;
    if ($full_uri && (count($messages) > $limit)) {
      $counts = array();

      $groups = mgroup($messages, 'getResult');
      foreach ($groups as $status => $group) {
        $counts[] = HarbormasterUnitStatus::getUnitStatusCountLabel(
          $status,
          count($group));
      }

      $link_text = pht(
        'View Full Test Results (%s)',
        implode(" \xC2\xB7 ", $counts));

      $full_link = phutil_tag(
        'a',
        array(
          'href' => $full_uri,
        ),
        $link_text);

      $link_icon = id(new PHUIIconView())
        ->setIcon('fa-ellipsis-h lightgreytext');

      $rows[] = array($link_icon, null, $full_link);
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          null,
          pht('Time'),
          pht('Test'),
        ))
      ->setColumnClasses(
        array(
          'top center',
          'top right',
          'top wide',
        ))
      ->setColumnWidths(
        array(
          '32px',
          '64px',
        ))
      ->setColumnVisibility(
        array(
          true,
          $any_duration,
        ));

    if ($this->notice) {
      $table->setNotice($this->notice);
    }

    return $table;
  }

  private function renderUnitTestDetails($full_details) {
    $details = id(new PhutilUTF8StringTruncator())
      ->setMaximumBytes(2048)
      ->truncateString($full_details);
    $details = phutil_split_lines($details);

    $limit = 3;
    if (count($details) > $limit) {
      $details = array_slice($details, 0, $limit);
    }

    $details = implode('', $details);

    return phutil_tag(
      'div',
      array(
        'class' => 'PhabricatorMonospaced harbormaster-unit-details',
      ),
      $details);
  }

}
