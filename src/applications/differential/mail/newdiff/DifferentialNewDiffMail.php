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

final class DifferentialNewDiffMail extends DifferentialReviewRequestMail {

  protected function renderSubject() {
    $revision = $this->getRevision();
    $line_count = $revision->getLineCount();
    $lines = ($line_count == 1 ? "1 line" : "{$line_count} lines");

    if ($this->isFirstMailToRecipients()) {
      $verb = 'Request';
    } else {
      $verb = 'Updated';
    }

    $revision_id = $revision->getID();
    $revision_title = $revision->getTitle();

    return "[{$verb}, {$lines}] D{$revision_id}: {$revision_title}";
  }

  protected function buildSubject() {
    if (!$this->isFirstMailToRecipients()) {
      return parent::buildSubject();
    }

    $prefix = $this->getSubjectPrefix();
    $subject = $this->renderSubject();

    return trim("{$prefix} {$subject}");
  }

  protected function renderBody() {
    $actor = $this->getActorName();

    $name  = $this->getRevision()->getTitle();

    $body = array();

    if ($this->isFirstMailToRecipients()) {
      $body[] = "{$actor} requested code review of \"{$name}\".";
    } else {
      $body[] = "{$actor} updated the revision \"{$name}\".";
    }
    $body[] = $this->renderReviewersLine();
    $body[] = null;

    $body[] = $this->renderReviewRequestBody();

    return implode("\n", $body);
  }
}
