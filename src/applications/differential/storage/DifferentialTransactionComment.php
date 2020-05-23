<?php

final class DifferentialTransactionComment
  extends PhabricatorApplicationTransactionComment
  implements
    PhabricatorInlineCommentInterface {

  protected $revisionPHID;
  protected $changesetID;
  protected $isNewFile = 0;
  protected $lineNumber = 0;
  protected $lineLength = 0;
  protected $fixedState;
  protected $hasReplies = 0;
  protected $replyToCommentPHID;
  protected $attributes = array();

  private $replyToComment = self::ATTACHABLE;
  private $isHidden = self::ATTACHABLE;
  private $changeset = self::ATTACHABLE;
  private $inlineContext = self::ATTACHABLE;

  public function getApplicationTransactionObject() {
    return new DifferentialTransaction();
  }

  public function attachReplyToComment(
    DifferentialTransactionComment $comment = null) {
    $this->replyToComment = $comment;
    return $this;
  }

  public function getReplyToComment() {
    return $this->assertAttached($this->replyToComment);
  }

  protected function getConfiguration() {
    $config = parent::getConfiguration();

    $config[self::CONFIG_COLUMN_SCHEMA] = array(
      'revisionPHID' => 'phid?',
      'changesetID' => 'id?',
      'isNewFile' => 'bool',
      'lineNumber' => 'uint32',
      'lineLength' => 'uint32',
      'fixedState' => 'text12?',
      'hasReplies' => 'bool',
      'replyToCommentPHID' => 'phid?',
    ) + $config[self::CONFIG_COLUMN_SCHEMA];

    $config[self::CONFIG_KEY_SCHEMA] = array(
      'key_draft' => array(
        'columns' => array('authorPHID', 'transactionPHID'),
      ),
      'key_changeset' => array(
        'columns' => array('changesetID'),
      ),
      'key_revision' => array(
        'columns' => array('revisionPHID'),
      ),
    ) + $config[self::CONFIG_KEY_SCHEMA];

    $config[self::CONFIG_SERIALIZATION] = array(
      'attributes' => self::SERIALIZATION_JSON,
    ) + idx($config, self::CONFIG_SERIALIZATION, array());

    return $config;
  }

  public function shouldUseMarkupCache($field) {
    // Only cache submitted comments.
    return ($this->getTransactionPHID() != null);
  }

  public static function sortAndGroupInlines(
    array $inlines,
    array $changesets) {
    assert_instances_of($inlines, 'DifferentialTransaction');
    assert_instances_of($changesets, 'DifferentialChangeset');

    $changesets = mpull($changesets, null, 'getID');
    $changesets = msort($changesets, 'getFilename');

    // Group the changesets by file and reorder them by display order.
    $inline_groups = array();
    foreach ($inlines as $inline) {
      $changeset_id = $inline->getComment()->getChangesetID();
      $inline_groups[$changeset_id][] = $inline;
    }
    $inline_groups = array_select_keys($inline_groups, array_keys($changesets));

    foreach ($inline_groups as $changeset_id => $group) {
      // Sort the group of inlines by line number.
      $items = array();
      foreach ($group as $inline) {
        $comment = $inline->getComment();
        $num = $comment->getLineNumber();
        $len = $comment->getLineLength();
        $id = $comment->getID();

        $items[] = array(
          'inline' => $inline,
          'sort' => sprintf('~%010d%010d%010d', $num, $len, $id),
        );
      }

      $items = isort($items, 'sort');
      $items = ipull($items, 'inline');
      $inline_groups[$changeset_id] = $items;
    }

    return $inline_groups;
  }

  public function getIsHidden() {
    return $this->assertAttached($this->isHidden);
  }

  public function attachIsHidden($hidden) {
    $this->isHidden = $hidden;
    return $this;
  }

  public function getAttribute($key, $default = null) {
    return idx($this->attributes, $key, $default);
  }

  public function setAttribute($key, $value) {
    $this->attributes[$key] = $value;
    return $this;
  }

  public function newInlineCommentObject() {
    return DifferentialInlineComment::newFromModernComment($this);
  }

  public function getInlineContext() {
    return $this->assertAttached($this->inlineContext);
  }

  public function attachInlineContext(
    PhabricatorInlineCommentContext $context = null) {
    $this->inlineContext = $context;
    return $this;
  }


  public function isEmptyComment() {
    if (!parent::isEmptyComment()) {
      return false;
    }

    return $this->newInlineCommentObject()
      ->getContentState()
      ->isEmptyContentState();
  }


}
