<?php

namespace app\Models;

use core\App;
use core\Models\ValidationModel;

class RegisterModel extends ValidationModel
{
    public string $id = '';
    public string $class = '';
    public string $name = '';
    public string $password = '';
    public string $confirm = '';
    public string $phone = '+60000000000';
    public string $created_at = '';
    public string $updated_at = '';
    public string|false|array $info = ''; // JSON

    public function __construct(array $data = [])
    {
        parent::loadData($data);
    }

    public function rules(): array
    {
        return [
            'id' => [self::RULE_REQUIRED, [self::RULE_MIN, 'min' => 5], [self::RULE_MAX, 'max' => 5]],
            'class' => [self::RULE_REQUIRED, [self::RULE_MIN, 'min' => 4], [self::RULE_MAX, 'max' => 4]],
            'name' => [self::RULE_REQUIRED, [self::RULE_MIN, 'min' => 5], [self::RULE_MAX, 'max' => 15]],
            'password' => [self::RULE_REQUIRED, [self::RULE_MIN, 'min' => 2], [self::RULE_MAX, 'max' => 15]],
            'confirm' => [self::RULE_REQUIRED, [self::RULE_MATCH, 'match' => 'password', 'matchMsg' => 'must match with password']],
        ];
    }

    public function fieldNames(): array
    {
        return [
            'id' => 'Student ID',
            'password' => 'Password',
            'confirm' => 'Confirm password',
            'class' => 'Class',
            'name' => 'Name',
        ];
    }

    public function registerUser(?UserModel $userModel = null): bool
    {
        $this->password = password_hash($this->password, PASSWORD_BCRYPT);
        $this->info = json_encode($this->info);
        return App::$app->database->insert('users', ['id', 'name', 'class', 'phone', 'password', 'info'], !is_null($userModel) ? $userModel : $this);
    }

    public function verifyNoDuplicate(): bool
    {
        $check = self::checkDatabaseForDuplicates($this->id);
        if (!$check) {
            $this->addError(false, 'id', self::RULE_UNIQUE);
        }
        return $check;
    }

    public function verifyValidID(): bool
    {
        $check = preg_match('/^[a-dA-D][0-9]{4}$/', $this->id);
        if (!$check) {
            $this->addError(false, 'id', self::RULE_MATCH, ['match', 'must match the below pattern: D1234']);
        }
        return $check;
    }

    /**
     * @param string $id
     * @return bool If user exists, then return false
     */
    public static function checkDatabaseForDuplicates(string $id): bool
    {
        $user = App::$app->database->findOne('users', ['id' => $id], class: UserModel::class);
        if ($user instanceof UserModel) {
            return false;
        } else {
            return true;
        }
    }
}