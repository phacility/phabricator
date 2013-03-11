<?php

final class PhabricatorProfileHeaderView extends AphrontView {

  protected $profilePicture;
  protected $profileName;
  protected $profileDescription;
  protected $profileActions = array();
  protected $profileStatus;

  public function setProfilePicture($picture) {
    $this->profilePicture = $picture;
    return $this;
  }

  public function setName($name) {
    $this->profileName = $name;
    return $this;
  }

  public function setDescription($description) {
    $this->profileDescription = $description;
    return $this;
  }

  public function addAction($action) {
    $this->profileActions[] = $action;
    return $this;
  }

  public function setStatus($status) {
    $this->profileStatus = $status;
    return $this;
  }

  public function render() {
    require_celerity_resource('phabricator-profile-header-css');

    $image = null;
    if ($this->profilePicture) {
      $image = phutil_tag(
        'div',
        array(
          'class' => 'profile-header-picture-frame',
          'style' => 'background-image: url('.$this->profilePicture.');',
        ),
        '');
    }

    $description = $this->profileDescription;
    if ($this->profileStatus != '') {
      $description = hsprintf(
        '<strong>%s</strong>%s',
        $this->profileStatus,
        ($description != '' ? "\xE2\x80\x94".$description : ''));
    }

    return hsprintf(
      '<table class="phabricator-profile-header">
        <tr>
          <td class="profile-header-name">%s</td>
          <td class="profile-header-actions" rowspan="2">%s</td>
          <td class="profile-header-picture" rowspan="2">%s</td>
        </tr>
        <tr>
          <td class="profile-header-description">%s</td>
        </tr>
      </table>
      %s',
      $this->profileName,
      $this->profileActions,
      $image,
      $description,
      $this->renderChildren());
  }
}
