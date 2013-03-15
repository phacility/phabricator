<?php

final class ReleephDefaultFieldSelector extends ReleephFieldSelector {

  public function getFieldSpecifications() {
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
      new ReleephStatusFieldSpecification(),
      new ReleephIntentFieldSpecification(),
      new ReleephBranchCommitFieldSpecification(),
      new ReleephDiffSizeFieldSpecification(),
      new ReleephDiffChurnFieldSpecification(),
    );
  }

  public function arrangeFieldsForHeaderView(array $fields) {
    return array(
      // Top group
      array(
        'left' => self::selectFields($fields, array(
          'ReleephAuthorFieldSpecification',
          'ReleephRevisionFieldSpecification',
          'ReleephOriginalCommitFieldSpecification',
          'ReleephDiffSizeFieldSpecification',
          'ReleephDiffChurnFieldSpecification',
        )),
        'right' => self::selectFields($fields, array(
          'ReleephRequestorFieldSpecification',
          'ReleephSeverityFieldSpecification',
          'ReleephStatusFieldSpecification',
          'ReleephIntentFieldSpecification',
          'ReleephBranchCommitFieldSpecification',
        ))
      ),

      // Bottom group
      array(
        'left' => self::selectFields($fields, array(
          'ReleephDiffMessageFieldSpecification',
        )),
        'right' => self::selectFields($fields, array(
          'ReleephReasonFieldSpecification',
        ))
      )
    );
  }

  public function arrangeFieldsForSelectForm(array $fields) {
    self::selectFields($fields, array(
      'ReleephStatusFieldSpecification',
      'ReleephSeverityFieldSpecification',
      'ReleephRequestorFieldSpecification',
    ));
  }

  public function sortFieldsForCommitMessage(array $fields) {
    self::selectFields($fields, array(
      'ReleephCommitMessageFieldSpecification',
      'ReleephRequestorFieldSpecification',
      'ReleephIntentFieldSpecification',
      'ReleephReasonFieldSpecification',
    ));
  }

}
