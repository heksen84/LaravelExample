<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Regions extends Model {
    protected $table = 'kz_region';
	protected $primaryKey = 'region_id';
	public $timestamps = false;
}
