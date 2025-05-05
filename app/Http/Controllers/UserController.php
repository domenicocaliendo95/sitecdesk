<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'Non autorizzato'], 403);
        }

        $users = User::get();

        return response()->json([
            'users' => $users,
        ]);
    }
}
