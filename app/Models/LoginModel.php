<?php

namespace app\Models;

use core\App;
use core\Models\ValidationModel;

class LoginModel extends ValidationModel
{
    public string $id = '';
    public string $password = '';
    public bool $rememberMe = false;
    public array $userData = [];
    
    public function __construct(array $data)
    {
        parent::loadData($data);
    }

    public function rules(): array
    {
        return [
            'id' => [self::RULE_REQUIRED, [self::RULE_MIN, 'min' => 5], [self::RULE_MAX, 'max' => 5]],
            'password' => [self::RULE_REQUIRED, [self::RULE_MIN, 'min' => 3], [self::RULE_MAX, 'max' => 15]],
            'rememberMe' => []
        ];
    }

    public function fieldNames(): array
    {
        return [
            'id' => 'Student ID',
            'password' => 'Password'
        ];
    }

    public function verifyUser(): bool
    {
        /** @var UserModel|AdminModel $user */
        $user = self::getUserFromDB($this->id);

        if (!$user) {
            $user = self::getAdminFromDB($this->id);
            if (!$user) {
                $this->addError(false, 'id', self::RULE_MATCH, ['match', 'must be a valid existing student ID']);
                return false;
            }
        }

        $checkedPassword = $user->password ?? $user->passwordAdmin;

        if (!password_verify($this->password, $checkedPassword)) {
            $this->addError(false, 'password', self::RULE_MATCH, ['match', 'is incorrect']);
            return false;
        }

        App::$app->user = $user;

        return true;
    }

    public static function getUserFromDB(string $id): UserModel|false
    {
        /** @var UserModel|false $user */
        $user = App::$app->database->findOne('users', conditions: ['id' => $id], class: UserModel::class);

        return $user;
    }

    public static function getAdminFromDB(string $idAdmin)
    {
        return App::$app->database->findOne('admin', conditions: ['idAdmin' => $idAdmin], class: AdminModel::class);
    }

    public static function setNewUpdatedUserData(string $id): void
    {
        $user = self::getUserFromDB($id);

        App::$app->user = $user;
        App::$app->session->set('user', App::$app->user);
    }
}