<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use App\Models\User;
use App\Models\Order;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

Route::post('/api/users', function (Request $request) {
    // Validasi input
    $validator = Validator::make($request->all(), [
        'email' => 'required|email|unique:users,email',
        'password' => 'required|string|min:8',
        'name' => 'required|string|min:3|max:50',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    // Simpan user ke database
    $user = User::create([
        'email' => $request->email,
        'password' => bcrypt($request->password),
        'name' => $request->name,
    ]);

    // Kirim email notifikasi
    $data = [
        'email' => $user->email,
        'name' => $user->name,
    ];

    Mail::raw('Your account has been created successfully.', function ($message) use ($data) {
        $message->to($data['email'])->subject('Account Created');
    });

    Mail::raw("A new user has registered: \nName: {$data['name']}\nEmail: {$data['email']}", function ($message) {
        $message->to('admin@example.com')->subject('New User Registration');
    });

    return response()->json([
        'message' => 'User created successfully.',
        'user' => [
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'created_at' => $user->created_at,
        ]
    ], 201);
});

Route::get('/api/users', function (Request $request) {
    // Validasi input
    $validator = Validator::make($request->all(), [
        'search' => 'nullable|string',
        'page' => 'nullable|integer|min:1',
        'sortBy' => 'nullable|string|in:name,email,created_at',
    ]);

    if ($validator->fails()) {
        return response()->json(['errors' => $validator->errors()], 422);
    }

    // Default values
    $search = $request->input('search', null);
    $page = $request->input('page', 1);
    $sortBy = $request->input('sortBy', 'created_at');

    // Query users
    $query = User::query();

    if ($search) {
        $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%$search%")
              ->orWhere('email', 'like', "%$search%");
        });
    }

    $users = $query
        ->withCount('orders')
        ->orderBy($sortBy)
        ->paginate(10, ['id', 'name', 'email', 'created_at'], 'page', $page);

    // Transform
    $result = [
        'page' => $users->currentPage(),
        'users' => $users->getCollection()->map(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at,
                'orders_count' => $user->orders_count,
            ];
        }),
    ];

    return response()->json($result);
});