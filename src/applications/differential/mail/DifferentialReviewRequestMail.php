<?php

abstract class DifferentialReviewRequestMail extends DifferentialMail {

  const MAX_AFFECTED_FILES = 1000;

  protected $commentText;

  private $patch;

  public function setCommentText($comment_text) {
    $this->commentText = $comment_text;
    return $this;
  }

  public function getCommentText() {
    return $this->commentText;
  }

  public function __construct(
    DifferentialRevision $revision,
    PhabricatorObjectHandle $actor,
    array $changesets) {
    assert_instances_of($changesets, 'DifferentialChangeset');

    $this->setRevision($revision);
    $this->setActorHandle($actor);
    $this->setChangesets($changesets);
  }

  protected function prepareBody() {
    parent::prepareBody();

    $inline_max_length = PhabricatorEnv::getEnvConfig(
      'metamta.differential.inline-patches');
    if ($inline_max_length) {
      $patch = $this->buildPatch();
      if (count(explode("\n", $patch)) <= $inline_max_length) {
        $this->patch = $patch;
      }
    }
  }

  protected function renderReviewRequestBody() {
    $revision = $this->getRevision();

    $body = array();
    if (!$this->isFirstMailToRecipients()) {
      if (strlen($this->getCommentText())) {
        $body[] = $this->formatText($this->getCommentText());
        $body[] = null;
      }
    }

    $phase = ($this->isFirstMailToRecipients() ?
      DifferentialMailPhase::WELCOME :
      DifferentialMailPhase::UPDATE);
    $body[] = $this->renderAuxFields($phase);

    $changesets = $this->getChangesets();
    if ($changesets) {
      $body[] = 'AFFECTED FILES';
      $max = self::MAX_AFFECTED_FILES;
      foreach (array_values($changesets) as $i => $changeset) {
        if ($i == $max) {
          $body[] = '  ('.(count($changesets) - $max).' more files)';
          break;
        }
        $body[] = '  '.$changeset->getFilename();
      }
      $body[] = null;
    }

    if ($this->patch) {
      $body[] = 'CHANGE DETAILS';
      $body[] = $this->patch;
    }

    return implode("\n", $body);
  }

  protected function buildAttachments() {
    $attachments = array();

    if (PhabricatorEnv::getEnvConfig('metamta.differential.attach-patches')) {

      $revision = $this->getRevision();
      $revision_id = $revision->getID();

      $diffs = id(new DifferentialDiffQuery())
        ->setViewer($this->getActor())
        ->withRevisionIDs(array($revision_id))
        ->execute();
      $diff_number = count($diffs);

      $attachments[] = new PhabricatorMetaMTAAttachment(
        $this->buildPatch(),
        "D{$revision_id}.{$diff_number}.patch",
        'text/x-patch; charset=utf-8'
      );
    }

    return $attachments;
  }

  private function buildPatch() {
    $renderer = new DifferentialRawDiffRenderer();
    $renderer->setChangesets($this->getChangesets());
    $renderer->setFormat(
      PhabricatorEnv::getEnvConfig('metamta.differential.patch-format'));

    // TODO: It would be nice to have a real viewer here eventually, but
    // in the meantime anyone we're sending mail to can certainly see the
    // patch.
    $renderer->setViewer(PhabricatorUser::getOmnipotentUser());
    return $renderer->buildPatch();
  }

  protected function getMailTags() {
    $tags = array();
    if ($this->isFirstMailToRecipients()) {
      $tags[] = MetaMTANotificationType::TYPE_DIFFERENTIAL_REVIEW_REQUEST;
    } else {
      $tags[] = MetaMTANotificationType::TYPE_DIFFERENTIAL_UPDATED;
    }
    return $tags;
  }

}
