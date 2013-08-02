<?php

final class HeraldFieldConfig {

  // TODO: Remove; still required by conditions, etc.
  const FIELD_TITLE                  = 'title';
  const FIELD_BODY                   = 'body';
  const FIELD_AUTHOR                 = 'author';
  const FIELD_REVIEWER               = 'reviewer';
  const FIELD_REVIEWERS              = 'reviewers';
  const FIELD_CC                     = 'cc';
  const FIELD_TAGS                   = 'tags';
  const FIELD_DIFF_FILE              = 'diff-file';
  const FIELD_DIFF_CONTENT           = 'diff-content';
  const FIELD_REPOSITORY             = 'repository';
  const FIELD_RULE                   = 'rule';
  const FIELD_AFFECTED_PACKAGE       = 'affected-package';
  const FIELD_AFFECTED_PACKAGE_OWNER = 'affected-package-owner';
  const FIELD_NEED_AUDIT_FOR_PACKAGE  = 'need-audit-for-package';
  const FIELD_DIFFERENTIAL_REVISION  = 'differential-revision';
  const FIELD_DIFFERENTIAL_REVIEWERS = 'differential-reviewers';
  const FIELD_DIFFERENTIAL_CCS       = 'differential-ccs';

  // TODO: Remove; still required by transcripts.
  public static function getFieldMap() {
    $map = array(
      self::FIELD_TITLE                  => pht('Title'),
      self::FIELD_BODY                   => pht('Body'),
      self::FIELD_AUTHOR                 => pht('Author'),
      self::FIELD_REVIEWER               => pht('Reviewer'),
      self::FIELD_REVIEWERS              => pht('Reviewers'),
      self::FIELD_CC                     => pht('CCs'),
      self::FIELD_TAGS                   => pht('Tags'),
      self::FIELD_DIFF_FILE              => pht('Any changed filename'),
      self::FIELD_DIFF_CONTENT           => pht('Any changed file content'),
      self::FIELD_REPOSITORY             => pht('Repository'),
      self::FIELD_RULE                   => pht('Another Herald rule'),
      self::FIELD_AFFECTED_PACKAGE       => pht('Any affected package'),
      self::FIELD_AFFECTED_PACKAGE_OWNER =>
                                      pht("Any affected package's owner"),
      self::FIELD_NEED_AUDIT_FOR_PACKAGE =>
                                      pht('Affected packages that need audit'),
      self::FIELD_DIFFERENTIAL_REVISION  => pht('Differential revision'),
      self::FIELD_DIFFERENTIAL_REVIEWERS => pht('Differential reviewers'),
      self::FIELD_DIFFERENTIAL_CCS       => pht('Differential CCs'),
    );

    return $map;
  }

}
