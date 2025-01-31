<!DOCTYPE html>
<html lang="en" class="sidebar-open">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->renderSection('pageTitle', true) ?></title>
    <meta name="description" content="The small framework with powerful features">

    <link rel="shortcut icon" type="image/png" href="/favicon.ico">

    <link rel="preload" as="font" href="<?= base_url('assets/fonts/PublicSansVariable.woff2') ?>" type="font/woff2" crossorigin="anonymous">

    <!-- STYLESHEETS -->
    <link rel="stylesheet" href="https://early.webawesome.com/webawesome@3.0.0-alpha.10/dist/styles/themes/classic.css" />
    <link rel="stylesheet" href="https://early.webawesome.com/webawesome@3.0.0-alpha.10/dist/styles/webawesome.css" />
    <link rel="stylesheet" href="<?= base_url('assets/stylesheets/global.css') ?>" />

    <!-- SCRIPTS -->
    <script type="module" src="https://early.webawesome.com/webawesome@3.0.0-alpha.10/dist/webawesome.loader.js"></script>
    <script type="module" src="/assets/scripts/darkmode-state.js"></script>
    <script type="module" src="/assets/scripts/sidebar-state.js"></script>
    <script type="module" src="/assets/scripts/dialogs.js"></script>
    <script type="module" src="/assets/scripts/notifications.js"></script>
<?= $this->renderSection('scripts') ?>

<?= $this->renderSection('head') ?>
</head>
<body>

<div id="notifications">
<?php
use App\Libraries\NotificationType;

if (session('errors')): ?> 
<?php foreach (session('errors') as $error): ?>
<?= view_cell('NotificationCell', ['type' => NotificationType::ERROR, 'body' => $error, 'persistent' => true]) ?>
<?php endforeach; ?>
<?php endif; ?>
<?php if (session('message')): ?> 
<?= view_cell('NotificationCell', ['type' => NotificationType::SUCCESS, 'body' => session('message')]) ?>
<?php endif; ?>
</div>

<!-- NAVIGATION -->

<?= $this->renderSectionOrDefault('navigation') ?>
<?= view_cell('NavigationCell') ?>
<?= $this->endDefaultSection() ?>

<main>

<!-- HEADER -->

<?= $this->renderSection('header') ?>

<!-- CONTENT -->

<section class="">

<?= $this->renderSection('content') ?>

</section>

<!-- FOOTER -->

<?= $this->renderSectionOrDefault('footer') ?>
<?= $this->include('footer-minimal') ?>
<?= $this->endDefaultSection() ?>

</main>

</body>
</html>
