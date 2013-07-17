<?php

final class AphrontAttachedFileView extends AphrontAbstractAttachedFileView {

  public function render() {
    require_celerity_resource('aphront-attached-file-view-css');

    $file = $this->getFile();
    $phid = $file->getPHID();

    $thumb = phutil_tag(
      'img',
      array(
        'src'     => $file->getThumb60x45URI(),
        'width'   => 60,
        'height'  => 45,
      ));

    $name = $this->getName();
    $size = number_format($file->getByteSize()).' ' .pht('bytes');

    $remove = $this->getRemoveElement();

    return hsprintf(
      '<table class="aphront-attached-file-view">
        <tr>
          <td>%s</td>
          <th><strong>%s</strong><br />%s</th>
          <td class="aphront-attached-file-view-remove">%s</td>
        </tr>
      </table>',
      $thumb,
      $name,
      $size,
      $remove);
  }

}
