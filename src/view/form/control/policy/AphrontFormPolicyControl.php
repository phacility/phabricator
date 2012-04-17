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

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function getUser() {
    return $this->user;
  }

  protected function getCustomControlClass() {
    return 'aphront-form-control-policy';
  }

  private function getOptions() {
    $show_public = PhabricatorEnv::getEnvConfig('policy.allow-public');

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
