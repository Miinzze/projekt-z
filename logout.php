<?php
require_once 'config.php';

// Session beenden
session_destroy();

// Zur Startseite weiterleiten
header('Location: index.php');
exit;