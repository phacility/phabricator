<?php

final class FileTypeIcon extends Phobject {

  public static function getFileIcon($filename) {
    $path_info = pathinfo($filename);
    $extension = idx($path_info, 'extension');
    switch ($extension) {
      case 'psd':
      case 'ai':
        $icon = 'fa-file-image-o';
        break;
      case 'conf':
        $icon = 'fa-wrench';
        break;
      case 'wav':
      case 'mp3':
      case 'aiff':
        $icon = 'fa-file-sound-o';
        break;
      case 'm4v':
      case 'mov':
        $icon = 'fa-file-movie-o';
        break;
      case 'sql':
      case 'db':
        $icon = 'fa-database';
        break;
      case 'xls':
      case 'csv':
        $icon = 'fa-file-excel-o';
        break;
      case 'ics':
        $icon = 'fa-calendar';
        break;
      case 'zip':
      case 'tar':
      case 'bz':
      case 'tgz':
      case 'gz':
        $icon = 'fa-file-archive-o';
        break;
      case 'png':
      case 'jpg':
      case 'bmp':
      case 'gif':
        $icon = 'fa-file-picture-o';
        break;
      case 'txt':
        $icon = 'fa-file-text-o';
        break;
      case 'doc':
      case 'docx':
        $icon = 'fa-file-word-o';
        break;
      case 'pdf':
        $icon = 'fa-file-pdf-o';
        break;
      default:
        $icon = 'fa-file-text-o';
        break;
    }
    return $icon;
  }

}
