<?php

final class DiffusionBrowseTableView extends DiffusionView {

  private $paths;
  private $handles = array();

  public function setPaths(array $paths) {
    assert_instances_of($paths, 'DiffusionRepositoryPath');
    $this->paths = $paths;
    return $this;
  }

  public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }

  public function render() {
    $request = $this->getDiffusionRequest();
    $repository = $request->getRepository();

    $base_path = trim($request->getPath(), '/');
    if ($base_path) {
      $base_path = $base_path.'/';
    }

    $need_pull = array();
    $rows = array();
    $show_edit = false;
    foreach ($this->paths as $path) {

      $dir_slash = null;
      $file_type = $path->getFileType();
      if ($file_type == DifferentialChangeType::FILE_DIRECTORY) {
        $browse_text = $path->getPath().'/';
        $dir_slash = '/';

        $browse_link = phutil_tag('strong', array(), $this->linkBrowse(
          $base_path.$path->getPath().$dir_slash,
          array(
            'text' => $this->renderPathIcon('dir', $browse_text),
          )));
      } else if ($file_type == DifferentialChangeType::FILE_SUBMODULE) {
        $browse_text = $path->getPath().'/';
        $browse_link = phutil_tag('strong', array(), $this->linkExternal(
          $path->getHash(),
          $path->getExternalURI(),
          $this->renderPathIcon('ext', $browse_text)));
      } else {
        if ($file_type == DifferentialChangeType::FILE_SYMLINK) {
          $type = 'link';
        } else {
          $type = 'file';
        }
        $browse_text = $path->getPath();
        $browse_link = $this->linkBrowse(
          $base_path.$path->getPath(),
          array(
            'text' => $this->renderPathIcon($type, $browse_text),
          ));
      }

      $dict = array(
        'lint'      => celerity_generate_unique_node_id(),
        'commit'    => celerity_generate_unique_node_id(),
        'date'      => celerity_generate_unique_node_id(),
        'time'      => celerity_generate_unique_node_id(),
        'author'    => celerity_generate_unique_node_id(),
        'details'   => celerity_generate_unique_node_id(),
      );

      $need_pull[$base_path.$path->getPath().$dir_slash] = $dict;
      foreach ($dict as $k => $uniq) {
        $dict[$k] = phutil_tag('span', array('id' => $uniq), '');
      }

      $rows[] = array(
        $browse_link,
        idx($dict, 'lint'),
        $dict['commit'],
        $dict['author'],
        $dict['details'],
        $dict['date'],
        $dict['time'],
      );
    }

    if ($need_pull) {
      Javelin::initBehavior(
        'diffusion-pull-lastmodified',
        array(
          'uri'   => (string)$request->generateURI(
            array(
              'action' => 'lastmodified',
              'stable' => true,
            )),
          'map' => $need_pull,
        ));
    }

    $branch = $this->getDiffusionRequest()->loadBranch();
    $show_lint = ($branch && $branch->getLintCommit());
    $lint = $request->getLint();

    $view = new AphrontTableView($rows);
    $view->setHeaders(
      array(
        pht('Path'),
        ($lint ? $lint : pht('Lint')),
        pht('Modified'),
        pht('Author/Committer'),
        pht('Details'),
        pht('Date'),
        pht('Time'),
      ));
    $view->setColumnClasses(
      array(
        '',
        'n',
        'n',
        '',
        'wide',
        '',
        'right',
      ));
    $view->setColumnVisibility(
      array(
        true,
        $show_lint,
        true,
        true,
        true,
        true,
        true,
      ));

    $view->setDeviceVisibility(
      array(
        true,
        false,
        true,
        false,
        true,
        false,
        false,
      ));


    return $view->render();
  }

  private function renderPathIcon($type, $text) {

    require_celerity_resource('diffusion-icons-css');

    return phutil_tag(
      'span',
      array(
        'class' => 'diffusion-path-icon diffusion-path-icon-'.$type,
      ),
      $text);
  }

}
