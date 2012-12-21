<!DOCTYPE html>
<html>
  <head>
    <title><?php echo _e($title); ?></title>

    <?php echo $skin->getCSSResources(); ?>

  </head>
  <body>
    <div class="oblivious-info">
      <h1>
        <a href="<?php echo _e($home_uri); ?>"><?php
          echo _e($blog->getName());
        ?></a>
      </h1>
      <p><?php echo _e($blog->getDescription()); ?></p>
    </div>
    <div class="oblivious-content">
