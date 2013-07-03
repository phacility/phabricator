<?php

final class DifferentialViewPolicyFieldSpecification
  extends DifferentialFieldSpecification {

  public function shouldAppearOnRevisionView() {
    return true;
  }

  public function renderLabelForRevisionView() {
    return pht('Visible To');
  }

  public function renderValueForRevisionView() {
    $user = $this->getUser();
    $revision = $this->getRevision();

    $descriptions = PhabricatorPolicyQuery::renderPolicyDescriptions(
      $user,
      $revision);

    return idx($descriptions, PhabricatorPolicyCapability::CAN_VIEW);
  }

}
