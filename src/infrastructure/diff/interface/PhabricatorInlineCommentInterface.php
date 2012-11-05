<?php

/**
 * Shared interface used by Differential and Diffusion inline comments.
 */
interface PhabricatorInlineCommentInterface extends PhabricatorMarkupInterface {

  const MARKUP_FIELD_BODY = 'markup:body';

  public function setChangesetID($id);
  public function getChangesetID();

  public function setIsNewFile($is_new);
  public function getIsNewFile();

  public function setLineNumber($number);
  public function getLineNumber();

  public function setLineLength($length);
  public function getLineLength();

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

}
