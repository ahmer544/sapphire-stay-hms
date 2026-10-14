<?php
require_once '../config/database.php';
require_once '../config/helpers.php';
logoutUser();
redirect('index.php');
