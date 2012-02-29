<?php

/*
 * Copyright 2012 Facebook, Inc.
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

class PhabricatorRepositoryCommitHeraldWorker
  extends PhabricatorRepositoryCommitParserWorker {

  public function parseCommit(
    PhabricatorRepository $repository,
    PhabricatorRepositoryCommit $commit) {

    $data = id(new PhabricatorRepositoryCommitData())->loadOneWhere(
      'commitID = %d',
      $commit->getID());

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

    $email_phids = $adapter->getEmailPHIDs();
    if (!$email_phids) {
      return;
    }

    if ($repository->getDetail('herald-disabled')) {
      // This just means "disable email"; audits are (mostly) idempotent.
      return;
    }

    $xscript = $engine->getTranscript();

    $commit_name = $adapter->getHeraldName();
    $revision = $adapter->loadDifferentialRevision();

    $name = null;
    if ($revision) {
      $name = ' '.$revision->getTitle();
    }

    $author_phid = $data->getCommitDetail('authorPHID');
    $reviewer_phid = $data->getCommitDetail('reviewerPHID');

    $phids = array_filter(array($author_phid, $reviewer_phid));

    $handles = array();
    if ($phids) {
      $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();
    }

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

    $details = PhabricatorEnv::getProductionURI('/'.$commit_name);
    $differential = $revision
      ? PhabricatorEnv::getProductionURI('/D'.$revision->getID())
      : 'No revision.';

    $files = $adapter->loadAffectedPaths();
    sort($files);
    $files = implode("\n  ", $files);

    $xscript_id = $xscript->getID();

    $manage_uri = PhabricatorEnv::getProductionURI('/herald/view/commits/');
    $why_uri = PhabricatorEnv::getProductionURI(
      '/herald/transcript/'.$xscript_id.'/');

    $body = <<<EOBODY
DESCRIPTION
{$description}

DETAILS
  {$details}

DIFFERENTIAL REVISION
  {$differential}

AFFECTED FILES
  {$files}

MANAGE HERALD COMMIT RULES
  {$manage_uri}

WHY DID I GET THIS EMAIL?
  {$why_uri}

EOBODY;

    $subject = "[Herald/Commit] {$commit_name} ({$who}){$name}";

    $mailer = new PhabricatorMetaMTAMail();
    $mailer->setRelatedPHID($commit->getPHID());
    $mailer->addTos($email_phids);
    $mailer->setSubject($subject);
    $mailer->setBody($body);
    $mailer->setIsBulk(true);

    $mailer->addHeader('X-Herald-Rules', $xscript->getXHeraldRulesHeader());
    if ($author_phid) {
      $mailer->setFrom($author_phid);
    }

    $mailer->saveAndSend();
  }

  private function createAudits(
    PhabricatorRepositoryCommit $commit,
    array $map,
    array $rules) {

    $table = new PhabricatorOwnersPackageCommitRelationship();
    $rships = $table->loadAllWhere(
      'commitPHID = %s',
      $commit->getPHID());
    $rships = mpull($rships, null, 'getPackagePHID');

    $rules = mpull($rules, null, 'getID');
    foreach ($map as $phid => $rule_ids) {
      $rship = idx($rships, $phid);
      if ($rship) {
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

      $rship = new PhabricatorOwnersPackageCommitRelationship();
      $rship->setCommitPHID($commit->getPHID());
      $rship->setPackagePHID($phid);
      $rship->setAuditStatus(PhabricatorAuditStatusConstants::AUDIT_REQUIRED);
      $rship->setAuditReasons($reasons);
      $rship->save();
    }

    $commit->updateAuditStatus($rships);
    $commit->save();
  }
}
