<?php

final class DifferentialDiffViewPolicyFieldSpecification
  extends DifferentialFieldSpecification {

  public function shouldAppearOnDiffView() {
    return true;
  }

  public function renderLabelForDiffView() {
    return pht('Visible To');
  }

  public function renderValueForDiffView() {
    $user = $this->getUser();
    $diff = $this->getDiff();

    $descriptions = PhabricatorPolicyQuery::renderPolicyDescriptions(
      $user,
      $diff);

    // TODO: Clean this up with new policy UI.
    $policy = idx($descriptions, PhabricatorPolicyCapability::CAN_VIEW);
    return $policy[1];
  }

}
