<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{
    /**
     * Show the login form
     */
    public function showLoginForm()
    {
        return view('auth.login');
    }

    /**
     * Handle login request
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|min:6',
        ], [
            'email.required' => '이메일을 입력해주세요.',
            'email.email' => '유효한 이메일 주소를 입력해주세요.',
            'password.required' => '비밀번호를 입력해주세요.',
            'password.min' => '비밀번호는 최소 6자리 이상이어야 합니다.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $credentials = $request->only('email', 'password');
        $remember = $request->filled('remember');

        if (Auth::attempt($credentials, $remember)) {
            $request->session()->regenerate();

            // Log successful login
            \Log::info('User logged in: '.Auth::user()->email, [
                'user_id' => Auth::id(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return redirect()->intended('/dashboard');
        }

        return back()->withErrors([
            'email' => '로그인 정보가 올바르지 않습니다.',
        ])->withInput($request->except('password'));
    }

    /**
     * Handle logout request
     */
    public function logout(Request $request)
    {
        $userId = Auth::id();
        $email = Auth::user()?->email;

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // Log successful logout
        \Log::info('User logged out: '.$email, [
            'user_id' => $userId,
            'ip' => $request->ip(),
        ]);

        return redirect('/login')->with('message', '성공적으로 로그아웃되었습니다.');
    }

    /**
     * Show registration form
     */
    public function showRegistrationForm()
    {
        return view('auth.register');
    }

    /**
     * Handle registration request
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6|confirmed',
            'role' => 'required|in:headquarters,branch,store',
            'branch_id' => 'nullable|exists:branches,id',
            'store_id' => 'nullable|exists:stores,id',
        ], [
            'name.required' => '이름을 입력해주세요.',
            'email.required' => '이메일을 입력해주세요.',
            'email.email' => '유효한 이메일 주소를 입력해주세요.',
            'email.unique' => '이미 사용중인 이메일입니다.',
            'password.required' => '비밀번호를 입력해주세요.',
            'password.min' => '비밀번호는 최소 6자리 이상이어야 합니다.',
            'password.confirmed' => '비밀번호 확인이 일치하지 않습니다.',
            'role.required' => '권한을 선택해주세요.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'branch_id' => $request->branch_id,
            'store_id' => $request->store_id,
        ]);

        Auth::login($user);

        // Log successful registration
        \Log::info('New user registered: '.$user->email, [
            'user_id' => $user->id,
            'role' => $user->role,
            'ip' => $request->ip(),
        ]);

        return redirect('/dashboard')->with('message', '회원가입이 완료되었습니다.');
    }

    /**
     * Get current user info for API
     */
    public function user(Request $request)
    {
        $user = Auth::user();

        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'branch_id' => $user->branch_id,
            'store_id' => $user->store_id,
            'branch' => $user->branch?->name,
            'store' => $user->store?->name,
        ]);
    }
}
