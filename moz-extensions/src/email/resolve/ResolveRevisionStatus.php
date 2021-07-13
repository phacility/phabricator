<?php


class ResolveRevisionStatus {
  public DifferentialRevision $rawRevision;

  public function __construct(DifferentialRevision $rawRevision) {
    $this->rawRevision = $rawRevision;
  }

  public function resolveIsReadyToLand(): bool {
    return $this->rawRevision->getStatus() == DifferentialRevisionStatus::ACCEPTED;
  }

  public function resolveIsNeedingReview(): bool {
    return $this->rawRevision->getStatus() == DifferentialRevisionStatus::NEEDS_REVIEW;
  }

  public function resolveLandoLink(): string {
    $landoUri = PhabricatorEnv::getEnvConfig('lando-ui.url');
    return (string) (new PhutilURI($landoUri))
      ->setPath('/' . $this->rawRevision->getMonogram() . '/');
  }
}