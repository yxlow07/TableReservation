<?php

namespace app\Controllers;

use app\Middleware\AuthMiddleware;
use app\Models\LoginModel;
use app\Models\ProfileModel;
use app\Models\TableModel;
use app\Models\TablesModel;
use app\Models\UserModel;
use core\App;
use core\Controller;
use core\Database\CSVDatabase;
use core\Database\Database;
use core\Models\ValidationModel;
use core\Router\Request;
use core\Router\Response;
use core\View;

class UserController extends Controller
{
    public function renderHome(): void
    {
        echo View::make()->renderView('index', ['nav' => App::$app->nav]);
    }

    public static function navItems(): void
    {
        $navItems = [
            'user' => [
                '/order_table' => [['fa-check-to-slot', 'fa-pencil-alt'], 'Order your table', true],
                '/profile' => ['fa-user', 'Profile'],
            ],
            'admin' => [
                '/add_admin' => [['fa-user-tie', 'fa-plus'], 'Add Admin', true],
                '/crud_users' => [['fa-users', 'fa-pencil-alt'], 'Edit Users', true],
                '/find_user' => [['fa-users', 'fa-magnifying-glass'], 'Find User Record', true],
                '/analysis_attendance' => [['fa-users', 'fa-chart-pie-simple'], 'Analysis Kehadiran', true],
                '/crud_announcements' => [['fa-megaphone', 'fa-pencil-alt'], 'Edit Announcements', true],
            ],
            'general' => [
                '/' => ['fa-house', 'Homepage'],
                '/announcements' => ['fa-megaphone', 'Announcements'],
            ],
            'end' => [
                '/logout' => ['fa-person-from-portal', 'Logout'],
            ],
            'guest' => [
                '/login' => ['fa-person-to-door', 'Login'],
                '/register' => ['fa-user-plus', 'Register'],
            ],
        ];


        $nav = [
            ...$navItems['general'],
            ...(AuthMiddleware::execute() ? (App::$app->user instanceof UserModel ? $navItems['user'] + $navItems['end'] : $navItems['admin']+ $navItems['end']) : $navItems['guest']),
        ];

        App::$app->nav = $nav;
    }

    public function profilePage(): void
    {
//        $model = new ProfileModel(App::$app->request->data());
//
//        if (App::$app->request->isMethod('post')) {
//            if ($model->type == ProfileModel::UPDATE_PROFILE) {
//                $this->handleUpdateProfile($model);
//            } else {
//                $this->handleUpdatePassword($model);
//            }
//        }
        $model = App::$app->user;
        echo View::make()->renderView('profile', ['model' => $model]);
    }

    private function handleUpdateProfile(ProfileModel $model): void
    {
        if ($model->validate() && $model->verifyNoDuplicate() && $model->updateDatabase()) {
            App::$app->session->setFlashMessage('success', 'Update successfully');
            LoginModel::setNewUpdatedUserData($model->id);
        }
    }

    private function handleUpdatePassword(ProfileModel $model): void
    {
        if ($model->validate($model->rulesUpdatePassword()) && $model->checkPassword() && $model->updateDatabasePasswordOnly()) {
            App::$app->session->setFlashMessage('success', 'Update successfully');
            LoginModel::setNewUpdatedUserData(App::$app->user->id);
        }
    }

    public function announcements(): void
    {
        $announcements = App::$app->database->findAll('announcements');
        echo View::make()->renderView('announcements', ['announcements' => $announcements]);
    }

    public function order_table(): void
    {
        $data = App::$app->request->data();
        $model = new TablesModel($data);

        $model->data = TablesModel::getAllTables();

        echo View::make()->renderView('order_table', ['model' => $model, 'registered' => TablesModel::$currentlyRegistered]);
    }

    public function order($tableId): void
    {
        $data = App::$app->request->data();
        $model = new TableModel($data);
        $status = $model->registerTable($tableId, App::$app->user->id);
        App::$app->response->sendJson(['status' => $status]);
    }

    public function leave($tableId): void
    {
        $model = new TableModel();
        $status = $model->leaveTable($tableId, App::$app->user->id);
        App::$app->response->sendJson(['status' => $status]);
    }
}