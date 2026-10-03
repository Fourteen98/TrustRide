<?php

use Illuminate\Support\Facades\Route;

// The admin dashboard lives at /admin (see app/Admin/AdminPanelProvider.php).
Route::redirect('/', '/admin');
