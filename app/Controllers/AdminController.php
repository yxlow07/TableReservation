<?php

namespace app\Controllers;

use app\Exceptions\MethodNotAllowedException;
use app\Exceptions\UserNotFoundException;
use app\Models\AdminModel;
use app\Models\AnnouncementModel;
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

    public function crud_tables($tableId, $action): void
    {
        $model = TableModel::getTableFromDatabase($tableId);

        if ($model === false) {
            throw new \Exception('Table not found', 400);
        }

        match ($action) {
            BaseModel::UPDATE => $this->editTable($model),
            BaseModel::DELETE => TableModel::deleteTableFromDatabase($tableId),
            default => $data = BaseModel::UNDEFINED,
        };

        if ($data === BaseModel::UNDEFINED) {
            throw new MethodNotAllowedException();
        }

        redirect('/view_tables');
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

    private function uploadAnnouncements($upload = false): void
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
            if ($upload) {
                $status = App::$app->database->insert('announcements', ['title', 'summary', 'content'], $data);
            } else {
                $status = App::$app->database->update('announcements', ['title', 'summary', 'content'], $data, ['id' => $id]);
            }
        }

        App::$app->response->sendJson(['status' => $status, 'msg' => $msg], true);
    }

    public function uploadUsers(): void
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

        $this->render('upload', ['results' => $uploadResults, 'subject' => 'Users']);
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

    public function find_student(): void
    {
        $data = [];

        if (App::$app->request->isMethod('post')) {
            $query = '%' . App::$app->request->data()['query'] . '%';
            $data = App::$app->database->findAll('users', conditions: ['id' => $query, 'class'=> $query, 'name'=> $query], isSearch: true);
            App::$app->response->sendJson($data, true);
        }

        $this->render('search', ['users' => $data]);
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

    public function upload_tables(): void
    {
        $uploadResults = [];

        if (App::$app->request->isMethod('post') && isset($_FILES['csv']) && $_FILES['csv']['error'] == UPLOAD_ERR_OK) {
            $csv = file($_FILES['csv']['tmp_name'], FILE_IGNORE_NEW_LINES);
            $fail = 0;
            foreach ($csv as $line) {
                $data = str_getcsv($line);
                $tableModel = new TableModel();
                $i = 0;

                $tableModel->setTableId($data[$i++])->setCapacity($data[$i++])->setTableName($data[$i++] ?? 'Table')->setParticipants($data[$i] ?? '[]');

                if (!$tableModel->isBasicDataSet()) {
                    $uploadResults[] = "Fail to add record for table - $data[0] (incomplete data)";
                    $fail++;
                    break;
                }

                if (!$tableModel->verifyNoDuplicate()) {
                    $uploadResults[] = "Fail to add record for table - $data[0] (duplicate)";
                    $fail++;
                    break;
                }

                if ($tableModel->createTable()) {
                    $uploadResults[] = "Successfully added record for table - $data[0]";
                } else {
                    $uploadResults[] = "Fail to add record for table - $data[0] (database)";
                    $fail++;
                }
            }
            App::$app->session->setFlashMessage($fail == 0 ? 'success' : 'error', 'CSV Uploaded!');
        }

        $this->render('upload', ['results' => $uploadResults, 'subject' => 'Tables']);
    }

    public function create_announcement(): void
    {
        $model = new AnnouncementModel(App::$app->request->data());

        if (App::$app->request->isMethod('post')) {
            if ($model->validate()) {
                if (App::$app->database->insert('announcements', ['title', 'summary', 'content', 'type'], $model)) {
                    App::$app->session->setFlashMessage('success', 'Announcement created successfully');
                    redirect('/crud_announcements');
                } else {
                    App::$app->session->setFlashMessage('error', 'Failed to create announcement (database)');
                }
            }
        }

        $this->render('create_announcement', ['model' => $model]);
    }

    private function editTable(TableModel|bool $model)
    {

    }
}