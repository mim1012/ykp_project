<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

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

        // Timebox 오류 해결: try-catch로 감싸서 안전하게 처리
        try {
            $loginSuccess = Auth::attempt($credentials, $remember);
        } catch (\Exception $e) {
            \Log::error('Timebox 인증 오류: '.$e->getMessage());
            // 대안: 직접 사용자 검증 (PostgreSQL 강제 연결)
            $userData = \DB::connection('pgsql')->table('users')->where('email', $credentials['email'])->first();
            if ($userData && \Hash::check($credentials['password'], $userData->password)) {
                // User 모델을 직접 생성하여 Auth::login 사용
                $user = new \App\Models\User();
                $user->id = $userData->id;
                $user->email = $userData->email;
                $user->name = $userData->name;
                $user->password = $userData->password;
                Auth::login($user, $remember);
                $loginSuccess = true;
            } else {
                $loginSuccess = false;
            }
        }

        if ($loginSuccess) {
            $request->session()->regenerate();

            // Log successful login
            \Log::info('User logged in: '.Auth::user()->email, [
                'user_id' => Auth::id(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // 명시적으로 대시보드로 리다이렉션 (intended 경로가 이상한 경우 대비)
            $intendedUrl = redirect()->intended('/dashboard')->getTargetUrl();

            // /api/activities/recent 같은 API 경로면 무시하고 대시보드로
            if (strpos($intendedUrl, '/api/') !== false) {
                return redirect('/dashboard');
            }

            return redirect()->intended('/dashboard');
        }

        return back()->withErrors([
            'email' => '로그인 정보가 올바르지 않습니다.',
        ])->withInput($request->except('password'));
    }

    /**
     * Handle logout request - 즉시 리다이렉트 (UX 개선)
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

        // 즉시 로그인 페이지로 리다이렉트 (메시지 없이)
        return redirect('/login');
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

        // PostgreSQL boolean 호환성을 위한 Raw SQL 사용
        DB::statement('INSERT INTO users (name, email, password, role, branch_id, store_id, is_active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?::boolean, ?, ?)', [
            $request->name,
            $request->email,
            Hash::make($request->password),
            $request->role,
            $request->branch_id,
            $request->store_id,
            'true',  // PostgreSQL boolean 리터럴
            now(),
            now(),
        ]);

        $user = User::where('email', $request->email)->first();

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

    /**
     * Change password for authenticated user
     */
    public function changePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'current_password' => 'required|string|current_password',
            'password' => [
                'required',
                'string',
                'confirmed',
                Password::min(8)
                    ->letters()
                    ->numbers()
                    ->symbols(),
            ],
        ], [
            'current_password.required' => '현재 비밀번호를 입력해주세요.',
            'current_password.current_password' => '현재 비밀번호가 올바르지 않습니다.',
            'password.required' => '새 비밀번호를 입력해주세요.',
            'password.confirmed' => '비밀번호 확인이 일치하지 않습니다.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => '입력값이 유효하지 않습니다.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $user = Auth::user();
        $user->password = Hash::make($request->password);
        $user->save();

        // Log successful password change
        \Log::info('Password changed for user: '.$user->email, [
            'user_id' => $user->id,
            'ip' => $request->ip(),
        ]);

        return response()->json([
            'success' => true,
            'message' => '비밀번호가 성공적으로 변경되었습니다.',
        ]);
    }
}
