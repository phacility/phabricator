<?php

final class HeraldFieldConfig {

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
  const FIELD_MERGE_REQUESTER        = 'merge-requester';

  public static function getFieldMap() {
    static $map = array(
      self::FIELD_TITLE                  => 'Title',
      self::FIELD_BODY                   => 'Body',
      self::FIELD_AUTHOR                 => 'Author',
      self::FIELD_REVIEWER               => 'Reviewer',
      self::FIELD_REVIEWERS              => 'Reviewers',
      self::FIELD_CC                     => 'CCs',
      self::FIELD_TAGS                   => 'Tags',
      self::FIELD_DIFF_FILE              => 'Any changed filename',
      self::FIELD_DIFF_CONTENT           => 'Any changed file content',
      self::FIELD_REPOSITORY             => 'Repository',
      self::FIELD_RULE                   => 'Another Herald rule',
      self::FIELD_AFFECTED_PACKAGE       => 'Any affected package',
      self::FIELD_AFFECTED_PACKAGE_OWNER => "Any affected package's owner",
      self::FIELD_NEED_AUDIT_FOR_PACKAGE =>
                                          'Affected packages that need audit',
      self::FIELD_DIFFERENTIAL_REVISION  => 'Differential revision',
      self::FIELD_DIFFERENTIAL_REVIEWERS => 'Differential reviewers',
      self::FIELD_DIFFERENTIAL_CCS       => 'Differential CCs',
      self::FIELD_MERGE_REQUESTER        => 'Merge requester'
    );

    return $map;
  }

  public static function getFieldMapForContentType($type) {
    $map = self::getFieldMap();

    switch ($type) {
      case HeraldContentTypeConfig::CONTENT_TYPE_DIFFERENTIAL:
        return array_select_keys(
          $map,
          array(
            self::FIELD_TITLE,
            self::FIELD_BODY,
            self::FIELD_AUTHOR,
            self::FIELD_REVIEWERS,
            self::FIELD_CC,
            self::FIELD_REPOSITORY,
            self::FIELD_DIFF_FILE,
            self::FIELD_DIFF_CONTENT,
            self::FIELD_RULE,
            self::FIELD_AFFECTED_PACKAGE,
            self::FIELD_AFFECTED_PACKAGE_OWNER,
          ));
      case HeraldContentTypeConfig::CONTENT_TYPE_COMMIT:
        return array_select_keys(
          $map,
          array(
            self::FIELD_BODY,
            self::FIELD_AUTHOR,
            self::FIELD_REVIEWER,
            self::FIELD_REPOSITORY,
            self::FIELD_DIFF_FILE,
            self::FIELD_DIFF_CONTENT,
            self::FIELD_RULE,
            self::FIELD_AFFECTED_PACKAGE,
            self::FIELD_AFFECTED_PACKAGE_OWNER,
            self::FIELD_NEED_AUDIT_FOR_PACKAGE,
            self::FIELD_DIFFERENTIAL_REVISION,
            self::FIELD_DIFFERENTIAL_REVIEWERS,
            self::FIELD_DIFFERENTIAL_CCS,
          ));
      case HeraldContentTypeConfig::CONTENT_TYPE_MERGE:
        return array_select_keys(
          $map,
          array(
            self::FIELD_BODY,
            self::FIELD_AUTHOR,
            self::FIELD_REVIEWER,
            self::FIELD_REPOSITORY,
            self::FIELD_DIFF_FILE,
            self::FIELD_DIFF_CONTENT,
            self::FIELD_RULE,
            self::FIELD_AFFECTED_PACKAGE,
            self::FIELD_AFFECTED_PACKAGE_OWNER,
            self::FIELD_DIFFERENTIAL_REVISION,
            self::FIELD_DIFFERENTIAL_REVIEWERS,
            self::FIELD_DIFFERENTIAL_CCS,
            self::FIELD_MERGE_REQUESTER,
          ));
      case HeraldContentTypeConfig::CONTENT_TYPE_OWNERS:
        return array_select_keys(
          $map,
          array(
            self::FIELD_AFFECTED_PACKAGE,
            self::FIELD_AFFECTED_PACKAGE_OWNER,
          ));
      default:
        throw new Exception("Unknown content type.");
    }
  }

}
