<?php

namespace App\Services;

use App\Models\Visit;
use App\Models\Task;
use App\Actions\StoreTaskAction;

class TaskService
{
    public function store(Visit $visit, array $data): Task|bool
    {
        return (new StoreTaskAction)->execute($visit, $data);
    }

    public function delete(Task $task): void
    {
        $task->delete();
    }
}