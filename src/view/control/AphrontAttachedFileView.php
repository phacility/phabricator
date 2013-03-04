<?php

final class AphrontAttachedFileView extends AphrontView {

  private $file;

  public function setFile(PhabricatorFile $file) {
    $this->file = $file;
    return $this;
  }

  public function render() {
    require_celerity_resource('aphront-attached-file-view-css');

    $file = $this->file;
    $phid = $file->getPHID();

    $thumb = phutil_tag(
      'img',
      array(
        'src'     => $file->getThumb60x45URI(),
        'width'   => 60,
        'height'  => 45,
      ));

    $name = phutil_tag(
      'a',
      array(
        'href'    => $file->getViewURI(),
        'target'  => '_blank',
      ),
      $file->getName());
    $size = number_format($file->getByteSize()).' ' .pht('bytes');

    $remove = javelin_tag(
      'a',
      array(
        'class' => 'button grey',
        'sigil' => 'aphront-attached-file-view-remove',
        // NOTE: Using 'ref' here instead of 'meta' because the file upload
        // endpoint doesn't receive request metadata and thus can't generate
        // a valid response with node metadata.
        'ref'   => $file->getPHID(),
      ),
      "\xE2\x9C\x96"); // "Heavy Multiplication X"

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
