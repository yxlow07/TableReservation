<?php

namespace app\Models;

use core\App;
use core\Models\BaseModel;

class TableModel extends BaseModel
{

    public string $tableId = '';
    public string $tableName = '';
    public array|string|false $participants = [];
    public int $capacity = 0;
    public string $created_at = '';
    public string $updated_at = '';
    public string $status = '';
    public bool $currentlyRegistered = false;

    public function __construct(array $data = [])
    {
        $this->loadData($data);
    }

    public function convertParticipants(): void
    {
        if (is_string($this->participants)) {
            $this->participants = json_decode($this->participants, true) ?? [];
        }
    }

    public function calculateSeatsLeft(): int
    {
        $occupied = count($this->participants);
        $capacity = $this->capacity;
        $freeSeats = $capacity - $occupied;
        $this->status = $freeSeats > 0 ? "Available - {$freeSeats} left" : "Full";
        return $freeSeats;
    }

    public function checkUserAvailability(string $id, $tables): bool
    {
        foreach ($tables as $table) {
            $table->convertParticipants();
            if (in_array($id, $table->participants)) {
                return false;
            }
        }
        return true;
    }

    public function registerTable($tableId, string $id)
    {
        // Make sure user is not bound to other table & table is available
        $found = false;
        $msg = '';
        $status = false;
        $tables = TablesModel::getAllTables();
        $available = $this->checkUserAvailability($id, $tables);

        if (!$available) {
            $msg = 'You are already registered to a table';
        } else {
            foreach ($tables as $table) {
                if ($table->tableId == $tableId) {
                    $found = true;
                    $table->convertParticipants();
                    $freeSeats = $table->calculateSeatsLeft();
                    if ($freeSeats > 0) {
                        $table->participants[] = $id;
                        $table->participants = json_encode($table->participants);
                        App::$app->database->update('tables', ['participants'], ['participants' => $table->participants], ['tableId' => $tableId]);
                        $msg = 'Successfully registered';
                        $status = true;
                    } else {
                        $msg = 'Table is full';
                    }
                }
            }

            if (!$found) {
                $msg = 'Table not found';
            }
        }

        return ['status' => $status, 'msg' => $msg];
    }

    public function leaveTable($tableId, string $id)
    {
        $found = false;
        $msg = '';
        $status = false;
        $tables = TablesModel::getAllTables();
        $availability = $this->checkUserAvailability($id, $tables);

        if ($availability) {
            $msg = 'You are not registered to any table';
        } else {
            foreach ($tables as $table) {
                if ($table->tableId == $tableId) {
                    $found = true;
                    $table->convertParticipants();
                    $key = array_search($id, $table->participants);
                    if ($key !== false) {
                        unset($table->participants[$key]);
                        $table->participants = json_encode($table->participants);
                        App::$app->database->update('tables', ['participants'], ['participants' => $table->participants], ['tableId' => $tableId]);
                        $msg = 'Successfully left';
                        $status = true;
                    }
                }
            }

            if (!$found) {
                $msg = 'Table not found';
            }
        }

        return ['status' => $status, 'msg' => $msg];
    }

    // Setters that chain
    public function setTableId(string $tableId): TableModel
    {
        $this->tableId = $tableId;
        return $this;
    }

    public function setTableName(string $tableName): TableModel
    {
        $this->tableName = $tableName;
        return $this;
    }

    public function setParticipants(array|string|false $participants): TableModel
    {
        $this->participants = is_array($participants) ? json_encode($participants) : $participants;
        return $this;
    }

    public function setCapacity(int $capacity): TableModel
    {
        $this->capacity = $capacity;
        return $this;
    }

    public function isBasicDataSet(): bool
    {
        return !empty($this->tableId) && !empty($this->tableName) && !empty($this->capacity);
    }

    public function verifyNoDuplicate()
    {
        $check = App::$app->database->findOne('tables', ['tableId' => $this->tableId], class:TableModel::class);
        return !$check instanceof TableModel;
    }

    public function createTable()
    {
        return App::$app->database->insert('tables', ['tableId', 'capacity', 'tableName', 'participants'], $this);
    }

    public static function getTableFromDatabase($tableId): TableModel|bool
    {
        return App::$app->database->findOne('tables', ['tableId' => $tableId], class: TableModel::class);
    }

    public static function deleteTableFromDatabase(string $tableId): void
    {
        $res = App::$app->database->delete('tables', ['tableId' => $tableId]);
        App::$app->response->sendJson(['status' => $res, 'msg' => $res ? "Successfully deleted table" : "Failed to delete table"], true);
    }
}