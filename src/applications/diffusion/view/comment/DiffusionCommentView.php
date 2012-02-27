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

final class DiffusionCommentView extends AphrontView {

  private $user;
  private $comment;
  private $commentNumber;
  private $handles;
  private $isPreview;

  private $engine;

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function setComment(PhabricatorAuditComment $comment) {
    $this->comment = $comment;
    return $this;
  }

  public function setCommentNumber($comment_number) {
    $this->commentNumber = $comment_number;
    return $this;
  }

  public function setHandles(array $handles) {
    $this->handles = $handles;
    return $this;
  }

  public function setIsPreview($is_preview) {
    $this->isPreview = $is_preview;
    return $this;
  }

  public function getRequiredHandlePHIDs() {
    return array($this->comment->getActorPHID());
  }

  private function getHandle($phid) {
    if (empty($this->handles[$phid])) {
      throw new Exception("Unloaded handle '{$phid}'!");
    }
    return $this->handles[$phid];
  }

  public function render() {
    $comment = $this->comment;
    $author = $this->getHandle($comment->getActorPHID());
    $author_link = $author->renderLink();

    $actions = $this->renderActions();
    $content = $this->renderContent();
    $classes = $this->renderClasses();

    $xaction_view = id(new PhabricatorTransactionView())
      ->setUser($this->user)
      ->setImageURI($author->getImageURI())
      ->setActions($actions)
      ->appendChild($content);

    if ($this->isPreview) {
      $xaction_view->setIsPreview(true);
    } else {
      $xaction_view
        ->setAnchor('comment-'.$this->commentNumber, '#'.$this->commentNumber)
        ->setEpoch($comment->getDateCreated());
    }

    foreach ($classes as $class) {
      $xaction_view->addClass($class);
    }

    return $xaction_view->render();
  }

  private function renderActions() {
    $comment = $this->comment;
    $author = $this->getHandle($comment->getActorPHID());
    $author_link = $author->renderLink();

    $actions = array();
    switch ($comment->getAction()) {
      case PhabricatorAuditActionConstants::ACCEPT:
        $actions[] = "{$author_link} accepted this commit.";
        break;
      case PhabricatorAuditActionConstants::CONCERN:
        $actions[] = "{$author_link} raised concerns with this commit.";
        break;
      case PhabricatorAuditActionConstants::COMMENT:
      default:
        $actions[] = "{$author_link} commented on this commit.";
        break;
    }

    foreach ($actions as $key => $action) {
      $actions[$key] = '<div>'.$action.'</div>';
    }

    return $actions;
  }

  private function renderContent() {
    $comment = $this->comment;
    $engine = $this->getEngine();

    return
      '<div class="phabricator-remarkup">'.
        $engine->markupText($comment->getContent()).
      '</div>';
  }

  private function getEngine() {
    if (!$this->engine) {
      $this->engine = PhabricatorMarkupEngine::newDiffusionMarkupEngine();
    }
    return $this->engine;
  }

  private function renderClasses() {
    $comment = $this->comment;

    $classes = array();
    switch ($comment->getAction()) {
      case PhabricatorAuditActionConstants::ACCEPT:
        $classes[] = 'audit-accept';
        break;
      case PhabricatorAuditActionConstants::CONCERN:
        $classes[] = 'audit-concern';
        break;
    }

    return $classes;
  }




}
