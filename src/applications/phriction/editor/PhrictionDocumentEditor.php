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

/**
 * Create or update Phriction documents.
 *
 * @group phriction
 */
final class PhrictionDocumentEditor extends PhabricatorEditor {

  private $document;
  private $content;

  private $newTitle;
  private $newContent;
  private $description;

  private function __construct() {
    // <restricted>
  }

  public static function newForSlug($slug) {
    $slug = PhabricatorSlug::normalize($slug);
    $document = id(new PhrictionDocument())->loadOneWhere(
      'slug = %s',
      $slug);
    $content = null;

    if ($document) {
      $content = id(new PhrictionContent())->load($document->getContentID());
    } else {
      $document = new PhrictionDocument();
      $document->setSlug($slug);
    }

    if (!$content) {
      $default_title = PhabricatorSlug::getDefaultTitle($slug);
      $content = new PhrictionContent();
      $content->setSlug($slug);
      $content->setTitle($default_title);
      $content->setContent('');
    }

    $obj = new PhrictionDocumentEditor();
    $obj->document = $document;
    $obj->content  = $content;

    return $obj;
  }

  public function setTitle($title) {
    $this->newTitle = $title;
    return $this;
  }

  public function setContent($content) {
    $this->newContent = $content;
    return $this;
  }

  public function setDescription($description) {
    $this->description = $description;
    return $this;
  }

  public function getDocument() {
    return $this->document;
  }

  public function delete() {
    $actor = $this->requireActor();

    // TODO: Should we do anything about deleting an already-deleted document?
    // We currently allow it.

    $document = $this->document;
    $content  = $this->content;

    $new_content = $this->buildContentTemplate($document, $content);

    $new_content->setChangeType(PhrictionChangeType::CHANGE_DELETE);
    $new_content->setContent('');

    return $this->updateDocument($document, $content, $new_content);
  }

  public function save() {
    $actor = $this->requireActor();

    if ($this->newContent === '') {
      // If this is an edit which deletes all the content, just treat it as
      // a delete. NOTE: null means "don't change the content", not "delete
      // the page"! Thus the strict type check.
      return $this->delete();
    }

    $document = $this->document;
    $content  = $this->content;

    $new_content = $this->buildContentTemplate($document, $content);

    return $this->updateDocument($document, $content, $new_content);
  }

  private function buildContentTemplate(
    PhrictionDocument $document,
    PhrictionContent $content) {

    $new_content = new PhrictionContent();
    $new_content->setSlug($document->getSlug());
    $new_content->setAuthorPHID($this->getActor()->getPHID());
    $new_content->setChangeType(PhrictionChangeType::CHANGE_EDIT);

    $new_content->setTitle(
      coalesce(
        $this->newTitle,
        $content->getTitle()));

    $new_content->setContent(
      coalesce(
        $this->newContent,
        $content->getContent()));

    if (strlen($this->description)) {
      $new_content->setDescription($this->description);
    }

    return $new_content;
  }

  private function updateDocument($document, $content, $new_content) {

    $is_new = false;
    if (!$document->getID()) {
      $is_new = true;
    }

    $new_content->setVersion($content->getVersion() + 1);

    $change_type = $new_content->getChangeType();
    switch ($change_type) {
      case PhrictionChangeType::CHANGE_EDIT:
        $doc_status = PhrictionDocumentStatus::STATUS_EXISTS;
        $feed_action = $is_new
          ? PhrictionActionConstants::ACTION_CREATE
          : PhrictionActionConstants::ACTION_EDIT;
        break;
      case PhrictionChangeType::CHANGE_DELETE:
        $doc_status = PhrictionDocumentStatus::STATUS_DELETED;
        $feed_action = PhrictionActionConstants::ACTION_DELETE;
        if ($is_new) {
          throw new Exception(
            "You can not delete a document which doesn't exist yet!");
        }
        break;
      default:
        throw new Exception(
          "Unsupported content change type '{$change_type}'!");
    }

    $document->setStatus($doc_status);

    // TODO: This should be transactional.

    if ($is_new) {
      $document->save();
    }

    $new_content->setDocumentID($document->getID());
    $new_content->save();

    $document->setContentID($new_content->getID());
    $document->save();

    $document->attachContent($new_content);
    PhabricatorSearchPhrictionIndexer::indexDocument($document);

    $project_phid = null;
    $slug = $document->getSlug();
    if (PhrictionDocument::isProjectSlug($slug)) {
      $project = id(new PhabricatorProject())->loadOneWhere(
        'phrictionSlug = %s',
        PhrictionDocument::getProjectSlugIdentifier($slug));
      if ($project) {
        $project_phid = $project->getPHID();
      }
    }

    $related_phids = array(
      $document->getPHID(),
      $this->getActor()->getPHID(),
    );

    if ($project_phid) {
      $related_phids[] = $project_phid;
    }

    id(new PhabricatorFeedStoryPublisher())
      ->setRelatedPHIDs($related_phids)
      ->setStoryAuthorPHID($this->getActor()->getPHID())
      ->setStoryTime(time())
      ->setStoryType(PhabricatorFeedStoryTypeConstants::STORY_PHRICTION)
      ->setStoryData(
        array(
          'phid'    => $document->getPHID(),
          'action'  => $feed_action,
          'content' => phutil_utf8_shorten($new_content->getContent(), 140),
          'project' => $project_phid,
        ))
      ->publish();

    return $this;
  }

}
