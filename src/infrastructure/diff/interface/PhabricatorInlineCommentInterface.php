<?php

/**
 * Shared interface used by Differential and Diffusion inline comments.
 */
interface PhabricatorInlineCommentInterface extends PhabricatorMarkupInterface {

  const MARKUP_FIELD_BODY = 'markup:body';

  const STATE_UNDONE = 'undone';
  const STATE_DRAFT = 'draft';
  const STATE_UNDRAFT = 'undraft';
  const STATE_DONE = 'done';

  public function setChangesetID($id);
  public function getChangesetID();

  public function setIsNewFile($is_new);
  public function getIsNewFile();

  public function setLineNumber($number);
  public function getLineNumber();

  public function setLineLength($length);
  public function getLineLength();

  public function setReplyToCommentPHID($phid);
  public function getReplyToCommentPHID();

  public function setHasReplies($has_replies);
  public function getHasReplies();

  public function setIsDeleted($deleted);
  public function getIsDeleted();

  public function setFixedState($state);
  public function getFixedState();

  public function setContent($content);
  public function getContent();

  public function setCache($cache);
  public function getCache();

  public function setAuthorPHID($phid);
  public function getAuthorPHID();

  public function setSyntheticAuthor($synthetic_author);
  public function getSyntheticAuthor();

  public function isCompatible(PhabricatorInlineCommentInterface $inline);
  public function isDraft();

  public function save();
  public function delete();

  public function setIsGhost($is_ghost);
  public function getIsGhost();

}
