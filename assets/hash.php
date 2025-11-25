<?php
$plain = "Municipal@123";
$hash  = password_hash($plain, PASSWORD_DEFAULT);
echo $hash;
