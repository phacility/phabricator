<?php

final class PonderQuestionDefaultEditCapability
  extends PhabricatorPolicyCapability {

  const CAPABILITY = 'ponder.question.default.edit';

  public function getCapabilityName() {
    return pht('Default Question Edit Policy');
  }

}
