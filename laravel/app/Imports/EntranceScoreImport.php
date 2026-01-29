<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Collection;

class EntranceScoreImport implements ToCollection
{
    public Collection $rows;

    public function collection(Collection $collection)
    {
        // skip header row
        $this->rows = $collection->skip(1);
    }
}
