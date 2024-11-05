<?php

namespace app\Models;

use AllowDynamicProperties;
use core\App;
use core\Models\ValidationModel;

#[AllowDynamicProperties]
class TablesModel extends ValidationModel
{
    public static bool $currentlyRegistered = false;
    public array $tables = [];

    public function rules(): array
    {
        return [];
    }

    public function fieldNames(): array
    {
        return [];
    }

    public static function getAllTables(): false|array
    {
        $tables = App::$app->database->findAll('tables', class: TableModel::class, fetchObject: true);

        foreach ($tables as &$table) {
            $table->convertParticipants();
            $freeSeats = $table->calculateSeatsLeft();
            $table->status = $freeSeats > 0 ? "Available - $freeSeats left" : "Full";

            if (in_array(App::$app->user->id, $table->participants)) {
                $table->currentlyRegistered = true;
                self::$currentlyRegistered = true;
            }
        }

        return $tables;
    }
}