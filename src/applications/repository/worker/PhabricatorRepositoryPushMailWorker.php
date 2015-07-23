<?php

final class PhabricatorRepositoryPushMailWorker
  extends PhabricatorWorker {

  protected function doWork() {
    $viewer = PhabricatorUser::getOmnipotentUser();

    $task_data = $this->getTaskData();

    $email_phids = idx($task_data, 'emailPHIDs');
    if (!$email_phids) {
      // If we don't have any email targets, don't send any email.
      return;
    }

    $event_phid = idx($task_data, 'eventPHID');
    $event = id(new PhabricatorRepositoryPushEventQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($event_phid))
      ->needLogs(true)
      ->executeOne();

    $repository = $event->getRepository();
    if (!$repository->shouldPublish()) {
      // If the repository is still importing, don't send email.
      return;
    }

    $targets = id(new PhabricatorRepositoryPushReplyHandler())
      ->setMailReceiver($repository)
      ->getMailTargets($email_phids, array());

    $messages = array();
    foreach ($targets as $target) {
      $messages[] = $this->sendMail($target, $repository, $event);
    }

    foreach ($messages as $message) {
      $message->save();
    }
  }

  private function sendMail(
    PhabricatorMailTarget $target,
    PhabricatorRepository $repository,
    PhabricatorRepositoryPushEvent $event) {

    $task_data = $this->getTaskData();
    $viewer = $target->getViewer();

    $locale = PhabricatorEnv::beginScopedLocale($viewer->getTranslation());

    $logs = $event->getLogs();

    list($ref_lines, $ref_list) = $this->renderRefs($logs);
    list($commit_lines, $subject_line) = $this->renderCommits(
      $repository,
      $logs,
      idx($task_data, 'info', array()));

    $ref_count = count($ref_lines);
    $commit_count = count($commit_lines);

    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($event->getPusherPHID()))
      ->execute();

    $pusher_name = $handles[$event->getPusherPHID()]->getName();
    $repo_name = $repository->getMonogram();

    if ($commit_count) {
      $overview = pht(
        '%s pushed %d commit(s) to %s.',
        $pusher_name,
        $commit_count,
        $repo_name);
    } else {
      $overview = pht(
        '%s pushed to %s.',
        $pusher_name,
        $repo_name);
    }

    $details_uri = PhabricatorEnv::getProductionURI(
      '/diffusion/pushlog/view/'.$event->getID().'/');

    $body = new PhabricatorMetaMTAMailBody();
    $body->addRawSection($overview);

    $body->addLinkSection(pht('DETAILS'), $details_uri);

    if ($commit_lines) {
      $body->addTextSection(pht('COMMITS'), implode("\n", $commit_lines));
    }

    if ($ref_lines) {
      $body->addTextSection(pht('REFERENCES'), implode("\n", $ref_lines));
    }

    $prefix = PhabricatorEnv::getEnvConfig('metamta.diffusion.subject-prefix');

    $parts = array();
    if ($commit_count) {
      $parts[] = pht('%s commit(s)', $commit_count);
    }
    if ($ref_count) {
      $parts[] = implode(', ', $ref_list);
    }
    $parts = implode(', ', $parts);

    if ($subject_line) {
      $subject = pht('(%s) %s', $parts, $subject_line);
    } else {
      $subject = pht('(%s)', $parts);
    }

    $mail = id(new PhabricatorMetaMTAMail())
      ->setRelatedPHID($event->getPHID())
      ->setSubjectPrefix($prefix)
      ->setVarySubjectPrefix(pht('[Push]'))
      ->setSubject($subject)
      ->setFrom($event->getPusherPHID())
      ->setBody($body->render())
      ->setThreadID($event->getPHID(), $is_new = true)
      ->addHeader('Thread-Topic', $subject)
      ->setIsBulk(true);

    return $target->willSendMail($mail);
  }

  public function renderForDisplay(PhabricatorUser $viewer) {
    // This data has some sensitive stuff, so don't show it.
    return null;
  }

  private function renderRefs(array $logs) {
    $ref_lines = array();
    $ref_list = array();

    foreach ($logs as $log) {
      $type_name = null;
      $type_prefix = null;
      switch ($log->getRefType()) {
        case PhabricatorRepositoryPushLog::REFTYPE_BRANCH:
          $type_name = pht('branch');
          break;
        case PhabricatorRepositoryPushLog::REFTYPE_TAG:
          $type_name = pht('tag');
          $type_prefix = pht('tag:');
          break;
        case PhabricatorRepositoryPushLog::REFTYPE_BOOKMARK:
          $type_name = pht('bookmark');
          $type_prefix = pht('bookmark:');
          break;
        case PhabricatorRepositoryPushLog::REFTYPE_COMMIT:
        default:
          break;
      }

      if ($type_name === null) {
        continue;
      }

      $flags = $log->getChangeFlags();
      if ($flags & PhabricatorRepositoryPushLog::CHANGEFLAG_DANGEROUS) {
        $action = '!';
      } else if ($flags & PhabricatorRepositoryPushLog::CHANGEFLAG_DELETE) {
        $action = '-';
      } else if ($flags & PhabricatorRepositoryPushLog::CHANGEFLAG_REWRITE) {
        $action = '~';
      } else if ($flags & PhabricatorRepositoryPushLog::CHANGEFLAG_APPEND) {
        $action = ' ';
      } else if ($flags & PhabricatorRepositoryPushLog::CHANGEFLAG_ADD) {
        $action = '+';
      } else {
        $action = '?';
      }

      $old = nonempty($log->getRefOldShort(), pht('<null>'));
      $new = nonempty($log->getRefNewShort(), pht('<null>'));

      $name = $log->getRefName();

      $ref_lines[] = "{$action} {$type_name} {$name} {$old} > {$new}";
      $ref_list[] = $type_prefix.$name;
    }

    return array(
      $ref_lines,
      array_unique($ref_list),
    );
  }

  private function renderCommits(
    PhabricatorRepository $repository,
    array $logs,
    array $info) {

    $commit_lines = array();
    $subject_line = null;
    foreach ($logs as $log) {
      if ($log->getRefType() != PhabricatorRepositoryPushLog::REFTYPE_COMMIT) {
        continue;
      }

      $commit_info = idx($info, $log->getRefNew(), array());

      $name = $repository->formatCommitName($log->getRefNew());

      $branches = null;
      if (idx($commit_info, 'branches')) {
        $branches = ' ('.implode(', ', $commit_info['branches']).')';
      }

      $summary = null;
      if (strlen(idx($commit_info, 'summary'))) {
        $summary = ' '.$commit_info['summary'];
      }

      $commit_lines[] = "{$name}{$branches}{$summary}";
      if ($subject_line === null) {
        $subject_line = "{$name}{$summary}";
      }
    }

    return array($commit_lines, $subject_line);
  }

}
