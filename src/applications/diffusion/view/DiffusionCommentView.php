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
  private $pathMap;

  private $inlineComments;
  private $markupEngine;

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
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }

  public function setIsPreview($is_preview) {
    $this->isPreview = $is_preview;
    return $this;
  }

  public function setInlineComments(array $inline_comments) {
    assert_instances_of($inline_comments, 'PhabricatorInlineCommentInterface');
    $this->inlineComments = $inline_comments;
    return $this;
  }

  public function setPathMap(array $path_map) {
    $this->pathMap = $path_map;
    return $this;
  }

  public function setMarkupEngine(PhabricatorMarkupEngine $markup_engine) {
    $this->markupEngine = $markup_engine;
    return $this;
  }

  public function getMarkupEngine() {
    return $this->markupEngine;
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

    $action = $comment->getAction();
    $verb = PhabricatorAuditActionConstants::getActionPastTenseVerb($action);

    $metadata = $comment->getMetadata();
    $added_auditors = idx(
      $metadata,
      PhabricatorAuditComment::METADATA_ADDED_AUDITORS,
      array());
    $added_ccs = idx(
      $metadata,
      PhabricatorAuditComment::METADATA_ADDED_CCS,
      array());

    $actions = array();
    if ($action == PhabricatorAuditActionConstants::ADD_CCS) {
      $rendered_ccs = $this->renderHandleList($added_ccs);
      $actions[] = "{$author_link} added CCs: {$rendered_ccs}.";
    } else if ($action == PhabricatorAuditActionConstants::ADD_AUDITORS) {
      $rendered_auditors = $this->renderHandleList($added_auditors);
      $actions[] = "{$author_link} added auditors: ".
        "{$rendered_auditors}.";
    } else {
      $actions[] = "{$author_link} ".phutil_escape_html($verb)." this commit.";
    }

    foreach ($actions as $key => $action) {
      $actions[$key] = '<div>'.$action.'</div>';
    }

    return $actions;
  }

  private function renderContent() {
    $comment = $this->comment;
    $engine = $this->getMarkupEngine();

    if (!strlen($comment->getContent()) && empty($this->inlineComments)) {
      return null;
    } else {
      return
        '<div class="phabricator-remarkup">'.
          $engine->getOutput(
            $comment,
            PhabricatorAuditComment::MARKUP_FIELD_BODY).
          $this->renderSingleView($this->renderInlines()).
        '</div>';
    }
  }

  private function renderInlines() {
    if (!$this->inlineComments) {
      return null;
    }

    $engine = $this->getMarkupEngine();

    $inlines_by_path = mgroup($this->inlineComments, 'getPathID');

    $view = new PhabricatorInlineSummaryView();
    foreach ($inlines_by_path as $path_id => $inlines) {
      $path = idx($this->pathMap, $path_id);
      if ($path === null) {
        continue;
      }

      $items = array();
      foreach ($inlines as $inline) {
        $items[] = array(
          'id'      => $inline->getID(),
          'line'    => $inline->getLineNumber(),
          'length'  => $inline->getLineLength(),
          'content' => $engine->getOutput(
            $inline,
            PhabricatorInlineCommentInterface::MARKUP_FIELD_BODY),
        );
      }

      $view->addCommentGroup($path, $items);
    }

    return $view;
  }

  private function renderHandleList(array $phids) {
    $result = array();
    foreach ($phids as $phid) {
      $result[] = $this->handles[$phid]->renderLink();
    }
    return implode(', ', $result);
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
