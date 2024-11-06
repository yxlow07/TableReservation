<?php

namespace app\Controllers;

use app\Exceptions\MethodNotAllowedException;
use app\Exceptions\UserNotFoundException;
use app\Models\AdminModel;
use app\Models\LoginModel;
use app\Models\ProfileModel;
use app\Models\RegisterModel;
use app\Models\TableModel;
use app\Models\UserModel;
use core\App;
use core\Controller;
use core\Database\CSVDatabase;
use core\Database\Generator;
use core\Exceptions\ViewNotFoundException;
use core\Filesystem;
use core\Models\BaseModel;
use core\Models\ValidationModel;
use core\Router\Request;
use core\Router\Response;
use core\View;

class AdminController extends Controller
{
    public function render(string $view, array $params = []): void
    {
        echo View::make(['/views/admin/'])->renderView($view, $params);
    }

    public function list_users(): void
    {
        $users = (array) App::$app->database->findAll('users');

        $this->render('users', ['users' => $users]);
    }

    public function createUsers(): void
    {
        $model = new RegisterModel(App::$app->request->data());

        if (App::$app->request->isMethod('post')) {
            if ($model->validate() && $model->verifyValidID() && $model->verifyNoDuplicate() && $model->registerUser()) {
                App::$app->session->setFlashMessage('success', 'Registered new user successfully!');
                redirect('/crud_users');
            }
        }

        echo View::make()->renderView('register', ['model' => $model]);
    }

    /**
     * @throws ViewNotFoundException
     * @throws UserNotFoundException|MethodNotAllowedException
     */
    public function crud_users($id, $action): void
    {
        $data = (array) LoginModel::getUserFromDB($id);

        match ($action) {
            BaseModel::READ => '',
            BaseModel::UPDATE => $this->editUser($data),
            BaseModel::DELETE => UserModel::deleteUserFromDB($id),
            default => $data = BaseModel::UNDEFINED,
        };

        if (isset($data[0]) && $data[0] === false) {
            throw new UserNotFoundException();
        }

        if ($data === BaseModel::UNDEFINED) {
            throw new MethodNotAllowedException();
        }

        $this->render('user_profile', ['data' => $data, 'action' => $action]);
    }

    private function editUser($data): void
    {
        $model = new ProfileModel($data);

        if (App::$app->request->isMethod('post')) {
            $model = new ProfileModel(App::$app->request->data());

            if ($model->validate() && $model->verifyNoDuplicate($data) && $model->updateDatabase($data)) {
                App::$app->session->setFlashMessage('success', 'Updated successfully!');
            }
        }

        $this->render('edit_profile', ['model' => $model, 'isAdmin' => true]);
        exit;
    }

    public function crud_announcements(): void
    {
        $data = App::$app->database->findAll('announcements');;

        if (App::$app->request->isMethod('post')) {
            $this->uploadAnnouncements();
        }

        $this->render('crud_announcements', ['announcements' => $data]);
    }

    private function uploadAnnouncements(): void
    {
        $data = json_decode(App::$app->request->data(true), true);
        $msg = ''; $status = false;

        // Validate the data
        if (!isset($data['title']) || !isset($data['summary']) || !isset($data['content']) || !isset($data['id'])) {
            $msg = 'Data is not complete';
        } else {
            foreach ($data as &$datum) {
                $datum = htmlspecialchars($datum, ENT_NOQUOTES, "UTF-8");
            }
            $id = $data['id'];
            unset($data['id']);
            $status = App::$app->database->update('announcements', ['title', 'summary', 'content'], $data, ['id' => $id]);
        }

        App::$app->response->sendJson(['status' => $status, 'msg' => $msg], true);
    }

    public function uploadUsers()
    {
        $uploadResults = [];

        if (App::$app->request->isMethod('post') && isset($_FILES['csv']) && $_FILES['csv']['error'] == UPLOAD_ERR_OK) {
            $csv = file($_FILES['csv']['tmp_name'], FILE_IGNORE_NEW_LINES);
            $fail = 0;
            foreach ($csv as $line) {
                $data = str_getcsv($line);
                $userModel = new UserModel();
                $i = 0;
                $userModel->setId($data[$i++])->setName($data[$i++] ?? 'Anonymous')->setClass($data[$i++] ?? 'Non-student')->setPhone($data[$i++] ?? '+60000000000')
                    ->setPassword($data[$i++] ?? 'abc123')->setInfo($data[$i++] ?? '[]');

                if ($userModel->isBasicDataSet()) {
                    try {
                        if ((new RegisterModel())->registerUser($userModel)) {
                            $uploadResults[] = "Successfully added record for user - $data[0]";
                        } else {
                            $uploadResults[] = "Fail to add record for user - $data[0]";
                            $fail++;
                        }
                    } catch (\Exception $exception) {
                        $uploadResults[] = "Fail to add record for user - $data[0]";
                        $fail++;
                    }
                }
            }
            App::$app->session->setFlashMessage($fail == 0 ? 'success' : 'error', 'CSV Uploaded!');
        }

        $this->render('upload_users', ['results' => $uploadResults]);
    }

    public function analysis_kehadiran()
    {
        $kehadirans = App::$app->database->findAll('kehadiran');
        $data = ['xaxis' => '[', 'yaxis' => '['];

        foreach ($kehadirans as $kehadiran) {
            $data['xaxis'] .= '"' . $kehadiran['idMurid'] . '",';
            $data['yaxis'] .= count(array_filter(json_decode($kehadiran['kehadiran']))) . ',';
        }

        $data['xaxis'] = trim($data['xaxis'], ',');
        $data['yaxis'] = trim($data['yaxis'], ',');
        $data['xaxis'] .= ']';
        $data['yaxis'] .= ']';

        $this->render('attendance', ['data' => $data]);
    }

    public function add_admin(): void
    {
        $model = new AdminModel();
        if (App::$app->request->isMethod('post')) {
            $model = new AdminModel(App::$app->request->data());

            if ($model->validate($model->newAdminRules()) && $model->verifyNoDuplicate() && $model->updateDatabase()) {
                App::$app->session->setFlashMessage('success', 'Successfully created new admin account');
                redirect();
            }
        }

        $this->render('add_admin', ['model' => $model, 'isAdmin' => true]);
    }

    public function find_student()
    {
        $data = [];

        if (App::$app->request->isMethod('post')) {
            $query = '%' . App::$app->request->data()['query'] . '%';
            $data = App::$app->database->findAll('murid', conditions: ['idMurid' => $query, 'noTel' => $query], isSearch: true);
            App::$app->response->sendJson($data, true);
        }

        $this->render('search', ['users' => $data]);
    }

    public function set_date()
    {
        $data = CSVDatabase::returnAllData('dates.csv');

        if (App::$app->request->isMethod('post')) {
            $dates = App::$app->request->data()['dates'] ?? [];

            App::$app->response->sendJson(CSVDatabase::saveToDatabase('dates.csv', $dates, 'w') ? 'Berjaya' : 'Gagal', true);
        }

        $this->render('setdate', ['data' => $data]);
    }

    public function view_tables(): void
    {
        $tables = App::$app->database->findAll('tables', class: TableModel::class, fetchObject: true);

        foreach ($tables as $table) {
            $table->convertParticipants();
            $table->calculateSeatsLeft();
        }

        $this->render('displayTables', ['tables' => $tables]);
    }
}