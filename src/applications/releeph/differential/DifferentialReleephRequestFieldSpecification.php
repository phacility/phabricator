<?php

/**
 * This DifferentialFieldSpecification exists for two reason:
 *
 * 1: To parse "Releeph: picks RQ<nn>" headers in commits created by
 * arc-releeph so that RQs committed by arc-releeph have real
 * PhabricatorRepositoryCommits associated with them (instaed of just the SHA
 * of the commit, as seen by the pusher).
 *
 * 2: If requestors want to commit directly to their release branch, they can
 * use this header to (i) indicate on a differential revision that this
 * differential revision is for the release branch, and (ii) when they land
 * their diff on to the release branch manually, the ReleephRequest is
 * automatically updated (instead of having to use the "Mark Manually Picked"
 * button.)
 *
 */
final class DifferentialReleephRequestFieldSpecification extends Phobject {

  // TODO: This class is essentially dead right now, see T2222.

  const ACTION_PICKS    = 'picks';
  const ACTION_REVERTS  = 'reverts';

  private $releephAction;
  private $releephPHIDs = array();

  public function getStorageKey() {
    return 'releeph:actions';
  }

  public function getValueForStorage() {
    return json_encode(array(
      'releephAction' => $this->releephAction,
      'releephPHIDs'  => $this->releephPHIDs,
    ));
  }

  public function setValueFromStorage($json) {
    if ($json) {
      $dict = phutil_json_decode($json);
      $this->releephAction = idx($dict, 'releephAction');
      $this->releephPHIDs = idx($dict, 'releephPHIDs');
    }
    return $this;
  }

  public function shouldAppearOnRevisionView() {
    return true;
  }

  public function renderLabelForRevisionView() {
    return pht('Releeph');
  }

  public function getRequiredHandlePHIDs() {
    return mpull($this->loadReleephRequests(), 'getPHID');
  }

  public function renderValueForRevisionView() {
    static $tense;

    if ($tense === null) {
      $tense = array(
        self::ACTION_PICKS => array(
          'future'  => pht('Will pick'),
          'past'    => pht('Picked'),
        ),
        self::ACTION_REVERTS => array(
          'future'  => pht('Will revert'),
          'past'    => pht('Reverted'),
        ),
      );
    }

    $releeph_requests = $this->loadReleephRequests();
    if (!$releeph_requests) {
      return null;
    }

    $status = $this->getRevision()->getStatus();
    if ($status == ArcanistDifferentialRevisionStatus::CLOSED) {
      $verb = $tense[$this->releephAction]['past'];
    } else {
      $verb = $tense[$this->releephAction]['future'];
    }

    $parts = hsprintf('%s...', $verb);
    foreach ($releeph_requests as $releeph_request) {
      $parts->appendHTML(phutil_tag('br'));
      $parts->appendHTML(
        $this->getHandle($releeph_request->getPHID())->renderLink());
    }

    return $parts;
  }

  public function shouldAppearOnCommitMessage() {
    return true;
  }

  public function getCommitMessageKey() {
    return 'releephActions';
  }

  public function setValueFromParsedCommitMessage($dict) {
    $this->releephAction = $dict['releephAction'];
    $this->releephPHIDs = $dict['releephPHIDs'];
    return $this;
  }

  public function renderValueForCommitMessage($is_edit) {
    $releeph_requests = $this->loadReleephRequests();
    if (!$releeph_requests) {
      return null;
    }

    $parts = array($this->releephAction);
    foreach ($releeph_requests as $releeph_request) {
      $parts[] = 'RQ'.$releeph_request->getID();
    }

    return implode(' ', $parts);
  }

  /**
   * Releeph fields should look like:
   *
   *   Releeph: picks RQ1 RQ2, RQ3
   *   Releeph: reverts RQ1
   */
  public function parseValueFromCommitMessage($value) {
    /**
     * Releeph commit messages look like this (but with more blank lines,
     * omitted here):
     *
     *   Make CaptainHaddock more reasonable
     *   Releeph: picks RQ1
     *   Requested By: edward
     *   Approved By: edward (requestor)
     *   Request Reason: x
     *   Summary: Make the Haddock implementation more reasonable.
     *   Test Plan: none
     *   Reviewers: user1
     *
     * Some of these fields are recognized by Differential (e.g. "Requested
     * By"). They are folded up into the "Releeph" field, parsed by this
     * class. As such $value includes more than just the first-line:
     *
     *   "picks RQ1\n\nRequested By: edward\n\nApproved By: edward (requestor)"
     *
     * To hack around this, just consider the first line of $value when
     * determining what Releeph actions the parsed commit is performing.
     */
    $first_line = head(array_filter(explode("\n", $value)));

    $tokens = preg_split('/\s*,?\s+/', $first_line);
    $raw_action = array_shift($tokens);
    $action = strtolower($raw_action);

    if (!$action) {
      return null;
    }

    switch ($action) {
      case self::ACTION_REVERTS:
      case self::ACTION_PICKS:
        break;

      default:
        throw new DifferentialFieldParseException(
          pht(
            "Commit message contains unknown Releeph action '%s'!",
            $raw_action));
        break;
    }

    $releeph_requests = array();
    foreach ($tokens as $token) {
      $match = array();
      if (!preg_match('/^(?:RQ)?(\d+)$/i', $token, $match)) {
        $label = $this->renderLabelForCommitMessage();
        throw new DifferentialFieldParseException(
          pht(
            "Commit message contains unparseable ".
            "Releeph request token '%s'!",
            $token));
      }

      $id = (int)$match[1];
      $releeph_request = id(new ReleephRequest())->load($id);

      if (!$releeph_request) {
        throw new DifferentialFieldParseException(
          pht(
            'Commit message references non existent Releeph request: %s!',
            $value));
      }

      $releeph_requests[] = $releeph_request;
    }

    if (count($releeph_requests) > 1) {
      $rqs_seen = array();
      $groups = array();
      foreach ($releeph_requests as $releeph_request) {
        $releeph_branch = $releeph_request->getBranch();
        $branch_name = $releeph_branch->getName();
        $rq_id = 'RQ'.$releeph_request->getID();

        if (idx($rqs_seen, $rq_id)) {
          throw new DifferentialFieldParseException(
            pht(
              'Commit message refers to %s multiple times!',
              $rq_id));
        }
        $rqs_seen[$rq_id] = true;

        if (!isset($groups[$branch_name])) {
          $groups[$branch_name] = array();
        }
        $groups[$branch_name][] = $rq_id;
      }

      if (count($groups) > 1) {
        $lists = array();
        foreach ($groups as $branch_name => $rq_ids) {
          $lists[] = implode(', ', $rq_ids).' in '.$branch_name;
        }
        throw new DifferentialFieldParseException(
          pht(
            'Commit message references multiple Releeph requests, '.
            'but the requests are in different branches: %s',
            implode('; ', $lists)));
      }
    }

    $phids = mpull($releeph_requests, 'getPHID');

    $data = array(
      'releephAction' => $action,
      'releephPHIDs'  => $phids,
    );
    return $data;
  }

  public function renderLabelForCommitMessage() {
    return pht('Releeph');
  }

  public function shouldAppearOnCommitMessageTemplate() {
    return false;
  }

  public function didParseCommit(
    PhabricatorRepository $repo,
    PhabricatorRepositoryCommit $commit,
    PhabricatorRepositoryCommitData $data) {

    // NOTE: This is currently dead code. See T2222.

    $releeph_requests = $this->loadReleephRequests();

    if (!$releeph_requests) {
      return;
    }

    $releeph_branch = head($releeph_requests)->getBranch();
    if (!$this->isCommitOnBranch($repo, $commit, $releeph_branch)) {
      return;
    }

    foreach ($releeph_requests as $releeph_request) {
      if ($this->releephAction === self::ACTION_PICKS) {
        $action = 'pick';
      } else {
        $action = 'revert';
      }

      $actor_phid = coalesce(
        $data->getCommitDetail('committerPHID'),
        $data->getCommitDetail('authorPHID'));

      $actor = id(new PhabricatorUser())
        ->loadOneWhere('phid = %s', $actor_phid);

      $xactions = array();

      $xactions[] = id(new ReleephRequestTransaction())
        ->setTransactionType(ReleephRequestTransaction::TYPE_DISCOVERY)
        ->setMetadataValue('action', $action)
        ->setMetadataValue('authorPHID',
          $data->getCommitDetail('authorPHID'))
        ->setMetadataValue('committerPHID',
          $data->getCommitDetail('committerPHID'))
        ->setNewValue($commit->getPHID());

      $editor = id(new ReleephRequestTransactionalEditor())
        ->setActor($actor)
        ->setContinueOnNoEffect(true)
        ->setContentSource(
          PhabricatorContentSource::newForSource(
            PhabricatorContentSource::SOURCE_UNKNOWN,
            array()));

      $editor->applyTransactions($releeph_request, $xactions);
    }
  }

  private function loadReleephRequests() {
    if (!$this->releephPHIDs) {
      return array();
    }

    return id(new ReleephRequestQuery())
      ->setViewer($this->getViewer())
      ->withPHIDs($this->releephPHIDs)
      ->execute();
  }

  private function isCommitOnBranch(
    PhabricatorRepository $repo,
    PhabricatorRepositoryCommit $commit,
    ReleephBranch $releeph_branch) {

    switch ($repo->getVersionControlSystem()) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        list($output) = $repo->execxLocalCommand(
          'branch --all --no-color --contains %s',
          $commit->getCommitIdentifier());

        $remote_prefix = 'remotes/origin/';
        $branches = array();
        foreach (array_filter(explode("\n", $output)) as $line) {
          $tokens = explode(' ', $line);
          $ref = last($tokens);
          if (strncmp($ref, $remote_prefix, strlen($remote_prefix)) === 0) {
            $branch = substr($ref, strlen($remote_prefix));
            $branches[$branch] = $branch;
          }
        }

        return idx($branches, $releeph_branch->getName());
        break;

      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        $change_query = DiffusionPathChangeQuery::newFromDiffusionRequest(
          DiffusionRequest::newFromDictionary(array(
            'user' => $this->getUser(),
            'repository' => $repo,
            'commit' => $commit->getCommitIdentifier(),
          )));
        $path_changes = $change_query->loadChanges();
        $commit_paths = mpull($path_changes, 'getPath');

        $branch_path = $releeph_branch->getName();

        $in_branch = array();
        $ex_branch = array();
        foreach ($commit_paths as $path) {
          if (strncmp($path, $branch_path, strlen($branch_path)) === 0) {
            $in_branch[] = $path;
          } else {
            $ex_branch[] = $path;
          }
        }

        if ($in_branch && $ex_branch) {
          $error = pht(
            'CONFUSION: commit %s in %s contains %d path change(s) that were '.
            'part of a Releeph branch, but also has %d path change(s) not '.
            'part of a Releeph branch!',
            $commit->getCommitIdentifier(),
            $repo->getCallsign(),
            count($in_branch),
            count($ex_branch));
          phlog($error);
        }

        return !empty($in_branch);
        break;
    }
  }

}
