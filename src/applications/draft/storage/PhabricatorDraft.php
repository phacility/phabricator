<?php

final class PhabricatorDraft extends PhabricatorDraftDAO {

  protected $authorPHID;
  protected $draftKey;
  protected $draft;
  protected $metadata = array();

  public function getConfiguration() {
    return array(
      self::CONFIG_SERIALIZATION => array(
        'metadata' => self::SERIALIZATION_JSON,
      ),
    ) + parent::getConfiguration();
  }

  public function replaceOrDelete() {
    if ($this->draft == '' && !array_filter($this->metadata)) {
      queryfx(
        $this->establishConnection('w'),
        'DELETE FROM %T WHERE authorPHID = %s AND draftKey = %s',
        $this->getTableName(),
        $this->authorPHID,
        $this->draftKey);
      return $this;
    }
    return parent::replace();
  }

  public static function newFromUserAndKey(PhabricatorUser $user, $key) {
    if ($user->getPHID() && strlen($key)) {
      $draft = id(new PhabricatorDraft())->loadOneWhere(
        'authorPHID = %s AND draftKey = %s',
        $user->getPHID(),
        $key);
      if ($draft) {
        return $draft;
      }
    }

    $draft = new PhabricatorDraft();
    if ($user->getPHID()) {
      $draft
        ->setAuthorPHID($user->getPHID())
        ->setDraftKey($key);
    }

    return $draft;
  }

  public static function buildFromRequest(AphrontRequest $request) {
    $user = $request->getUser();
    if (!$user->getPHID()) {
      return null;
    }

    if (!$request->getStr('__draft__')) {
      return null;
    }

    $draft = id(new PhabricatorDraft())
      ->setAuthorPHID($user->getPHID())
      ->setDraftKey($request->getStr('__draft__'));

    // If this is a preview, add other data. If not, leave the draft empty so
    // that replaceOrDelete() will delete it.
    if ($request->isPreviewRequest()) {
      $other_data = $request->getPassthroughRequestData();
      unset($other_data['comment']);

      $draft
        ->setDraft($request->getStr('comment'))
        ->setMetadata($other_data);
    }

    return $draft;
  }

}
