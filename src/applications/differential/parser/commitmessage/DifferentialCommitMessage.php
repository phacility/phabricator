<?php

/*
 * Copyright 2011 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class DifferentialCommitMessage {

  protected $rawCorpus;

  protected $title;
  protected $summary;
  protected $testPlan;

  protected $blameRevision;
  protected $revertPlan;

  protected $reviewerNames = array();
  protected $reviewerPHIDs;

  protected $reviewedByNames = array();
  protected $reviewedByPHIDs;

  protected $ccNames = array();
  protected $ccPHIDs;

  protected $revisionID;
  protected $gitSVNID;

  protected function __construct() {

  }

  public function getReviewerPHIDs() {
    return $this->reviewerPHIDs;
  }

  public function getReviewedByPHIDs() {
    return $this->reviewedByPHIDs;
  }

  public function getCCPHIDs() {
    return $this->ccPHIDs;
  }

  public function setTitle($title) {
    $this->title = $title;
    return $this;
  }

  public function getTitle() {
    return $this->title;
  }

  public function setRevisionID($revision_id) {
    $this->revisionID = $revision_id;
    return $this;
  }

  public function getRevisionID() {
    return $this->revisionID;
  }

  public function setSummary($summary) {
    $this->summary = $summary;
    return $this;
  }

  public function getSummary() {
    return $this->summary;
  }

  public function setTestPlan($test_plan) {
    $this->testPlan = $test_plan;
    return $this;
  }

  public function getTestPlan() {
    return $this->testPlan;
  }

  public function setBlameRevision($blame_revision) {
    $this->blameRevision = $blame_revision;
    return $this;
  }

  public function getBlameRevision() {
    return $this->blameRevision;
  }

  public function setRevertPlan($revert_plan) {
    $this->revertPlan = $revert_plan;
    return $this;
  }

  public function getRevertPlan() {
    return $this->revertPlan;
  }

  public function setReviewerNames($reviewer_names) {
    $this->reviewerNames = $reviewer_names;
    return $this;
  }

  public function getReviewerNames() {
    return $this->reviewerNames;
  }

  public function setCCNames($cc_names) {
    $this->ccNames = $cc_names;
    return $this;
  }

  public function getCCNames() {
    return $this->ccNames;
  }

  public function setReviewedByNames($reviewed_by_names) {
    $this->reviewedByNames = $reviewed_by_names;
    return $this;
  }

  public function getReviewedByNames() {
    return $this->reviewedByNames;
  }

  public function setGitSVNID($git_svn_id) {
    $this->gitSVNID = $git_svn_id;
    return $this;
  }

  public function getGitSVNID() {
    return $this->gitSVNID;
  }

  public function setReviewerPHIDs(array $phids) {
    $this->reviewerPHIDs = $phids;
    return $this;
  }

  public function setReviewedByPHIDs(array $phids) {
    $this->reviewedByPHIDs = $phids;
    return $this;
  }

  public function setCCPHIDs(array $phids) {
    $this->ccPHIDs = $phids;
    return $this;
  }


  public static function newFromRawCorpus($raw_corpus) {
    $message = new DifferentialCommitMessage();
    $message->setRawCorpus($raw_corpus);

    $fields = $message->parseFields($raw_corpus);

    foreach ($fields as $field => $data) {
      switch ($field) {
        case 'Title':
          $message->setTitle($data);
          break;
        case 'Differential Revision':
          $message->setRevisionID($data);
          break;
        case 'Summary':
          $message->setSummary($data);
          break;
        case 'Test Plan':
          $message->setTestPlan($data);
          break;
        case 'Blame Revision':
          $message->setBlameRevision($data);
          break;
        case 'Revert Plan';
          $message->setRevertPlan($data);
          break;
        case 'Reviewers':
          $message->setReviewerNames($data);
          break;
        case 'Reviewed By':
          $message->setReviewedByNames($data);
          break;
        case 'CC':
          $message->setCCNames($data);
          break;
        case 'git-svn-id':
          $message->setGitSVNID($data);
          break;
        case 'Commenters':
          // Just drop this.
          break;
        default:
          throw new Exception("Unrecognized field '{$field}'.");
      }
    }

    $need_users = array_merge(
      $message->getReviewerNames(),
      $message->getReviewedByNames(),
      $message->getCCNames());
    $need_mail = $message->getCCNames();

    if ($need_users) {
      $users = id(new PhabricatorUser())->loadAllWhere(
        '(username IN (%Ls)) OR (email IN (%Ls))',
        $need_users,
        $need_users);
      $users = mpull($users, 'getPHID', 'getUsername') +
               mpull($users, 'getPHID', 'getEmail');
    } else {
      $users = array();
    }

    if ($need_mail) {
      $mail = id(new PhabricatorMetaMTAMailingList())->loadAllWhere(
        '(email in (%Ls)) OR (name IN (%Ls))',
        $need_mail,
        $need_mail);
      $mail = mpull($mail, 'getPHID', 'getName') +
              mpull($mail, 'getPHID', 'getEmail');
    } else {
      $mail = array();
    }

    $reviewer_phids = array();
    foreach ($message->getReviewerNames() as $name) {
      $phid = idx($users, $name);
      if (!$phid) {
        throw new DifferentialCommitMessageParserException(
          "Commit message references nonexistent 'Reviewer' value '".$name."'");
      }
      $reviewer_phids[] = $phid;
    }
    $message->setReviewerPHIDs($reviewer_phids);

    $reviewed_by_phids = array();
    foreach ($message->getReviewedByNames() as $name) {
      $phid = idx($users, $name);
      if (!$phid) {
        throw new DifferentialCommitMessageParserException(
          "Commit message references nonexistent 'Reviewed by' value '".
          $name."'");
      }
      $reviewed_by_phids[] = $phid;
    }
    $message->setReviewedByPHIDs($reviewed_by_phids);

    $cc_phids = array();
    foreach ($message->getCCNames() as $name) {
      $phid = idx($users, $name);
      if (!$phid) {
        $phid = idx($mail, $name);
      }
      if (!$phid) {
        throw new DifferentialCommitMessageParserException(
          "Commit message references nonexistent 'CC' value '".$name."'");
      }
      $cc_phids[] = $phid;
    }
    $message->setCCPHIDs($cc_phids);

    return $message;
  }

  public function setRawCorpus($raw_corpus) {
    $this->rawCorpus = $raw_corpus;
    return $this;
  }

  public function getRawCorpus() {
    return $this->rawCorpus;
  }

  protected function parseFields($message) {

    $field_spec = array(
      'Differential Revision' => 'Differential Revision',
      'Title'                 => 'Title',
      'Summary'               => 'Summary',
      'Test Plan'             => 'Test Plan',
      'Blame Rev'             => 'Blame Revision',
      'Blame Revision'        => 'Blame Revision',
      'Reviewed By'           => 'Reviewed By',
      'Reviewers'             => 'Reviewers',
      'CC'                    => 'CC',
      'Revert'                => 'Revert Plan',
      'Revert Plan'           => 'Revert Plan',

      'git-svn-id'            => 'git-svn-id',

      // This appears only in "arc amend"-ed messages, just discard it.
      'Commenters'            => 'Commenters',
    );

    $field_names = array_keys($field_spec);
    foreach ($field_names as $key => $name) {
      $field_names[$key] = preg_quote($name, '/');
    }
    $field_names = implode('|', $field_names);
    $field_pattern = '/^(?P<field>'.$field_names.'):(?P<text>.*)$/i';

    foreach ($field_spec as $key => $value) {
      $field_spec[strtolower($key)] = $value;
    }

    $message = trim($message);
    $lines = explode("\n", $message);
    $this->rawInput = $lines;

    if (!$message) {
      $this->fail(
        null,
        "Your commit message is empty.");
    }

    $field = 'Title';
    // Note, deliberately not populating $seen with 'Title' because it is
    // optional to include the 'Title:' header.
    $seen = array();
    $field_map = array();
    foreach ($lines as $key => $line) {
      $match = null;
      if (preg_match($field_pattern, $line, $match)) {
        $lines[$key] = trim($match['text']);
        $field = $field_spec[strtolower($match['field'])];
        if (!empty($seen[$field])) {
          $this->fail(
            $key,
            "Field '{$field}' occurs twice in commit message.");
        }
        $seen[$field] = true;
      }

      $field_map[$key] = $field;
    }

    $fields = array();
    foreach ($lines as $key => $line) {
      $fields[$field_map[$key]][] = $line;
    }

    foreach ($fields as $name => $lines) {
      if ($name == 'Title') {
        // If the user enters a title and then a blank line without a summary,
        // treat the first line as the title and the rest as the summary.
        if (!isset($fields['Summary'])) {
          $ii = 0;
          for ($ii = 0; $ii < count($lines); $ii++) {
            if (strlen(trim($lines[$ii])) == 0) {
              break;
            }
          }
          if ($ii != count($lines)) {
            $fields['Title'] = array_slice($lines, 0, $ii);
            $fields['Summary'] = array_slice($lines, $ii);
          }
        }
      }
    }

    foreach ($fields as $name => $lines) {
      $data = rtrim(implode("\n", $lines));
      $data = ltrim($data, "\n");
      switch ($name) {
        case 'Title':
          $data = preg_replace('/\s*\n\s*/', ' ', $data);
          break;
        case 'Tasks':
          list($pre_comment) = split(' -- ', $data);
          $data = array_filter(preg_split('/[^\d]+/', $pre_comment));
          foreach ($data as $k => $v) {
            $data[$k] = (int)$v;
          }
          $data = array_unique($data);
          break;
        case 'Blame Revision':
        case 'Differential Revision':
          $data = (int)preg_replace('/[^\d]/', '', $data);
          break;
        case 'CC':
        case 'Reviewers':
        case 'Reviewed By':
          $data = array_filter(preg_split('/[\s,]+/', $data));
          break;
      }
      if (is_array($data)) {
        $data = array_values($data);
      }
      if ($data) {
        $fields[$name] = $data;
      } else {
        unset($fields[$name]);
      }
    }

    return $fields;
  }

  protected function fail($line, $reason) {
    if ($line !== null) {
      $lines = $this->rawInput;
      $min = max(0, $line - 3);
      $max = min(count($lines) - 1, $line + 3);
      $reason .= "\n\n";
      $len = strlen($max);
      for ($ii = $min; $ii <= $max; $ii++) {
         $reason .= sprintf(
           "%8.8s % {$len}d %s\n",
           $ii == $line ? '>>>' : '',
           $ii + 1,
           $lines[$ii]);
      }
    }
    throw new DifferentialCommitMessageParserException($reason);
  }

}
