<?php

namespace Modules\Gerador\Entities;

use Illuminate\Database\Eloquent\Model;

class DataBase extends Model
{
    public function getTableColumns() {
        return $this->getConnection()->getSchemaBuilder()->getColumnListing($this->getTable());
    }
}
