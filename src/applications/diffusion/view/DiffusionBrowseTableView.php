<?php

final class DiffusionBrowseTableView extends DiffusionView {

  private $paths;

  public function setPaths(array $paths) {
    assert_instances_of($paths, 'DiffusionRepositoryPath');
    $this->paths = $paths;
    return $this;
  }

  public function render() {
    $request = $this->getDiffusionRequest();
    $repository = $request->getRepository();
    require_celerity_resource('diffusion-css');

    if ($request->getPath() !== null) {
      $base_path = trim($request->getPath(), '/');
      if ($base_path) {
        $base_path = $base_path.'/';
      }
    } else {
      $base_path = '';
    }

    $need_pull = array();
    $rows = array();
    foreach ($this->paths as $path) {
      $full_path = $base_path.$path->getPath();

      $dir_slash = null;
      $file_type = $path->getFileType();
      if ($file_type == DifferentialChangeType::FILE_DIRECTORY) {
        $browse_text = $path->getPath().'/';
        $dir_slash = '/';

        $browse_link = phutil_tag('strong', array(), $this->linkBrowse(
          $full_path.$dir_slash,
          array(
            'type' => $file_type,
            'name' => $browse_text,
          )));

        $history_path = $full_path.'/';
      } else if ($file_type == DifferentialChangeType::FILE_SUBMODULE) {
        $browse_text = $path->getPath().'/';
        $browse_link = phutil_tag('strong', array(), $this->linkBrowse(
          null,
          array(
            'type' => $file_type,
            'name' => $browse_text,
            'hash' => $path->getHash(),
            'external' => $path->getExternalURI(),
          )));

        $history_path = $full_path.'/';
      } else {
        $browse_text = $path->getPath();
        $browse_link = $this->linkBrowse(
          $full_path,
          array(
            'type' => $file_type,
            'name' => $browse_text,
          ));

        $history_path = $full_path;
      }

      $history_link = $this->linkHistory($history_path);

      $dict = array(
        'lint'      => celerity_generate_unique_node_id(),
        'date'      => celerity_generate_unique_node_id(),
        'details'   => celerity_generate_unique_node_id(),
      );

      $need_pull[$full_path.$dir_slash] = $dict;
      foreach ($dict as $k => $uniq) {
        $dict[$k] = phutil_tag('span', array('id' => $uniq), '');
      }

      $rows[] = array(
        $browse_link,
        idx($dict, 'lint'),
        $dict['details'],
        $dict['date'],
        $history_link,
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
    $view->setColumnClasses(
      array(
        '',
        '',
        'wide commit-detail',
        'right',
        'right narrow',
      ));
    $view->setColumnVisibility(
      array(
        true,
        $show_lint,
        true,
        true,
        true,
      ));

    $view->setDeviceVisibility(
      array(
        true,
        false,
        false,
        false,
        false,
      ));


    return phutil_tag_div('diffusion-browse-table', $view->render());
  }

}
