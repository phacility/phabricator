<?php

final class PackageDeleteMail extends PackageMail {

  protected function getVerb() {
    return "Deleted";
  }

  protected function isNewThread() {
    return false;
  }

}
