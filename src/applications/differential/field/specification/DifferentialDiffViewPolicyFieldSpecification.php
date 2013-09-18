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

    return idx($descriptions, PhabricatorPolicyCapability::CAN_VIEW);
  }

}
