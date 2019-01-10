<?php

final class DiffusionPushLogListView extends AphrontView {

  private $logs;

  public function setLogs(array $logs) {
    assert_instances_of($logs, 'PhabricatorRepositoryPushLog');
    $this->logs = $logs;
    return $this;
  }

  public function render() {
    $logs = $this->logs;
    $viewer = $this->getViewer();

    $reject_herald = PhabricatorRepositoryPushLog::REJECT_HERALD;

    $handle_phids = array();
    foreach ($logs as $log) {
      $handle_phids[] = $log->getPusherPHID();
      $device_phid = $log->getDevicePHID();
      if ($device_phid) {
        $handle_phids[] = $device_phid;
      }

      if ($log->getPushEvent()->getRejectCode() == $reject_herald) {
        $handle_phids[] = $log->getPushEvent()->getRejectDetails();
      }
    }

    $viewer->loadHandles($handle_phids);

    // Only administrators can view remote addresses.
    $remotes_visible = $viewer->getIsAdmin();

    $flag_map = PhabricatorRepositoryPushLog::getFlagDisplayNames();
    $reject_map = PhabricatorRepositoryPushLog::getRejectCodeDisplayNames();

    $rows = array();
    $any_host = false;
    foreach ($logs as $log) {
      $repository = $log->getRepository();
      $event = $log->getPushEvent();

      if ($remotes_visible) {
        $remote_address = $event->getRemoteAddress();
      } else {
        $remote_address = null;
      }

      $event_id = $log->getPushEvent()->getID();

      $old_ref_link = null;
      if ($log->getRefOld() != DiffusionCommitHookEngine::EMPTY_HASH) {
        $old_ref_link = phutil_tag(
          'a',
          array(
            'href' => $repository->getCommitURI($log->getRefOld()),
          ),
          $log->getRefOldShort());
      }

      $device_phid = $log->getDevicePHID();
      if ($device_phid) {
        $device = $viewer->renderHandle($device_phid);
        $any_host = true;
      } else {
        $device = null;
      }

      $flags = $log->getChangeFlags();
      $flag_names = array();
      foreach ($flag_map as $flag_key => $flag_name) {
        if (($flags & $flag_key) === $flag_key) {
          $flag_names[] = $flag_name;
        }
      }
      $flag_names = phutil_implode_html(
        phutil_tag('br'),
        $flag_names);

      $reject_code = $event->getRejectCode();

      if ($reject_code == $reject_herald) {
        $rule_phid = $event->getRejectDetails();
        $handle = $viewer->renderHandle($rule_phid);
        $reject_label = pht('Blocked: %s', $handle);
      } else {
        $reject_label = idx(
          $reject_map,
          $reject_code,
          pht('Unknown ("%s")', $reject_code));
      }

      $host_wait = $this->formatMicroseconds($event->getHostWait());
      $write_wait = $this->formatMicroseconds($event->getWriteWait());
      $read_wait = $this->formatMicroseconds($event->getReadWait());
      $hook_wait = $this->formatMicroseconds($event->getHookWait());

      $rows[] = array(
        phutil_tag(
          'a',
          array(
            'href' => '/diffusion/pushlog/view/'.$event_id.'/',
          ),
          $event_id),
        phutil_tag(
          'a',
          array(
            'href' => $repository->getURI(),
          ),
          $repository->getDisplayName()),
        $viewer->renderHandle($log->getPusherPHID()),
        $remote_address,
        $event->getRemoteProtocol(),
        $device,
        $log->getRefType(),
        $log->getRefName(),
        $old_ref_link,
        phutil_tag(
          'a',
          array(
            'href' => $repository->getCommitURI($log->getRefNew()),
          ),
          $log->getRefNewShort()),
        $flag_names,
        $reject_label,
        $viewer->formatShortDateTime($log->getEpoch()),
        $host_wait,
        $write_wait,
        $read_wait,
        $hook_wait,
      );
    }

    $table = id(new AphrontTableView($rows))
      ->setHeaders(
        array(
          pht('Push'),
          pht('Repository'),
          pht('Pusher'),
          pht('From'),
          pht('Via'),
          pht('Host'),
          pht('Type'),
          pht('Name'),
          pht('Old'),
          pht('New'),
          pht('Flags'),
          pht('Result'),
          pht('Date'),
          pht('Host Wait'),
          pht('Write Wait'),
          pht('Read Wait'),
          pht('Hook Wait'),
        ))
      ->setColumnClasses(
        array(
          '',
          '',
          '',
          '',
          '',
          '',
          '',
          'wide',
          'n',
          'n',
          '',
          '',
          'right',
          'n right',
          'n right',
          'n right',
          'n right',
        ))
      ->setColumnVisibility(
        array(
          true,
          true,
          true,
          $remotes_visible,
          true,
          $any_host,
        ));

    return $table;
  }

  private function formatMicroseconds($duration) {
    if ($duration === null) {
      return null;
    }

    return pht('%sus', new PhutilNumber($duration));
  }

}
