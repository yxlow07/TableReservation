<?php

namespace app\Models;

use core\App;
use core\Cookies;

class UserModel
{
    public string $id = '';
    public string $name = '';
    public string $class = '';
    public string $phone = '';
    public string $password = '';
    public string|false|array $info = ''; // JSON
    public bool $isAdmin = false;

    public function __construct()
    {
        $this->decodeInfo();
    }

    public static function deleteUserFromDB(string $id)
    {
        $res = App::$app->database->delete('users', ['id' => $id]);
        header('Content-Type: application/json');
        echo json_encode(['success' => $res]);
        exit();
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

    public function setInfo(array $info): UserModel
    {
        $this->info = $info;
        return $this;
    }

    public function isBasicDataSet(): bool
    {
        return !empty($this->id) && !empty($this->password) && !empty($this->phone);
    }
}