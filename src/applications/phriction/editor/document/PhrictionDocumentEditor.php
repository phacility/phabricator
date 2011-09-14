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
 * Create or update Phriction documents.
 *
 * @group phriction
 */
final class PhrictionDocumentEditor {

  private $document;
  private $content;

  private $user;

  private $newTitle;
  private $newContent;
  private $description;

  private function __construct() {
    // <restricted>
  }

  public static function newForSlug($slug) {
    $slug = PhrictionDocument::normalizeSlug($slug);
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
      $default_title = PhrictionDocument::getDefaultSlugTitle($slug);
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

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
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

  public function save() {
    if (!$this->user) {
      throw new Exception("Call setUser() before save()!");
    }

    $document = $this->document;
    $content  = $this->content;

    $new_content = new PhrictionContent();
    $new_content->setSlug($document->getSlug());
    $new_content->setAuthorPHID($this->user->getPHID());

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

    $new_content->setVersion($content->getVersion() + 1);

    // TODO: This should be transactional.
    $is_new = false;
    if (!$document->getID()) {
      $is_new = true;
      $document->save();
    }

    $new_content->setDocumentID($document->getID());
    $new_content->save();

    $document->setContentID($new_content->getID());
    $document->save();

    $document->attachContent($new_content);
    PhabricatorSearchPhrictionIndexer::indexDocument($document);

    id(new PhabricatorFeedStoryPublisher())
      ->setRelatedPHIDs(
        array(
          $document->getPHID(),
          $this->user->getPHID(),
        ))
      ->setStoryAuthorPHID($this->user->getPHID())
      ->setStoryTime(time())
      ->setStoryType(PhabricatorFeedStoryTypeConstants::STORY_PHRICTION)
      ->setStoryData(
        array(
          'phid'    => $document->getPHID(),
          'action'  => $is_new
            ? PhrictionActionConstants::ACTION_CREATE
            : PhrictionActionConstants::ACTION_EDIT,
          'content' => phutil_utf8_shorten($new_content->getContent(), 140),
        ))
      ->publish();

    return $this;
  }

}
