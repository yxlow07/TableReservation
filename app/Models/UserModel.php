<?php

namespace app\Models;

use core\App;
use core\Cookies;
use core\Models\BaseModel;

class UserModel extends BaseModel
{
    public string $id = '';
    public string $name = '';
    public string $class = '';
    public string $phone = '';
    public string $password = '';
    public string|false|array $info = ''; // JSON
    public bool $isAdmin = false;
    public string $created_at = '';
    public string $updated_at = '';

    public function __construct(array $data = [])
    {
        parent::loadData($data);
        $this->decodeInfo();
    }

    public static function deleteUserFromDB(string $id): void
    {
        $res = App::$app->database->delete('users', ['id' => $id]);
        App::$app->response->sendJson(['success' => $res], true);
    }

    public function setCookies(): void
    {
        $sessionID = App::$app->session->generateSessionID();
        Cookies::setCookie('id', $this->id);
        Cookies::setCookie('sessionID', $sessionID);
        $this->decodeInfo();
        $this->info['sessionID'] = $sessionID;
        App::$app->database->update('users', ['info'], ['info' => json_encode($this->info)], ['id' => $this->id]);
    }

    public function isLogin(): bool
    {
        return !empty($this->id);
    }

    public function decodeInfo(): void
    {
        if (is_string($this->info)) {
            $this->info = json_decode($this->info, true) ?? [];
        }
    }

    // Data setters
    public function setId(string $id): UserModel
    {
        $this->id = $id;
        return $this;
    }

    public function setPhone(string $phone): UserModel
    {
        $this->phone = $phone;
        return $this;
    }

    public function setPassword(string $password): UserModel
    {
        $this->password = password_hash($password, PASSWORD_BCRYPT);
        return $this;
    }

    public function setName(string $name): UserModel
    {
        $this->name = $name;
        return $this;
    }

    public function setClass(string $class): UserModel
    {
        $this->class = $class;
        return $this;
    }

    public function setInfo(array|string $info): UserModel
    {
        $this->info = $info;
        return $this;
    }

    public function isBasicDataSet(): bool
    {
        return !empty($this->id) && !empty($this->password) && !empty($this->phone) && !empty($this->name) && !empty($this->class);
    }
}