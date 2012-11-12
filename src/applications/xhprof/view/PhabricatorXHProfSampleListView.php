<?php

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
