#!/usr/bin/env php
<?php

if (extension_loaded('pcntl')) {
  echo "YES\n";
} else {
  echo "NO\n";
}
