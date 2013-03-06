<?php

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

  public function moveAway($new_doc_id) {
    return $this->execute(
      PhrictionChangeType::CHANGE_MOVE_AWAY, true, $new_doc_id);
  }

  public function moveHere($old_doc_id) {
    return $this->execute(
      PhrictionChangeType::CHANGE_MOVE_HERE, false, $old_doc_id);
  }

  private function execute(
    $change_type, $del_new_content = true, $doc_ref = null) {

    $actor = $this->requireActor();

    $document = $this->document;
    $content  = $this->content;

    $new_content = $this->buildContentTemplate($document, $content);
    $new_content->setChangeType($change_type);

    if ($del_new_content) {
      $new_content->setContent('');
    }

    if ($doc_ref) {
      $new_content->setChangeRef($doc_ref);
    }

    return $this->updateDocument($document, $content, $new_content);
  }

  public function delete() {
    return $this->execute(PhrictionChangeType::CHANGE_DELETE, true);
  }

  private function stub() {
    return $this->execute(PhrictionChangeType::CHANGE_STUB, true);
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
      case PhrictionChangeType::CHANGE_STUB:
        $doc_status = PhrictionDocumentStatus::STATUS_STUB;
        $feed_action = null;
        break;
      case PhrictionChangeType::CHANGE_MOVE_AWAY:
        $doc_status = PhrictionDocumentStatus::STATUS_MOVED;
        $feed_action = PhrictionActionConstants::ACTION_MOVE_AWAY;
        break;
      case PhrictionChangeType::CHANGE_MOVE_HERE:
        $doc_status = PhrictionDocumentStatus::STATUS_EXISTS;
        $feed_action = null;
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

    id(new PhabricatorSearchIndexer())
      ->indexDocumentByPHID($document->getPHID());

    // Stub out empty parent documents if they don't exist
    $ancestral_slugs = PhabricatorSlug::getAncestry($document->getSlug());
    if ($ancestral_slugs) {
      $ancestors = id(new PhrictionDocument())->loadAllWhere(
        'slug IN (%Ls)',
        $ancestral_slugs);
      $ancestors = mpull($ancestors, null, 'getSlug');
      foreach ($ancestral_slugs as $slug) {
        // We check for change type to prevent near-infinite recursion
        if (!isset($ancestors[$slug]) &&
          $new_content->getChangeType() != PhrictionChangeType::CHANGE_STUB) {

          id(PhrictionDocumentEditor::newForSlug($slug))
            ->setActor($this->getActor())
            ->setTitle(PhabricatorSlug::getDefaultTitle($slug))
            ->setContent('')
            ->setDescription(pht('Empty Parent Document'))
            ->stub();
        }
      }
    }

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

    if ($feed_action) {
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
    }

    return $this;
  }

}
