<?php

final class PhabricatorRepositoryCommitHeraldWorker
  extends PhabricatorRepositoryCommitParserWorker {

  public function getRequiredLeaseTime() {
    // Herald rules may take a long time to process.
    return 4 * 60 * 60;
  }

  public function parseCommit(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit) {

    $data = id(new PhabricatorRepositoryCommitData())->loadOneWhere(
      'commitID = %d',
      $commit->getID());

    if (!$data) {
      // TODO: Permanent failure.
      return;
    }

    $rules = HeraldRule::loadAllByContentTypeWithFullData(
      HeraldContentTypeConfig::CONTENT_TYPE_COMMIT,
      $commit->getPHID());

    $adapter = new HeraldCommitAdapter(
      $repository,
      $commit,
      $data);
    $engine = new HeraldEngine();

    $effects = $engine->applyRules($rules, $adapter);
    $engine->applyEffects($effects, $adapter, $rules);

    $audit_phids = $adapter->getAuditMap();
    if ($audit_phids) {
      $this->createAudits($commit, $audit_phids, $rules);
    }

    $explicit_auditors = $this->createAuditsFromCommitMessage($commit, $data);

    if ($repository->getDetail('herald-disabled')) {
      // This just means "disable email"; audits are (mostly) idempotent.
      return;
    }

    $this->publishFeedStory($repository, $commit, $data);

    $herald_targets = $adapter->getEmailPHIDs();

    $email_phids = array_unique(
      array_merge(
        $explicit_auditors,
        $herald_targets));
    if (!$email_phids) {
      return;
    }

    $xscript = $engine->getTranscript();

    $revision = $adapter->loadDifferentialRevision();
    if ($revision) {
      $name = $revision->getTitle();
    } else {
      $name = $data->getSummary();
    }

    $author_phid = $data->getCommitDetail('authorPHID');
    $reviewer_phid = $data->getCommitDetail('reviewerPHID');

    $phids = array_filter(
      array(
        $author_phid,
        $reviewer_phid,
        $commit->getPHID(),
      ));

    $handles = id(new PhabricatorObjectHandleData($phids))
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->loadHandles();

    $commit_handle = $handles[$commit->getPHID()];
    $commit_name = $commit_handle->getName();

    if ($author_phid) {
      $author_name = $handles[$author_phid]->getName();
    } else {
      $author_name = $data->getAuthorName();
    }

    if ($reviewer_phid) {
      $reviewer_name = $handles[$reviewer_phid]->getName();
    } else {
      $reviewer_name = null;
    }

    $who = implode(', ', array_filter(array($author_name, $reviewer_name)));

    $description = $data->getCommitMessage();

    $commit_uri = PhabricatorEnv::getProductionURI($commit_handle->getURI());
    $differential = $revision
      ? PhabricatorEnv::getProductionURI('/D'.$revision->getID())
      : 'No revision.';

    $files = $adapter->loadAffectedPaths();
    sort($files);
    $files = implode("\n", $files);

    $xscript_id = $xscript->getID();

    $manage_uri = '/herald/view/commits/';
    $why_uri = '/herald/transcript/'.$xscript_id.'/';

    $reply_handler = PhabricatorAuditCommentEditor::newReplyHandlerForCommit(
      $commit);

    $template = new PhabricatorMetaMTAMail();

    $inline_patch_text = $this->buildPatch($template, $repository, $commit);

    $body = new PhabricatorMetaMTAMailBody();
    $body->addRawSection($description);
    $body->addTextSection(pht('DETAILS'), $commit_uri);
    $body->addTextSection(pht('DIFFERENTIAL REVISION'), $differential);
    $body->addTextSection(pht('AFFECTED FILES'), $files);
    $body->addReplySection($reply_handler->getReplyHandlerInstructions());
    $body->addHeraldSection($manage_uri, $why_uri);
    $body->addRawSection($inline_patch_text);
    $body = $body->render();

    $prefix = PhabricatorEnv::getEnvConfig('metamta.diffusion.subject-prefix');

    $threading = PhabricatorAuditCommentEditor::getMailThreading(
      $repository,
      $commit);
    list($thread_id, $thread_topic) = $threading;

    $template->setRelatedPHID($commit->getPHID());
    $template->setSubject("{$commit_name}: {$name}");
    $template->setSubjectPrefix($prefix);
    $template->setVarySubjectPrefix("[Commit]");
    $template->setBody($body);
    $template->setThreadID($thread_id, $is_new = true);
    $template->addHeader('Thread-Topic', $thread_topic);
    $template->setIsBulk(true);

    $template->addHeader('X-Herald-Rules', $xscript->getXHeraldRulesHeader());
    if ($author_phid) {
      $template->setFrom($author_phid);
    }

    // TODO: We should verify that each recipient can actually see the
    // commit before sending them email (T603).

    $mails = $reply_handler->multiplexMail(
      $template,
      id(new PhabricatorObjectHandleData($email_phids))
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->loadHandles(),
      array());

    foreach ($mails as $mail) {
      $mail->saveAndSend();
    }
  }

  private function createAudits(
    PhabricatorRepositoryCommit $commit,
    array $map,
    array $rules) {
    assert_instances_of($rules, 'HeraldRule');

    $requests = id(new PhabricatorRepositoryAuditRequest())->loadAllWhere(
      'commitPHID = %s',
      $commit->getPHID());
    $requests = mpull($requests, null, 'getAuditorPHID');

    $rules = mpull($rules, null, 'getID');
    foreach ($map as $phid => $rule_ids) {
      $request = idx($requests, $phid);
      if ($request) {
        continue;
      }
      $reasons = array();
      foreach ($rule_ids as $id) {
        $rule_name = '?';
        if ($rules[$id]) {
          $rule_name = $rules[$id]->getName();
        }
        $reasons[] = 'Herald Rule #'.$id.' "'.$rule_name.'" Triggered Audit';
      }

      $request = new PhabricatorRepositoryAuditRequest();
      $request->setCommitPHID($commit->getPHID());
      $request->setAuditorPHID($phid);
      $request->setAuditStatus(PhabricatorAuditStatusConstants::AUDIT_REQUIRED);
      $request->setAuditReasons($reasons);
      $request->save();
    }

    $commit->updateAuditStatus($requests);
    $commit->save();
  }


  /**
   * Find audit requests in the "Auditors" field if it is present and trigger
   * explicit audit requests.
   */
  private function createAuditsFromCommitMessage(
    PhabricatorRepositoryCommit $commit,
    PhabricatorRepositoryCommitData $data) {

    $message = $data->getCommitMessage();

    $matches = null;
    if (!preg_match('/^Auditors:\s*(.*)$/im', $message, $matches)) {
      return array();
    }

    $phids = DifferentialFieldSpecification::parseCommitMessageObjectList(
      $matches[1],
      $include_mailables = false,
      $allow_partial = true);

    if (!$phids) {
      return array();
    }

    $requests = id(new PhabricatorRepositoryAuditRequest())->loadAllWhere(
      'commitPHID = %s',
      $commit->getPHID());
    $requests = mpull($requests, null, 'getAuditorPHID');

    foreach ($phids as $phid) {
      if (isset($requests[$phid])) {
        continue;
      }

      $request = new PhabricatorRepositoryAuditRequest();
      $request->setCommitPHID($commit->getPHID());
      $request->setAuditorPHID($phid);
      $request->setAuditStatus(
        PhabricatorAuditStatusConstants::AUDIT_REQUESTED);
      $request->setAuditReasons(
        array(
          'Requested by Author',
        ));
      $request->save();

      $requests[$phid] = $request;
    }

    $commit->updateAuditStatus($requests);
    $commit->save();

    return $phids;
  }

  private function publishFeedStory(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit,
    PhabricatorRepositoryCommitData $data) {

    if (time() > $commit->getEpoch() + (24 * 60 * 60)) {
      // Don't publish stories that are more than 24 hours old, to avoid
      // ridiculous levels of feed spam if a repository is imported without
      // disabling feed publishing.
      return;
    }

    $author_phid = $commit->getAuthorPHID();
    $committer_phid = $data->getCommitDetail('committerPHID');

    $publisher = new PhabricatorFeedStoryPublisher();
    $publisher->setStoryType(PhabricatorFeedStoryTypeConstants::STORY_COMMIT);
    $publisher->setStoryData(
      array(
        'commitPHID'    => $commit->getPHID(),
        'summary'       => $data->getSummary(),
        'authorName'    => $data->getAuthorName(),
        'authorPHID'    => $author_phid,
        'committerName' => $data->getCommitDetail('committer'),
        'committerPHID' => $committer_phid,
      ));
    $publisher->setStoryTime($commit->getEpoch());
    $publisher->setRelatedPHIDs(
      array_filter(
        array(
          $author_phid,
          $committer_phid,
        )));
    if ($author_phid) {
      $publisher->setStoryAuthorPHID($author_phid);
    }
    $publisher->publish();
  }

  private function buildPatch(
    PhabricatorMetaMTAMail $template,
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit) {

    $attach_key = 'metamta.diffusion.attach-patches';
    $inline_key = 'metamta.diffusion.inline-patches';

    $attach_patches = PhabricatorEnv::getEnvConfig($attach_key);
    $inline_patches = PhabricatorEnv::getEnvConfig($inline_key);

    if (!$attach_patches && !$inline_patches) {
      return;
    }

    $encoding = $repository->getDetail('encoding', 'UTF-8');

    $result = null;
    $patch_error = null;

    try {
      $raw_patch = $this->loadRawPatchText($repository, $commit);
      if ($attach_patches) {
        $commit_name = $repository->formatCommitName(
          $commit->getCommitIdentifier());

        $template->addAttachment(
          new PhabricatorMetaMTAAttachment(
            $raw_patch,
            $commit_name.'.patch',
            'text/x-patch; charset='.$encoding));
      }
    } catch (Exception $ex) {
      phlog($ex);
      $patch_error = 'Unable to generate: '.$ex->getMessage();
    }

    if ($patch_error) {
      $result = $patch_error;
    } else if ($inline_patches) {
      $len = substr_count($raw_patch, "\n");
      if ($len <= $inline_patches) {
        // We send email as utf8, so we need to convert the text to utf8 if
        // we can.
        if ($encoding) {
          $raw_patch = phutil_utf8_convert($raw_patch, 'UTF-8', $encoding);
        }
        $result = phutil_utf8ize($raw_patch);
      }
    }

    if ($result) {
      $result = "PATCH\n\n{$result}\n";
    }

    return $result;
  }

  private function loadRawPatchText(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit) {

    $drequest = DiffusionRequest::newFromDictionary(
      array(
        'repository'  => $repository,
        'commit'      => $commit->getCommitIdentifier(),
      ));

    $raw_query = DiffusionRawDiffQuery::newFromDiffusionRequest($drequest);
    $raw_query->setLinesOfContext(3);

    $time_key = 'metamta.diffusion.time-limit';
    $byte_key = 'metamta.diffusion.byte-limit';
    $time_limit = PhabricatorEnv::getEnvConfig($time_key);
    $byte_limit = PhabricatorEnv::getEnvConfig($byte_key);

    if ($time_limit) {
      $raw_query->setTimeout($time_limit);
    }

    $raw_diff = $raw_query->loadRawDiff();

    $size = strlen($raw_diff);
    if ($byte_limit && $size > $byte_limit) {
      $pretty_size = phabricator_format_bytes($size);
      $pretty_limit = phabricator_format_bytes($byte_limit);
      throw new Exception(
        "Patch size of {$pretty_size} exceeds configured byte size limit of ".
        "{$pretty_limit}.");
    }

    return $raw_diff;
  }

}
