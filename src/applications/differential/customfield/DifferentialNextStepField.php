<?php

final class DifferentialNextStepField
  extends DifferentialCustomField {

  public function getFieldKey() {
    return 'differential:next-step';
  }

  public function getFieldName() {
    return pht('Next Step');
  }

  public function getFieldDescription() {
    return pht('Provides a hint for the next step to take.');
  }

  public function shouldAppearInPropertyView() {
    return true;
  }

  public function renderPropertyViewLabel() {
    return $this->getFieldName();
  }

  public function renderPropertyViewValue(array $handles) {
    $revision = $this->getObject();
    $diff = $revision->getActiveDiff();

    $status = $revision->getStatus();
    if ($status != ArcanistDifferentialRevisionStatus::ACCEPTED) {
      return null;
    }

    $local_vcs = $diff->getSourceControlSystem();
    switch ($local_vcs) {
      case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
        $bookmark = $diff->getBookmark();
        if (strlen($bookmark)) {
          $next_step = csprintf('arc land %R', $bookmark);
        } else {
          $next_step = csprintf('arc land');
        }
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
        $branch = $diff->getBranch();
        if (strlen($branch)) {
          $next_step = csprintf('arc land %R', $branch);
        } else {
          $next_step = csprintf('arc land');
        }
        break;
      case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
        $next_step = csprintf('arc commit');
        break;
      default:
        return null;
    }

    $next_step = phutil_tag('tt', array(), (string)$next_step);

    return $next_step;
  }

}
