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

class DifferentialCommentMail extends DifferentialMail {

  protected $changedByCommit;

  public function setChangedByCommit($changed_by_commit) {
    $this->changedByCommit = $changed_by_commit;
    return $this;
  }

  public function getChangedByCommit() {
    return $this->changedByCommit;
  }

  public function __construct(
    DifferentialRevision $revision,
    PhabricatorObjectHandle $actor,
    DifferentialComment $comment,
    array $changesets,
    array $inline_comments) {

    $this->setRevision($revision);
    $this->setActorHandle($actor);
    $this->setComment($comment);
    $this->setChangesets($changesets);
    $this->setInlineComments($inline_comments);

  }

  protected function renderSubject() {
    $revision = $this->getRevision();
    $verb = $this->getVerb();
    return ucwords($verb).': '.$revision->getTitle();
  }

  protected function getVerb() {
    $comment = $this->getComment();
    $action = $comment->getAction();
    $verb = DifferentialAction::getActionPastTenseVerb($action);
    return $verb;
  }

  protected function renderBody() {

    $comment = $this->getComment();

    $actor = $this->getActorName();
    $name  = $this->getRevision()->getTitle();
    $verb  = $this->getVerb();

    $body  = array();

    $body[] = "{$actor} has {$verb} the revision \"{$name}\".";
    $body[] = null;

    $content = $comment->getContent();
    if (strlen($content)) {
      $body[] = $this->formatText($content);
      $body[] = null;
    }

    if ($this->getChangedByCommit()) {
      $body[] = 'CHANGED PRIOR TO COMMIT';
      $body[] = '  This revision was updated prior to commit.';
      $body[] = null;
    }

    $inlines = $this->getInlineComments();
    if ($inlines) {
      $body[] = 'INLINE COMMENTS';
      $changesets = $this->getChangesets();
      foreach ($inlines as $inline) {
        $changeset = $changesets[$inline->getChangesetID()];
        if (!$changeset) {
          throw new Exception('Changeset missing!');
        }
        $file = $changeset->getFilename();
        $line = $inline->renderLineRange();
        $content = $inline->getContent();
        $body[] = $this->formatText("{$file}:{$line} {$content}");
      }
      $body[] = null;
    }

    $body[] = $this->renderRevisionDetailLink();
    $revision = $this->getRevision();
    if ($revision->getStatus() == DifferentialRevisionStatus::COMMITTED) {
      /*
      
      TODO
      
      $rev_ref = $revision->getRevisionRef();
      if ($rev_ref) {
        $body[] = "  Detail URL: ".$rev_ref->getDetailURL();
      }
      */
    }
    $body[] = null;

    return implode("\n", $body);
  }
}
