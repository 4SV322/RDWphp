<?php

namespace App\Http\Controllers\Campus;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Supervisor;
use App\Models\Director;
use App\Models\Applicant;
use App\Models\Admin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Выход из аккаунта
 */
class LogoutController extends Controller
{
    /**
     * @OA\Get(
     *     path="/campus_out",
     *     summary="Выход из аккаунта",
     *     tags={"Auth"},
     *     @OA\Response(
     *         response="200",
     *         description="Пользователь вышел из аккаунта",
     *     )
     * )
     * )
     */
    public function __invoke(Request $request)
    {
        $token = $request->get('api_token');

        Supervisor::where('api_token', $token)->update(['api_token' => null]);
        Candidate::where('api_token', $token)->update(['api_token' => null]);
        Applicant::where('api_token', $token)->update(['api_token'=> null]);
        Director::where('api_token', $token)->update(['api_token'=> null]);
        Admin::where('api_token', $token)->update(['api_token'=> null]);
        Cookie::queue(Cookie::forget('token'));
        Cookie::queue(Cookie::forget('is_student'));
        Cookie::queue(Cookie::forget('is_teacher'));
        Cookie::queue(Cookie::forget('is_applicant'));
        Cookie::queue(Cookie::forget('is_director'));
        Cookie::queue(Cookie::forget('is_admin'));
        return redirect('/'); //->away('https://int.istu.edu/?logout=yes');
    }
}