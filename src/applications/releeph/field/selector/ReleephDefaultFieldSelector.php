<?php

final class ReleephDefaultFieldSelector extends ReleephFieldSelector {

  /**
   * Determine if this install is Facebook.
   *
   * TODO: This is a giant hacky mess because I am dumb and moved forward on
   * Releeph changes with partial information. Recover from this as gracefully
   * as possible. This obviously is an abomination. -epriestley
   */
  public static function isFacebook() {
    return class_exists('ReleephFacebookKarmaFieldSpecification');
  }

  /**
   * @phutil-external-symbol class ReleephFacebookKarmaFieldSpecification
   * @phutil-external-symbol class ReleephFacebookSeverityFieldSpecification
   * @phutil-external-symbol class ReleephFacebookTagFieldSpecification
   * @phutil-external-symbol class ReleephFacebookTasksFieldSpecification
   */
  public function getFieldSpecifications() {
    if (self::isFacebook()) {
      return array(
        new ReleephCommitMessageFieldSpecification(),
        new ReleephSummaryFieldSpecification(),
        new ReleephReasonFieldSpecification(),
        new ReleephAuthorFieldSpecification(),
        new ReleephRevisionFieldSpecification(),
        new ReleephRequestorFieldSpecification(),
        new ReleephFacebookKarmaFieldSpecification(),
        new ReleephFacebookSeverityFieldSpecification(),
        new ReleephOriginalCommitFieldSpecification(),
        new ReleephDiffMessageFieldSpecification(),
        new ReleephIntentFieldSpecification(),
        new ReleephBranchCommitFieldSpecification(),
        new ReleephDiffSizeFieldSpecification(),
        new ReleephDiffChurnFieldSpecification(),
        new ReleephDependsOnFieldSpecification(),
        new ReleephFacebookTagFieldSpecification(),
        new ReleephFacebookTasksFieldSpecification(),
      );
    } else {
      return array(
        new ReleephCommitMessageFieldSpecification(),
        new ReleephSummaryFieldSpecification(),
        new ReleephReasonFieldSpecification(),
        new ReleephAuthorFieldSpecification(),
        new ReleephRevisionFieldSpecification(),
        new ReleephRequestorFieldSpecification(),
        new ReleephSeverityFieldSpecification(),
        new ReleephOriginalCommitFieldSpecification(),
        new ReleephDiffMessageFieldSpecification(),
        new ReleephIntentFieldSpecification(),
        new ReleephBranchCommitFieldSpecification(),
        new ReleephDiffSizeFieldSpecification(),
        new ReleephDiffChurnFieldSpecification(),
      );
    }
  }

  public function sortFieldsForCommitMessage(array $fields) {
    return self::selectFields($fields, array(
      'ReleephCommitMessageFieldSpecification',
      'ReleephRequestorFieldSpecification',
      'ReleephIntentFieldSpecification',
      'ReleephReasonFieldSpecification',
    ));
  }

}
