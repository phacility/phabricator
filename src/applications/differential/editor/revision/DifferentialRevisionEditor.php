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

/**
 * Handle major edit operations to DifferentialRevision -- adding and removing
 * reviewers, diffs, and CCs. Unlike simple edits, these changes trigger
 * complicated email workflows.
 */
class DifferentialRevisionEditor {

  protected $revision;
  protected $actorPHID;

  protected $cc         = null;
  protected $reviewers  = null;
  protected $diff;
  protected $comments;
  protected $silentUpdate;

  private $auxiliaryFields = array();
  private $contentSource;

  public function __construct(DifferentialRevision $revision, $actor_phid) {
    $this->revision = $revision;
    $this->actorPHID = $actor_phid;
  }

  public static function newRevisionFromConduitWithDiff(
    array $fields,
    DifferentialDiff $diff,
    $user_phid) {

    $revision = new DifferentialRevision();
    $revision->setPHID($revision->generatePHID());

    $revision->setAuthorPHID($user_phid);
    $revision->setStatus(DifferentialRevisionStatus::NEEDS_REVIEW);

    $editor = new DifferentialRevisionEditor($revision, $user_phid);

    $editor->copyFieldsFromConduit($fields);

    $editor->addDiff($diff, null);
    $editor->save();

    return $revision;
  }

  public function copyFieldsFromConduit(array $fields) {

    $revision = $this->revision;
    $revision->loadRelationships();

    $aux_fields = DifferentialFieldSelector::newSelector()
      ->getFieldSpecifications();

    foreach ($aux_fields as $key => $aux_field) {
      $aux_field->setRevision($revision);
      if (!$aux_field->shouldAppearOnCommitMessage()) {
        unset($aux_fields[$key]);
      }
    }

    $aux_fields = mpull($aux_fields, null, 'getCommitMessageKey');

    foreach ($fields as $field => $value) {
      if (empty($aux_fields[$field])) {
        throw new Exception(
          "Parsed commit message contains unrecognized field '{$field}'.");
      }
      $aux_fields[$field]->setValueFromParsedCommitMessage($value);
    }

    $aux_fields = array_values($aux_fields);
    $this->setAuxiliaryFields($aux_fields);
  }

  public function setAuxiliaryFields(array $auxiliary_fields) {
    $this->auxiliaryFields = $auxiliary_fields;
    return $this;
  }

  public function getRevision() {
    return $this->revision;
  }

  public function setReviewers(array $reviewers) {
    $this->reviewers = $reviewers;
    return $this;
  }

  public function setCCPHIDs(array $cc) {
    $this->cc = $cc;
    return $this;
  }

  public function setContentSource(PhabricatorContentSource $content_source) {
    $this->contentSource = $content_source;
    return $this;
  }

  public function addDiff(DifferentialDiff $diff, $comments) {
    if ($diff->getRevisionID() &&
        $diff->getRevisionID() != $this->getRevision()->getID()) {
      $diff_id = (int)$diff->getID();
      $targ_id = (int)$this->getRevision()->getID();
      $real_id = (int)$diff->getRevisionID();
      throw new Exception(
        "Can not attach diff #{$diff_id} to Revision D{$targ_id}, it is ".
        "already attached to D{$real_id}.");
    }
    $this->diff = $diff;
    $this->comments = $comments;
    return $this;
  }

  protected function getDiff() {
    return $this->diff;
  }

  protected function getComments() {
    return $this->comments;
  }

  protected function getActorPHID() {
    return $this->actorPHID;
  }

  public function isNewRevision() {
    return !$this->getRevision()->getID();
  }

  /**
   * A silent update does not trigger Herald rules or send emails. This is used
   * for auto-amends at commit time.
   */
  public function setSilentUpdate($silent) {
    $this->silentUpdate = $silent;
    return $this;
  }

  public function save() {
    $revision = $this->getRevision();

// TODO
//    $revision->openTransaction();

    $is_new = $this->isNewRevision();
    if ($is_new) {
      // These fields aren't nullable; set them to sensible defaults if they
      // haven't been configured. We're just doing this so we can generate an
      // ID for the revision if we don't have one already.
      $revision->setLineCount(0);
      if ($revision->getStatus() === null) {
        $revision->setStatus(DifferentialRevisionStatus::NEEDS_REVIEW);
      }
      if ($revision->getTitle() === null) {
        $revision->setTitle('Untitled Revision');
      }
      if ($revision->getAuthorPHID() === null) {
        $revision->setAuthorPHID($this->getActorPHID());
      }
      if ($revision->getSummary() === null) {
        $revision->setSummary('');
      }
      if ($revision->getTestPlan() === null) {
        $revision->setTestPlan('');
      }
      $revision->save();
    }

    $revision->loadRelationships();

    $this->willWriteRevision();

    if ($this->reviewers === null) {
      $this->reviewers = $revision->getReviewers();
    }

    if ($this->cc === null) {
      $this->cc = $revision->getCCPHIDs();
    }

    // We're going to build up three dictionaries: $add, $rem, and $stable. The
    // $add dictionary has added reviewers/CCs. The $rem dictionary has
    // reviewers/CCs who have been removed, and the $stable array is
    // reviewers/CCs who haven't changed. We're going to send new reviewers/CCs
    // a different ("welcome") email than we send stable reviewers/CCs.

    $old = array(
      'rev' => array_fill_keys($revision->getReviewers(), true),
      'ccs' => array_fill_keys($revision->getCCPHIDs(), true),
    );

    $diff = $this->getDiff();

    $xscript_header = null;
    $xscript_uri = null;

    $new = array(
      'rev' => array_fill_keys($this->reviewers, true),
      'ccs' => array_fill_keys($this->cc, true),
    );


    $rem_ccs = array();
    if ($diff) {
      $diff->setRevisionID($revision->getID());
      $revision->setLineCount($diff->getLineCount());

      $adapter = new HeraldDifferentialRevisionAdapter(
        $revision,
        $diff);
      $adapter->setExplicitCCs($new['ccs']);
      $adapter->setExplicitReviewers($new['rev']);
      $adapter->setForbiddenCCs($revision->getUnsubscribedPHIDs());

      $xscript = HeraldEngine::loadAndApplyRules($adapter);
      $xscript_uri = PhabricatorEnv::getProductionURI(
        '/herald/transcript/'.$xscript->getID().'/');
      $xscript_phid = $xscript->getPHID();
      $xscript_header = $xscript->getXHeraldRulesHeader();

      $xscript_header = HeraldTranscript::saveXHeraldRulesHeader(
        $revision->getPHID(),
        $xscript_header);

      $sub = array(
        'rev' => array(),
        'ccs' => $adapter->getCCsAddedByHerald(),
      );
      $rem_ccs = $adapter->getCCsRemovedByHerald();
    } else {
      $sub = array(
        'rev' => array(),
        'ccs' => array(),
      );
    }

    // Remove any CCs which are prevented by Herald rules.
    $sub['ccs'] = array_diff_key($sub['ccs'], $rem_ccs);
    $new['ccs'] = array_diff_key($new['ccs'], $rem_ccs);

    $add = array();
    $rem = array();
    $stable = array();
    foreach (array('rev', 'ccs') as $key) {
      $add[$key] = array();
      if ($new[$key] !== null) {
        $add[$key] += array_diff_key($new[$key], $old[$key]);
      }
      $add[$key] += array_diff_key($sub[$key], $old[$key]);

      $combined = $sub[$key];
      if ($new[$key] !== null) {
        $combined += $new[$key];
      }
      $rem[$key] = array_diff_key($old[$key], $combined);

      $stable[$key] = array_diff_key($old[$key], $add[$key] + $rem[$key]);
    }

    self::alterReviewers(
      $revision,
      $this->reviewers,
      array_keys($rem['rev']),
      array_keys($add['rev']),
      $this->actorPHID);

/*

    // TODO: When Herald is brought over, run through this stuff to figure
    // out which adds are Herald's fault.

    // TODO: Still need to do this.

    if ($add['ccs'] || $rem['ccs']) {
      foreach (array_keys($add['ccs']) as $id) {
        if (empty($new['ccs'][$id])) {
          $reason_phid = 'TODO';//$xscript_phid;
        } else {
          $reason_phid = $this->getActorPHID();
        }
      }
      foreach (array_keys($rem['ccs']) as $id) {
        if (empty($new['ccs'][$id])) {
          $reason_phid = $this->getActorPHID();
        } else {
          $reason_phid = 'TODO';//$xscript_phid;
        }
      }
    }
*/
    self::alterCCs(
      $revision,
      $this->cc,
      array_keys($rem['ccs']),
      array_keys($add['ccs']),
      $this->actorPHID);

    $this->updateAuxiliaryFields();

    // Add the author and users included from Herald rules to the relevant set
    // of users so they get a copy of the email.
    if (!$this->silentUpdate) {
      if ($is_new) {
        $add['rev'][$this->getActorPHID()] = true;
        if ($diff) {
          $add['rev'] += $adapter->getEmailPHIDsAddedByHerald();
        }
      } else {
        $stable['rev'][$this->getActorPHID()] = true;
        if ($diff) {
          $stable['rev'] += $adapter->getEmailPHIDsAddedByHerald();
        }
      }
    }

    $mail = array();

    $phids = array($this->getActorPHID());

    $handles = id(new PhabricatorObjectHandleData($phids))
      ->loadHandles();
    $actor_handle = $handles[$this->getActorPHID()];

    $changesets = null;
    $comment = null;
    if ($diff) {
      $changesets = $diff->loadChangesets();
      // TODO: This should probably be in DifferentialFeedbackEditor?
      if (!$is_new) {
        $comment = $this->createComment();
      }
      if ($comment) {
        $mail[] = id(new DifferentialNewDiffMail(
            $revision,
            $actor_handle,
            $changesets))
          ->setIsFirstMailAboutRevision($is_new)
          ->setIsFirstMailToRecipients($is_new)
          ->setComments($this->getComments())
          ->setToPHIDs(array_keys($stable['rev']))
          ->setCCPHIDs(array_keys($stable['ccs']));
      }

      // Save the changes we made above.

      $diff->setDescription(substr($this->getComments(), 0, 80));
      $diff->save();

      // An updated diff should require review, as long as it's not committed
      // or accepted. The "accepted" status is "sticky" to encourage courtesy
      // re-diffs after someone accepts with minor changes/suggestions.

      $status = $revision->getStatus();
      if ($status != DifferentialRevisionStatus::COMMITTED &&
          $status != DifferentialRevisionStatus::ACCEPTED) {
        $revision->setStatus(DifferentialRevisionStatus::NEEDS_REVIEW);
      }

    } else {
      $diff = $revision->loadActiveDiff();
      if ($diff) {
        $changesets = $diff->loadChangesets();
      } else {
        $changesets = array();
      }
    }

    $revision->save();

    $this->didWriteRevision();

    $event_data = array(
      'revision_id'          => $revision->getID(),
      'revision_phid'        => $revision->getPHID(),
      'revision_name'        => $revision->getTitle(),
      'revision_author_phid' => $revision->getAuthorPHID(),
      'action'               => $is_new
        ? DifferentialAction::ACTION_CREATE
        : DifferentialAction::ACTION_UPDATE,
      'feedback_content'     => $is_new
        ? phutil_utf8_shorten($revision->getSummary(), 140)
        : $this->getComments(),
      'actor_phid'           => $revision->getAuthorPHID(),
    );
    id(new PhabricatorTimelineEvent('difx', $event_data))
      ->recordEvent();

    id(new PhabricatorFeedStoryPublisher())
      ->setStoryType(PhabricatorFeedStoryTypeConstants::STORY_DIFFERENTIAL)
      ->setStoryData($event_data)
      ->setStoryTime(time())
      ->setStoryAuthorPHID($revision->getAuthorPHID())
      ->setRelatedPHIDs(
        array(
          $revision->getPHID(),
          $revision->getAuthorPHID(),
        ))
      ->publish();

// TODO
//    $revision->saveTransaction();

//  TODO: Move this into a worker task thing.
    PhabricatorSearchDifferentialIndexer::indexRevision($revision);

    if ($this->silentUpdate) {
      return;
    }

    $revision->loadRelationships();

    if ($add['rev']) {
      $message = id(new DifferentialNewDiffMail(
          $revision,
          $actor_handle,
          $changesets))
        ->setIsFirstMailAboutRevision($is_new)
        ->setIsFirstMailToRecipients(true)
        ->setToPHIDs(array_keys($add['rev']));

      if ($is_new) {
        // The first time we send an email about a revision, put the CCs in
        // the "CC:" field of the same "Review Requested" email that reviewers
        // get, so you don't get two initial emails if you're on a list that
        // is CC'd.
        $message->setCCPHIDs(array_keys($add['ccs']));
      }

      $mail[] = $message;
    }

    // If you were added as a reviewer and a CC, just give you the reviewer
    // email. We could go to greater lengths to prevent this, but there's
    // bunch of stuff with list subscriptions anyway. You can still get two
    // emails, but only if a revision is updated and you are added as a reviewer
    // at the same time a list you are on is added as a CC, which is rare and
    // reasonable.
    $add['ccs'] = array_diff_key($add['ccs'], $add['rev']);

    if (!$is_new && $add['ccs']) {
      $mail[] = id(new DifferentialCCWelcomeMail(
          $revision,
          $actor_handle,
          $changesets))
        ->setIsFirstMailToRecipients(true)
        ->setToPHIDs(array_keys($add['ccs']));
    }

    foreach ($mail as $message) {
      $message->setHeraldTranscriptURI($xscript_uri);
      $message->setXHeraldRulesHeader($xscript_header);
      $message->send();
    }
  }

  public static function addCCAndUpdateRevision(
    $revision,
    $phid,
    $reason) {

    self::addCC($revision, $phid, $reason);

    $unsubscribed = $revision->getUnsubscribed();
    if (isset($unsubscribed[$phid])) {
      unset($unsubscribed[$phid]);
      $revision->setUnsubscribed($unsubscribed);
      $revision->save();
    }
  }

  public static function removeCCAndUpdateRevision(
    $revision,
    $phid,
    $reason) {

    self::removeCC($revision, $phid, $reason);

    $unsubscribed = $revision->getUnsubscribed();
    if (empty($unsubscribed[$phid])) {
      $unsubscribed[$phid] = true;
      $revision->setUnsubscribed($unsubscribed);
      $revision->save();
    }
  }

  public static function addCC(
    DifferentialRevision $revision,
    $phid,
    $reason) {
    return self::alterCCs(
      $revision,
      $revision->getCCPHIDs(),
      $rem = array(),
      $add = array($phid),
      $reason);
  }

  public static function removeCC(
    DifferentialRevision $revision,
    $phid,
    $reason) {
    return self::alterCCs(
      $revision,
      $revision->getCCPHIDs(),
      $rem = array($phid),
      $add = array(),
      $reason);
  }

  protected static function alterCCs(
    DifferentialRevision $revision,
    array $stable_phids,
    array $rem_phids,
    array $add_phids,
    $reason_phid) {

    return self::alterRelationships(
      $revision,
      $stable_phids,
      $rem_phids,
      $add_phids,
      $reason_phid,
      DifferentialRevision::RELATION_SUBSCRIBED);
  }


  public static function alterReviewers(
    DifferentialRevision $revision,
    array $stable_phids,
    array $rem_phids,
    array $add_phids,
    $reason_phid) {

    return self::alterRelationships(
      $revision,
      $stable_phids,
      $rem_phids,
      $add_phids,
      $reason_phid,
      DifferentialRevision::RELATION_REVIEWER);
  }

  private static function alterRelationships(
    DifferentialRevision $revision,
    array $stable_phids,
    array $rem_phids,
    array $add_phids,
    $reason_phid,
    $relation_type) {

    $rem_map = array_fill_keys($rem_phids, true);
    $add_map = array_fill_keys($add_phids, true);

    $seq_map = array_values($stable_phids);
    $seq_map = array_flip($seq_map);
    foreach ($rem_map as $phid => $ignored) {
      if (!isset($seq_map[$phid])) {
        $seq_map[$phid] = count($seq_map);
      }
    }
    foreach ($add_map as $phid => $ignored) {
      if (!isset($seq_map[$phid])) {
        $seq_map[$phid] = count($seq_map);
      }
    }

    $raw = $revision->getRawRelations($relation_type);
    $raw = ipull($raw, null, 'objectPHID');

    $sequence = count($seq_map);
    foreach ($raw as $phid => $ignored) {
      if (isset($seq_map[$phid])) {
        $raw[$phid]['sequence'] = $seq_map[$phid];
      } else {
        $raw[$phid]['sequence'] = $sequence++;
      }
    }
    $raw = isort($raw, 'sequence');

    foreach ($raw as $phid => $ignored) {
      if (isset($rem_map[$phid])) {
        unset($raw[$phid]);
      }
    }

    foreach ($add_phids as $add) {
      $raw[$add] = array(
        'objectPHID'  => $add,
        'sequence'    => idx($seq_map, $add, $sequence++),
        'reasonPHID'  => $reason_phid,
      );
    }

    $conn_w = $revision->establishConnection('w');

    $sql = array();
    foreach ($raw as $relation) {
      $sql[] = qsprintf(
        $conn_w,
        '(%d, %s, %s, %d, %s)',
        $revision->getID(),
        $relation_type,
        $relation['objectPHID'],
        $relation['sequence'],
        $relation['reasonPHID']);
    }

    $conn_w->openTransaction();
      queryfx(
        $conn_w,
        'DELETE FROM %T WHERE revisionID = %d AND relation = %s',
        DifferentialRevision::RELATIONSHIP_TABLE,
        $revision->getID(),
        $relation_type);
      if ($sql) {
        queryfx(
          $conn_w,
          'INSERT INTO %T
            (revisionID, relation, objectPHID, sequence, reasonPHID)
          VALUES %Q',
          DifferentialRevision::RELATIONSHIP_TABLE,
          implode(', ', $sql));
      }
    $conn_w->saveTransaction();

    $revision->loadRelationships();
  }


  private function createComment() {
    $revision_id = $this->revision->getID();
    $comment = id(new DifferentialComment())
      ->setAuthorPHID($this->getActorPHID())
      ->setRevisionID($revision_id)
      ->setContent($this->getComments())
      ->setAction('update');

    if ($this->contentSource) {
      $comment->setContentSource($this->contentSource);
    }

    $comment->save();

    return $comment;
  }

  private function updateAuxiliaryFields() {
    $aux_map = array();
    foreach ($this->auxiliaryFields as $aux_field) {
      $key = $aux_field->getStorageKey();
      if ($key !== null) {
        $val = $aux_field->getValueForStorage();
        $aux_map[$key] = $val;
      }
    }

    if (!$aux_map) {
      return;
    }

    $revision = $this->revision;

    $fields = id(new DifferentialAuxiliaryField())->loadAllWhere(
      'revisionPHID = %s AND name IN (%Ls)',
      $revision->getPHID(),
      array_keys($aux_map));
    $fields = mpull($fields, null, 'getName');

    foreach ($aux_map as $key => $val) {
      $obj = idx($fields, $key);
      if (!strlen($val)) {
        // If the new value is empty, just delete the old row if one exists and
        // don't add a new row if it doesn't.
        if ($obj) {
          $obj->delete();
        }
      } else {
        if (!$obj) {
          $obj = new DifferentialAuxiliaryField();
          $obj->setRevisionPHID($revision->getPHID());
          $obj->setName($key);
        }

        if ($obj->getValue() !== $val) {
          $obj->setValue($val);
          $obj->save();
        }
      }
    }
  }

  private function willWriteRevision() {
    foreach ($this->auxiliaryFields as $aux_field) {
      $aux_field->willWriteRevision($this);
    }
  }

  private function didWriteRevision() {
    foreach ($this->auxiliaryFields as $aux_field) {
      $aux_field->didWriteRevision($this);
    }
  }

}

