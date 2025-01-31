<?= $this->extend('Layouts/default') ?>

<?= $this->section('pageTitle') ?>
SPCW App
<?= $this->endSection() ?>

<?= $this->section('header') ?>
    <div id="hero">
        <h1>Welcome to SPCW</h1>
        <p>A production grade web application with SQLite3, PHP, CodeIgniter4, and Web Awesome. Generated in minutes with <a href="https://expecto.dev/" target="_blank">Expecto.dev</a>.</p>
    </div>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php if (session('message')): ?> 
    <p style="color: green;"><?= esc(session('message')) ?></p>
<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('footer') ?>
<?= $this->include('footer') ?>
<?= $this->endSection() ?>