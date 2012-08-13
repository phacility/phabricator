<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

final class AphrontFormPolicyControl extends AphrontFormControl {

  private $user;
  private $object;
  private $capability;

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function getUser() {
    return $this->user;
  }

  public function setPolicyObject(PhabricatorPolicyInterface $object) {
    $this->object = $object;
    return $this;
  }

  public function setCapability($capability) {
    $this->capability = $capability;

    $labels = array(
      PhabricatorPolicyCapability::CAN_VIEW => 'Visible To',
      PhabricatorPolicyCapability::CAN_EDIT => 'Editable By',
      PhabricatorPolicyCapability::CAN_JOIN => 'Joinable By',
    );

    $this->setLabel(idx($labels, $this->capability, 'Unknown Policy'));

    return $this;
  }

  protected function getCustomControlClass() {
    return 'aphront-form-control-policy';
  }

  private function getOptions() {
    $show_public = PhabricatorEnv::getEnvConfig('policy.allow-public');

    if ($this->capability != PhabricatorPolicyCapability::CAN_VIEW) {
      // We don't generally permit 'public' for anything except viewing.
      $show_public = false;
    }

    if ($this->getValue() == PhabricatorPolicies::POLICY_PUBLIC) {
      // If the object already has a "public" policy, show the option in
      // the dropdown even if it will be enforced as "users", so we don't
      // change the policy just because the config is changing.
      $show_public = true;
    }

    $options = array();

    if ($show_public) {
      $options[PhabricatorPolicies::POLICY_PUBLIC] = 'Public';
    }

    $options[PhabricatorPolicies::POLICY_USER] = 'All Users';

    if ($this->user->getIsAdmin()) {
      $options[PhabricatorPolicies::POLICY_ADMIN] = 'Administrators';
    }

    $options[PhabricatorPolicies::POLICY_NOONE] = 'No One';

    return $options;
  }

  protected function renderInput() {
    if (!$this->object) {
      throw new Exception("Call setPolicyObject() before rendering!");
    }
    if (!$this->capability) {
      throw new Exception("Call setCapability() before rendering!");
    }

    $policy = $this->object->getPolicy($this->capability);
    if (!$policy) {
      // TODO: Make this configurable.
      $policy = PhabricatorPolicies::POLICY_USER;
    }
    $this->setValue($policy);

    return AphrontFormSelectControl::renderSelectTag(
      $this->getValue(),
      $this->getOptions(),
      array(
        'name'      => $this->getName(),
        'disabled'  => $this->getDisabled() ? 'disabled' : null,
        'id'        => $this->getID(),
      ));
  }


}
