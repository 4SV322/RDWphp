<?php

namespace App\Http\Controllers\Campus;

use App\Http\Controllers\Controller;
use App\Models\Candidate;
use App\Models\Supervisor;
use App\Models\Applicant;
use App\Models\Admin;
use Exception;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Авторизация через кампус
 */
class LoginController extends Controller
{
    /**
     * @OA\Get(
     *     path="/campus_auth",
     *     summary="Авторизация через кампус",
     *     tags={"Auth"},
     *     @OA\Response(
     *         response="200",
     *         description="Пользователь авторизован",
     *
     *     )
     * )
     * )
     */
    public function __invoke(Request $request)
    {


        if (env('APP_DEVELOPMENT')) {
            $role = $request->input('role');
            $token = $request->input('api_token');

            $expiresTime = time() + (7 * 24 * 60 * 60);
            $cookieOptions = ['secure' => true, 'httponly' => true, 'expires' => $expiresTime, 'domain' => null, 'sameSite' => 'None'];
            setcookie('is_student', $role == 'is_student', $cookieOptions);
            setcookie('is_teacher', $role == 'is_teacher', $cookieOptions);
            setcookie('token', $token, $cookieOptions);

            $cookie = cookie('mydomain_api', $token, 60 * 24);
            return response(['url' => ''])->withCookie($cookie);
        }

        $return = false;
        $APP = [
            'ID' => 'local.6149ff4c7fcf40.88217011',
            'CODE' => 'hpSC3PDk3TGpW1tWTqozH67k2JCD9n6ZY00Zp501baj8sNWvFW',
        ];

        //  ЭТАП 1 - авторизация учетной записи в ЛИЧНОМ КАБИНЕТЕ
        //  редирект на страницу авторизации
        //  редирект обратно после успешной авторизации
        if (!isset($_REQUEST['code'])) {
            //return redirect()->away('http://exmple.com');

            //header('HTTP 302 Found');
            //header('Location: https://int.istu.edu/oauth/authorize/?client_id=' . $APP['ID']);
            return json_encode(['url' => 'https://int.istu.edu/oauth/authorize/?client_id=' . $APP['ID']]); //);
            exit;
        }

        //  ЭТАП 2 - авторизация приложения
        if (isset($_REQUEST['code'])) {
            //  формирование параметров запроса
            $url = implode('&', [
                'https://int.istu.edu/oauth/token/?grant_type=authorization_code',
                'code=' . $_REQUEST['code'],
                'client_id=' . $APP['ID'],
                'client_secret=' . $APP['CODE'],
            ]);
            //  выполнение запроса и обработка ответа

            $data = @file_get_contents($url);

            if (explode(' ', $http_response_header[0])[1] !== '200') {
                return false;
            }

            $data = json_decode($data, true);
        }

        //  ЭТАП 3 - запрос данных по учетной записи
        if (isset($data['client_endpoint']) && isset($data['access_token'])) {
            //  формирование параметров запроса
            $url = $data['client_endpoint'] . 'user.info.json?auth=' . $data['access_token'];
            //  выполнение запроса и обработка ответа
            $data = @file_get_contents($url);
            if (explode(' ', $http_response_header[0])[1] !== '200') {
                return false;
            }

            $data = json_decode($data, true);
            //  проверка наличия структуры данных
            if (isset($data['result']['email'])) {
                $return = $data['result'];
            }
        }

        $api_token = null;
        $expiresTime = time() + (7 * 24 * 60 * 60);

        $cookieOptions = ['httponly' => false, 'expires' => $expiresTime];

        Cookie::forget('is_student'); // Студент
        Cookie::forget('is_supervisor'); // Руководитель
        Cookie::forget('is_applicant'); // Соискатель
        Cookie::forget('is_admin'); // Администратор


        if ($return['is_student']) {
            setcookie('is_student', $return['is_student'], $cookieOptions);
            $api_token = $this->authStudent($return);
        }
        if ($return['is_applicant']) {
            setcookie('is_applicant', $return['is_applicant'], $cookieOptions);
            $api_token = $this->authApplicant($return);
        }
        }
        if ($return['is_admin']) {
            setcookie('is_admin', $return['is_admin'], $cookieOptions);
            $api_token = $this->authAdmin($return);
        }
        try {
            if ($return['is_supervisor']) {
                $api_token = $this->authSupervisor($return);
                setcookie('is_supervisor', $return['is_supervisor'], $cookieOptions);
            }
        } catch (Exception $e) {
        }
        $expiresTime = time() + (7 * 24 * 60 * 60);
        setcookie('token', $api_token, $cookieOptions);
        return redirect('/');
    }

    private function authSupervisor($supervisorData)
    {
        if (!isset($supervisorData['mira_id']) || $supervisorData['mira_id'] == false) {
            throw new Exception();
        }
        $mira_id = $supervisorData['mira_id'][0];

        $fio = $supervisorData['last_name'] . ' ' . $supervisorData['name'] . ' ' . $supervisorData['second_name'];
        $email = $supervisorData['email'];
        $email = $supervisorData['email'];
        $position = $supervisorData['data_teacher']['dep'];

        $api_token = hash('sha256', Str::random(60));

        $supervisor = Supervisor::where('kampus_id', $mira_id)->limit(1)->get();

        if ($supervisor->count() == 0) {
            Supervisor::create([
                'fio' => $fio,
                'email' => $email,
                'position' => $position,
                'about' => '',
                'api_token' => $api_token,
            ]);
        } else {
            Supervisor::where('fio', $fio)->limit(1)->update([
                'fio' => $fio,
                'email' => $email,
                'position' => $position,
                'api_token' => $api_token,
            ]);
        }

        return $api_token;
    }

    private function authStudent($studentData)
    {
        //работа с пользователями
        $numz = $studentData['data_student']['nomz'];
        $user = Candidate::where('numz', $numz)->limit(1)->get();
        $fio = $studentData['last_name'] . ' ' . $studentData['name'] . ' ' . $studentData['second_name'];
        $group = $studentData['data_student']['grup'];
        //высчитываем номер курса из группы
        $course = intval(explode('-', $group)[1]);
        //если сентябрь то на курс выше
        $course = date('m') > 8 ? date('y') - $course + 1 : date('y') - $course;
        $api_token = hash('sha256', Str::random(60));

        if ($user->count() == 0) {
            Candidate::create([
                'fio' => $fio,
                'email' => $studentData['email'],
                'numz' => $numz,
                'phone' => '',
                'about' => '',
                'course' => $course,
                'training_group' => $group,
                'api_token' => $api_token,
            ]);
        } else {
            Candidate::where('numz', $numz)->limit(1)->update([
                'fio' => $fio,
                'email' => $studentData['email'],
                'course' => $course,
                'training_group' => $group,
                'api_token' => $api_token,
            ]);
        }

        return $api_token;
    }
    
    private function authApplicant($applicantData)
    {
        //Работа с Соучастником
        /*
        написать логику
        */
    }

    private function authAdmin($adminData)
    {
        //Работа с Админом
        /*
        написать логику
        */
}