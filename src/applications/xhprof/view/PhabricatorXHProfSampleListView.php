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

final class PhabricatorXHProfSampleListView extends AphrontView {

  private $samples;
  private $user;
  private $showType = false;

  public function setSamples(array $samples) {
    assert_instances_of($samples, 'PhabricatorXHProfSample');
    $this->samples = $samples;
    return $this;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function setShowType($show_type) {
    $this->showType = $show_type;
  }

  public function render() {
    $rows = array();

    if (!$this->user) {
      throw new Exception("Call setUser() before rendering!");
    }

    $user_phids = mpull($this->samples, 'getUserPHID');
    $users = id(new PhabricatorObjectHandleData($user_phids))->loadObjects();
    foreach ($this->samples as $sample) {
      $sample_link = phutil_render_tag(
        'a',
        array(
          'href' => '/xhprof/profile/'.$sample->getFilePHID().'/',
        ),
        $sample->getFilePHID());
      if ($this->showType) {
        if ($sample->getSampleRate() == 0) {
          $sample_link .= ' (manual run)';
        } else {
          $sample_link .= ' (sampled)';
        }
      }
      $rows[] = array(
        $sample_link,
        phabricator_datetime($sample->getDateCreated(), $this->user),
        number_format($sample->getUsTotal())." \xCE\xBCs",
        $sample->getHostname(),
        $sample->getRequestPath(),
        $sample->getController(),
        idx($users, $sample->getUserPHID()),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setHeaders(
      array(
        'Sample',
        'Date',
        'Wall Time',
        'Hostname',
        'Request Path',
        'Controller',
        'User',
      ));
    $table->setColumnClasses(
      array(
        '',
        '',
        'right',
        'wide wrap',
        '',
        '',
      ));

    return $table->render();
  }

}
